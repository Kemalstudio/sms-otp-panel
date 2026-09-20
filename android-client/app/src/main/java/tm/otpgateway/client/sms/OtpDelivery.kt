package tm.otpgateway.client.sms

import android.content.Context
import android.util.Log
import tm.otpgateway.client.data.DeliveryStatus
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.GatewayClient
import tm.otpgateway.client.data.SendSmsCommand
import tm.otpgateway.client.data.SettingsStore
import tm.otpgateway.client.data.SmsLogEntry
import tm.otpgateway.client.data.SmsLogStore
import tm.otpgateway.client.service.Alerts
import tm.otpgateway.client.service.ReportRetryWorker

/**
 * One OTP, end to end: radio, local log, gateway callback.
 *
 * Kept apart from [GatewayMessagingService] because the same steps run from the
 * retry worker when a push was handled while the network was down.
 */
object OtpDelivery {

    private const val TAG = "OtpDelivery"

    suspend fun deliver(context: Context, command: SendSmsCommand) {
        val settings = SettingsStore.get(context).current()
        val logs = SmsLogStore.get(context)

        val result = SmsSender.send(context, command, settings.subscriptionId)

        val status = when (result) {
            is SmsSender.Result.Sent -> DeliveryStatus.SENT
            is SmsSender.Result.Failed -> {
                Log.w(TAG, "otp ${command.otpId} failed: ${result.reason}")
                DeliveryStatus.FAILED
            }
        }

        // Written before the callback so a crash between the two still leaves a
        // record the retry worker can pick up.
        val entry = when (result) {
            is SmsSender.Result.Sent ->
                SmsLogEntry.sent(command.otpId, command.phone, reported = false)

            is SmsSender.Result.Failed ->
                SmsLogEntry.failed(command.otpId, command.phone, result.reason, reported = false)
        }
        logs.record(entry)

        val reported = runCatching {
            GatewayClient(DeviceStore.get(context)).reportStatus(command.otpId, status)
        }.onFailure {
            Log.e(TAG, "could not report otp ${command.otpId}", it)
        }.isSuccess

        if (reported) {
            logs.markReported(command.otpId)
        } else {
            ReportRetryWorker.schedule(context)
        }

        if (result is SmsSender.Result.Failed) {
            Alerts.smsFailed(context, command.phone, result.reason)
        }
    }
}
