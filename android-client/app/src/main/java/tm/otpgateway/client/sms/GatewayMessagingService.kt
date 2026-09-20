package tm.otpgateway.client.sms

import android.content.Context
import android.os.PowerManager
import android.util.Log
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withTimeoutOrNull
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.SendSmsCommand
import tm.otpgateway.client.data.SmsLogEntry
import tm.otpgateway.client.data.SmsLogStore
import tm.otpgateway.client.service.HeartbeatService
import tm.otpgateway.client.service.ReportRetryWorker

/**
 * Receives the gateway's data-only pushes.
 *
 * FCM gives this service a short window to finish, which is enough for one SMS
 * plus the status callback; a wake lock keeps the CPU up for that window even
 * with the screen off, and the timeout guarantees the process is never held
 * hostage by a hung radio.
 */
class GatewayMessagingService : FirebaseMessagingService() {

    override fun onMessageReceived(message: RemoteMessage) {
        val command = SendSmsCommand.fromData(message.data)

        if (command == null) {
            Log.w(TAG, "ignoring push with unexpected payload: ${message.data.keys}")
            return
        }

        if (DeviceStore.get(applicationContext).current() == null) {
            Log.w(TAG, "push for otp ${command.otpId} arrived on an unpaired handset")
            return
        }

        val wakeLock = getSystemService(PowerManager::class.java)
            ?.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, WAKE_TAG)
            ?.apply { acquire(WAKE_TIMEOUT_MS) }

        try {
            // Blocking on purpose: once onMessageReceived returns, the process
            // may be frozen, and an OTP that never reaches the radio is worse
            // than a few seconds held here.
            runBlocking {
                val finished = withTimeoutOrNull(DELIVERY_TIMEOUT_MS) {
                    OtpDelivery.deliver(applicationContext, command)
                    true
                }

                if (finished == null) {
                    Log.e(TAG, "otp ${command.otpId} timed out")
                    recordTimeout(applicationContext, command)
                }
            }
        } finally {
            runCatching { wakeLock?.takeIf { it.isHeld }?.release() }
        }
    }

    /**
     * A rotated token leaves the gateway pushing into a dead address. The
     * heartbeat carries the current token on every beat, so starting the
     * service is all it takes to repair the pairing.
     *
     * Both callbacks are overridden on purpose: messaging 25.x renamed
     * `onNewToken` to `onRegistered`, and which one fires depends on the
     * Play services build on the handset.
     */
    @Deprecated("Renamed to onRegistered in messaging 25.x; kept for older Play services.")
    override fun onNewToken(token: String) = onTokenRotated()

    override fun onRegistered(token: String) = onTokenRotated()

    private fun onTokenRotated() {
        Log.i(TAG, "FCM token rotated; re-announcing it with the next heartbeat")
        HeartbeatService.start(applicationContext)
    }

    private fun recordTimeout(context: Context, command: SendSmsCommand) {
        SmsLogStore.get(context).record(
            SmsLogEntry.failed(
                otpId = command.otpId,
                phone = command.phone,
                reason = "истекло время ожидания отправки",
                reported = false,
            ),
        )

        ReportRetryWorker.schedule(context)
    }

    companion object {
        private const val TAG = "GatewayMessaging"
        private const val WAKE_TAG = "otpgateway:delivery"
        private const val WAKE_TIMEOUT_MS = 40_000L
        private const val DELIVERY_TIMEOUT_MS = 25_000L
    }
}
