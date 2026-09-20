package tm.otpgateway.client.service

import android.content.Context
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.Worker
import androidx.work.WorkerParameters
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.SmsLogStore
import java.util.concurrent.TimeUnit

/**
 * Backstop for the heartbeat.
 *
 * WorkManager cannot run often enough to be the heartbeat itself (15 minutes is
 * its floor, the server's window is 5), but it survives the foreground service
 * being killed by an aggressive OEM battery manager and brings it back.
 */
class WatchdogWorker(
    context: Context,
    params: WorkerParameters,
) : Worker(context, params) {

    override fun doWork(): Result {
        if (DeviceStore.get(applicationContext).current() == null) {
            return Result.success()
        }

        HeartbeatService.start(applicationContext)

        // Same backstop reasoning: if a status callback is still stuck, this is
        // a free chance to retry it.
        if (SmsLogStore.get(applicationContext).pendingReports().isNotEmpty()) {
            ReportRetryWorker.schedule(applicationContext)
        }

        return Result.success()
    }

    companion object {
        private const val NAME = "gateway-heartbeat-watchdog"

        fun schedule(context: Context) {
            val request = PeriodicWorkRequestBuilder<WatchdogWorker>(15, TimeUnit.MINUTES)
                .setConstraints(
                    Constraints.Builder()
                        .setRequiredNetworkType(NetworkType.CONNECTED)
                        .build(),
                )
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request,
            )
        }

        fun cancel(context: Context) {
            WorkManager.getInstance(context).cancelUniqueWork(NAME)
        }
    }
}
