package tm.otpgateway.client.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import tm.otpgateway.client.MainActivity
import tm.otpgateway.client.R
import tm.otpgateway.client.data.SettingsStore

/**
 * Loud notifications for the two failures nobody may sleep through: the
 * handset losing the gateway, and an OTP that never made it to the carrier.
 *
 * The quiet ongoing notification is [HeartbeatService]'s own; this channel is
 * separate so an operator can silence one without losing the other.
 */
object Alerts {

    private const val CHANNEL_ID = "gateway_alerts"
    private const val ID_OFFLINE = 2001
    private const val ID_SMS_FAILED = 2002

    fun offline(context: Context, minutes: Long) {
        notify(
            context,
            ID_OFFLINE,
            "Шлюз потерял связь с сервером",
            if (minutes > 0) {
                "Нет успешного heartbeat уже $minutes мин. Панель считает телефон offline и не шлёт на него коды."
            } else {
                "Панель считает телефон offline и не шлёт на него коды."
            },
        )
    }

    fun clearOffline(context: Context) {
        NotificationManagerCompat.from(context).cancel(ID_OFFLINE)
    }

    fun smsFailed(context: Context, phone: String, reason: String) {
        notify(context, ID_SMS_FAILED, "SMS не отправлена", "$phone — $reason")
    }

    private fun notify(context: Context, id: Int, title: String, body: String) {
        if (!SettingsStore.get(context).current().alertsEnabled) return

        val manager = NotificationManagerCompat.from(context)
        if (!manager.areNotificationsEnabled()) return

        createChannel(context)

        val open = PendingIntent.getActivity(
            context,
            id,
            Intent(context, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_gateway)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_ERROR)
            .setAutoCancel(true)
            .setContentIntent(open)
            .build()

        // Suppressed by the system when POST_NOTIFICATIONS was denied, which is
        // exactly the behaviour we want — no crash, no nagging.
        runCatching { manager.notify(id, notification) }
    }

    private fun createChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val channel = NotificationChannel(
            CHANNEL_ID,
            context.getString(R.string.alerts_channel),
            NotificationManager.IMPORTANCE_HIGH,
        ).apply {
            description = context.getString(R.string.alerts_channel_description)
        }

        context.getSystemService(NotificationManager::class.java)
            ?.createNotificationChannel(channel)
    }
}
