package tm.otpgateway.client.data

import kotlinx.serialization.Serializable
import java.util.Calendar

/**
 * One attempt at handing an OTP to the carrier.
 *
 * [otpId] is `0` for a test message sent from the settings screen: it belongs
 * to no gateway log, so it is never reported back.
 */
@Serializable
data class SmsLogEntry(
    val otpId: Long,
    val phone: String,
    val at: Long,
    val status: String,
    val reason: String? = null,
    val reported: Boolean = false,
) {
    val isSent: Boolean
        get() = status == DeliveryStatus.SENT.wire

    val isTest: Boolean
        get() = otpId == 0L

    companion object {
        fun sent(otpId: Long, phone: String, reported: Boolean): SmsLogEntry = SmsLogEntry(
            otpId = otpId,
            phone = phone,
            at = System.currentTimeMillis(),
            status = DeliveryStatus.SENT.wire,
            reported = reported,
        )

        fun failed(otpId: Long, phone: String, reason: String, reported: Boolean): SmsLogEntry =
            SmsLogEntry(
                otpId = otpId,
                phone = phone,
                at = System.currentTimeMillis(),
                status = DeliveryStatus.FAILED.wire,
                reason = reason,
                reported = reported,
            )
    }
}

/** What the dashboard counts off the log. */
data class SmsStats(
    val sentToday: Int = 0,
    val failedToday: Int = 0,
    val sentTotal: Int = 0,
    val failedTotal: Int = 0,
    val pendingReports: Int = 0,
    val lastAt: Long = 0L,
) {
    val totalToday: Int get() = sentToday + failedToday

    /** Share of today's attempts the carrier accepted, 0..100. */
    val successRate: Int
        get() = if (totalToday == 0) 100 else (sentToday * 100) / totalToday
}

/** Midnight of the day [at] falls on, in the phone's own timezone. */
fun startOfDay(at: Long): Long = Calendar.getInstance().apply {
    timeInMillis = at
    set(Calendar.HOUR_OF_DAY, 0)
    set(Calendar.MINUTE, 0)
    set(Calendar.SECOND, 0)
    set(Calendar.MILLISECOND, 0)
}.timeInMillis

/**
 * Counters off a log snapshot.
 *
 * Pure, so Compose can recompute it from the list it already has instead of
 * reaching back into the store on every frame.
 */
fun statsOf(entries: List<SmsLogEntry>, now: Long = System.currentTimeMillis()): SmsStats {
    val since = startOfDay(now)

    return SmsStats(
        sentToday = entries.count { it.at >= since && it.isSent },
        failedToday = entries.count { it.at >= since && !it.isSent },
        sentTotal = entries.count { it.isSent },
        failedTotal = entries.count { !it.isSent },
        pendingReports = entries.count { !it.reported && !it.isTest },
        lastAt = entries.maxOfOrNull { it.at } ?: 0L,
    )
}
