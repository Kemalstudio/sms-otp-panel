package tm.otpgateway.client

import android.app.Application
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.SmsLogStore
import tm.otpgateway.client.service.HeartbeatService
import tm.otpgateway.client.service.ReportRetryWorker
import tm.otpgateway.client.service.WatchdogWorker

class OtpGatewayApp : Application() {

    override fun onCreate() {
        super.onCreate()

        // A handset that is already paired should be reporting in the moment
        // the process comes up, however it was started.
        if (DeviceStore.get(this).current() == null) return

        HeartbeatService.start(this)
        WatchdogWorker.schedule(this)

        // The process may have died between sending an SMS and telling the
        // panel about it — the queue survives in the log, so flush it.
        if (SmsLogStore.get(this).pendingReports().isNotEmpty()) {
            ReportRetryWorker.schedule(this)
        }
    }
}
