package tm.otpgateway.client.service

import android.content.Context
import android.util.Log
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import tm.otpgateway.client.data.DeliveryStatus
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.GatewayClient
import tm.otpgateway.client.data.SmsLogStore
import java.util.concurrent.TimeUnit

/**
 * Re-delivers status callbacks the gateway never acknowledged.
 *
 * Without it an SMS that left the handset while the network was down would sit
 * in the panel as `pending` until the sweeper expired it — the customer got
 * their code, the log says otherwise, and the operator gets blamed.
 */
class ReportRetryWorker(
    context: Context,
    params: WorkerParameters,
) : CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        if (DeviceStore.get(applicationContext).current() == null) return Result.success()

        val logs = SmsLogStore.get(applicationContext)
        val pending = logs.pendingReports()

        if (pending.isEmpty()) return Result.success()

        val client = GatewayClient(DeviceStore.get(applicationContext))
        var stuck = false

        for (entry in pending) {
            val status = if (entry.isSent) DeliveryStatus.SENT else DeliveryStatus.FAILED

            runCatching { client.reportStatus(entry.otpId, status) }
                .onSuccess { logs.markReported(entry.otpId) }
                .onFailure { e ->
                    Log.w(TAG, "otp ${entry.otpId} still unreported", e)

                    // A 404 means the gateway has no such log for this device —
                    // retrying forever would never fix that, so it is dropped.
                    if (e is GatewayClient.GatewayException && e.code == 404) {
                        logs.markReported(entry.otpId)
                    } else {
                        stuck = true
                    }
                }
        }

        return if (stuck) Result.retry() else Result.success()
    }

    companion object {
        private const val TAG = "ReportRetryWorker"
        private const val NAME = "gateway-report-retry"

        fun schedule(context: Context) {
            val request = OneTimeWorkRequestBuilder<ReportRetryWorker>()
                .setConstraints(
                    Constraints.Builder()
                        .setRequiredNetworkType(NetworkType.CONNECTED)
                        .build(),
                )
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
                .build()

            WorkManager.getInstance(context).enqueueUniqueWork(
                NAME,
                // REPLACE, so a fresh failure restarts the backoff rather than
                // waiting out the previous attempt's delay.
                ExistingWorkPolicy.REPLACE,
                request,
            )
        }
    }
}
