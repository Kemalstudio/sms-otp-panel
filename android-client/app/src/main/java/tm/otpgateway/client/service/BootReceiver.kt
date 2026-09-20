package tm.otpgateway.client.service

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * A gateway handset is expected to sit on a charger and just work, so the
 * heartbeat has to come back on its own after a reboot or an app update.
 */
class BootReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        when (intent.action) {
            Intent.ACTION_BOOT_COMPLETED,
            Intent.ACTION_MY_PACKAGE_REPLACED,
            -> {
                HeartbeatService.start(context)
                WatchdogWorker.schedule(context)
            }
        }
    }
}
