package tm.otpgateway.client.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * What the panel encodes into the pairing QR:
 * `{"pairing_code":"ABC123","api_url":"https://gateway.example.com"}`
 */
@Serializable
data class PairingPayload(
    @SerialName("pairing_code") val pairingCode: String,
    @SerialName("api_url") val apiUrl: String,
)

@Serializable
data class PairRequest(
    @SerialName("pairing_code") val pairingCode: String,
    @SerialName("device_name") val deviceName: String,
    @SerialName("fcm_token") val fcmToken: String,
    /**
     * Номер SIM, вставленной в этот телефон. Вводит оператор: прочитать его у
     * Android нельзя — getLine1Number() на большинстве прошивок и операторов
     * отдаёт пустую строку, а с Android 11 ещё и требует привилегий оператора.
     */
    @SerialName("phone_number") val phoneNumber: String? = null,
)

/**
 * `device_token` is handed out exactly once, at pairing time. The server only
 * keeps its hash, so losing it means re-pairing the handset.
 */
@Serializable
data class PairResponse(
    @SerialName("device_id") val deviceId: Long,
    @SerialName("device_name") val deviceName: String? = null,
    @SerialName("phone_number") val phoneNumber: String? = null,
    @SerialName("project_id") val projectId: Long? = null,
    @SerialName("device_token") val deviceToken: String,
)

/**
 * The heartbeat carries the current push address along with it.
 *
 * FCM rotates a token whenever the app is restored onto another handset, the
 * data is cleared, or Play services decides to — and the gateway would keep
 * pushing into the dead address until someone noticed. Re-sending it every
 * beat makes the pairing self-healing without an extra endpoint.
 */
@Serializable
data class HeartbeatRequest(
    @SerialName("fcm_token") val fcmToken: String? = null,
    /** Стойку из телефонов надо видеть целиком, включая разряжающиеся. */
    @SerialName("battery_level") val batteryLevel: Int? = null,
)

@Serializable
data class HeartbeatResponse(
    @SerialName("device_id") val deviceId: Long,
    val status: String,
    @SerialName("last_seen_at") val lastSeenAt: String? = null,
    /** Лимит задаётся в панели; телефон только показывает его оператору. */
    @SerialName("throughput_per_minute") val throughputPerMinute: Int? = null,
)

@Serializable
data class ReportStatusRequest(
    @SerialName("otp_id") val otpId: Long,
    val status: String,
)

/** Shape of every error body the gateway returns. */
@Serializable
data class ErrorResponse(
    val message: String? = null,
)

/**
 * The data-only FCM payload the gateway pushes for each OTP.
 *
 * Built by `SendOtpViaFcmJob`; every value arrives as a string because FCM data
 * messages carry no types.
 */
data class SendSmsCommand(
    val otpId: Long,
    val phone: String,
    val message: String,
) {
    companion object {
        const val TYPE = "send_sms"

        fun fromData(data: Map<String, String>): SendSmsCommand? {
            if (data["type"] != TYPE) return null

            val otpId = data["otp_id"]?.toLongOrNull() ?: return null
            val phone = data["phone"]?.takeIf { it.isNotBlank() } ?: return null
            val message = data["message"]?.takeIf { it.isNotBlank() } ?: return null

            return SendSmsCommand(otpId, phone, message)
        }
    }
}

/** The two outcomes `/devices/report-status` accepts. */
enum class DeliveryStatus(val wire: String) {
    SENT("sent"),
    FAILED("failed"),
}
