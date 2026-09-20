package tm.otpgateway.client.service

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.LifecycleService
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withTimeoutOrNull
import tm.otpgateway.client.MainActivity
import tm.otpgateway.client.R
import tm.otpgateway.client.data.BatteryGauge
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.FcmTokens
import tm.otpgateway.client.data.GatewayClient
import tm.otpgateway.client.data.SmsLogStore

/**
 * Keeps this handset inside the gateway's dispatch pool.
 *
 * The server drops a device after [DEVICE_TIMEOUT_MINUTES] minutes without a
 * heartbeat, so the interval is set well below that to survive one lost
 * request. A foreground service is the only way to hold this cadence with the
 * screen off — WorkManager's floor is 15 minutes, three times too slow.
 */
class HeartbeatService : LifecycleService() {

    private var loop: Job? = null

    override fun onCreate() {
        super.onCreate()
        createChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        super.onStartCommand(intent, flags, startId)

        startForegroundCompat()

        if (intent?.action == ACTION_PING) {
            // Triggered by the app's "check the connection" button: jump the
            // delay instead of waiting out the current interval.
            wake.trySend(Unit)
        }

        if (loop?.isActive != true) {
            loop = lifecycleScope.launch { beatForever() }
        }

        // Restarted by the system if it gets killed under memory pressure.
        return START_STICKY
    }

    override fun onBind(intent: Intent): IBinder? {
        super.onBind(intent)
        return null
    }

    override fun onDestroy() {
        loop?.cancel()
        _online.value = false
        super.onDestroy()
    }

    private suspend fun beatForever() {
        val store = DeviceStore.get(applicationContext)
        val client = GatewayClient(store)

        while (lifecycleScope.isActive) {
            if (store.current() == null) {
                // Unpaired — nothing to report, and no reason to keep a
                // notification in the shade.
                _online.value = false
                stopSelf()
                return
            }

            _beating.value = true

            // Sent on every beat so a rotated push address repairs itself; see
            // HeartbeatRequest.
            val token = FcmTokens.currentOrNull()
            val battery = BatteryGauge.level(applicationContext)

            val beat = runCatching { client.heartbeat(token, battery) }
                .onFailure { Log.w(TAG, "heartbeat failed", it) }

            val ok = beat.isSuccess

            // Лимит скорости живёт в панели — телефон только показывает его.
            beat.getOrNull()?.throughputPerMinute?.let { _throughput.value = it }

            _beating.value = false
            _online.value = ok

            if (ok) {
                _lastBeatAt.value = System.currentTimeMillis()
                _failures.value = 0
                Alerts.clearOffline(applicationContext)

                // A regained connection is the moment to flush anything the
                // gateway never acknowledged.
                if (SmsLogStore.get(applicationContext).pendingReports().isNotEmpty()) {
                    ReportRetryWorker.schedule(applicationContext)
                }
            } else {
                _failures.value += 1

                if (_failures.value == FAILURES_BEFORE_ALERT) {
                    Alerts.offline(applicationContext, minutesSinceLastBeat())
                }
            }

            updateNotification(ok)

            withTimeoutOrNull(if (ok) INTERVAL_MS else RETRY_MS) { wake.receive() }
        }
    }

    private fun minutesSinceLastBeat(): Long {
        val last = _lastBeatAt.value
        if (last == 0L) return 0L

        return (System.currentTimeMillis() - last) / 60_000L
    }

    private fun startForegroundCompat() {
        val notification = buildNotification(online = _online.value)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startForeground(
                NOTIFICATION_ID,
                notification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE,
            )
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }
    }

    private fun updateNotification(online: Boolean) {
        val manager = getSystemService(NotificationManager::class.java)
        runCatching { manager?.notify(NOTIFICATION_ID, buildNotification(online)) }
    }

    private fun buildNotification(online: Boolean): Notification {
        val open = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val stats = SmsLogStore.get(applicationContext).stats()
        val state = getString(
            if (online) R.string.notification_online else R.string.notification_offline,
        )

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_gateway)
            .setContentTitle(getString(R.string.notification_title))
            .setContentText(getString(R.string.notification_summary, state, stats.sentToday))
            .setOngoing(true)
            .setSilent(true)
            .setShowWhen(false)
            .setContentIntent(open)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val channel = NotificationChannel(
            CHANNEL_ID,
            getString(R.string.notification_channel),
            NotificationManager.IMPORTANCE_LOW,
        ).apply {
            description = getString(R.string.notification_channel_description)
            setShowBadge(false)
        }

        getSystemService(NotificationManager::class.java)?.createNotificationChannel(channel)
    }

    companion object {
        private const val TAG = "HeartbeatService"
        private const val CHANNEL_ID = "gateway_heartbeat"
        private const val NOTIFICATION_ID = 1001
        private const val ACTION_PING = "tm.otpgateway.client.PING"

        /** Must stay below the server's window, with room for one lost request. */
        const val DEVICE_TIMEOUT_MINUTES = 5L
        private const val INTERVAL_MS = 90_000L
        private const val RETRY_MS = 20_000L

        /** Roughly a minute of failed retries before the operator is told. */
        private const val FAILURES_BEFORE_ALERT = 3

        private val wake = Channel<Unit>(Channel.CONFLATED)

        private val _online = MutableStateFlow(false)
        val online: StateFlow<Boolean> = _online.asStateFlow()

        private val _beating = MutableStateFlow(false)
        val beating: StateFlow<Boolean> = _beating.asStateFlow()

        private val _lastBeatAt = MutableStateFlow(0L)
        val lastBeatAt: StateFlow<Long> = _lastBeatAt.asStateFlow()

        private val _failures = MutableStateFlow(0)
        val failures: StateFlow<Int> = _failures.asStateFlow()

        private val _throughput = MutableStateFlow(0)

        /** Лимит SMS в минуту, назначенный этому телефону в панели. */
        val throughput: StateFlow<Int> = _throughput.asStateFlow()

        fun start(context: Context) {
            if (DeviceStore.get(context).current() == null) return

            ContextCompat.startForegroundService(
                context,
                Intent(context, HeartbeatService::class.java),
            )
        }

        /** Beat now instead of at the end of the current interval. */
        fun ping(context: Context) {
            if (DeviceStore.get(context).current() == null) return

            wake.trySend(Unit)

            ContextCompat.startForegroundService(
                context,
                Intent(context, HeartbeatService::class.java).setAction(ACTION_PING),
            )
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, HeartbeatService::class.java))
            _online.value = false
            _failures.value = 0
        }
    }
}
