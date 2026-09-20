package tm.otpgateway.client.data

import android.util.Log
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import okhttp3.Call
import okhttp3.Callback
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import java.io.IOException
import java.util.concurrent.TimeUnit
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlin.coroutines.suspendCoroutine

/**
 * The gateway endpoints this handset talks to.
 *
 * Pairing is the only unauthenticated call; everything after it is signed with
 * the device token in `X-Device-Token`.
 */
class GatewayClient(private val store: DeviceStore) {

    class GatewayException(
        val code: Int,
        override val message: String,
    ) : Exception(message)

    suspend fun pair(
        apiUrl: String,
        pairingCode: String,
        deviceName: String,
        fcmToken: String,
        phoneNumber: String?,
    ): PairResponse {
        val body = json.encodeToString(
            PairRequest.serializer(),
            PairRequest(pairingCode, deviceName, fcmToken, phoneNumber),
        )

        val response = post(
            url = "${apiUrl.trimEnd('/')}/api/v1/devices/pair",
            body = body,
            deviceToken = null,
        )

        return json.decodeFromString(PairResponse.serializer(), response)
    }

    /**
     * Reports in, and re-states the push address while doing so — see
     * [HeartbeatRequest] for why the token rides along.
     */
    suspend fun heartbeat(fcmToken: String? = null, batteryLevel: Int? = null): HeartbeatResponse {
        val registration = requireRegistration()

        val response = post(
            url = "${registration.apiUrl}/api/v1/devices/heartbeat",
            body = json.encodeToString(
                HeartbeatRequest.serializer(),
                HeartbeatRequest(fcmToken, batteryLevel),
            ),
            deviceToken = registration.deviceToken,
        )

        return json.decodeFromString(HeartbeatResponse.serializer(), response)
    }

    suspend fun reportStatus(otpId: Long, status: DeliveryStatus) {
        val registration = requireRegistration()

        val body = json.encodeToString(
            ReportStatusRequest.serializer(),
            ReportStatusRequest(otpId, status.wire),
        )

        post(
            url = "${registration.apiUrl}/api/v1/devices/report-status",
            body = body,
            deviceToken = registration.deviceToken,
        )
    }

    private fun requireRegistration(): DeviceStore.Registration =
        store.current() ?: throw GatewayException(0, "device is not paired")

    private suspend fun post(url: String, body: String, deviceToken: String?): String =
        withContext(Dispatchers.IO) {
            val request = Request.Builder()
                .url(url)
                .post(body.toRequestBody(JSON_MEDIA_TYPE))
                // The gateway answers JSON for /api/* regardless, but being
                // explicit keeps the contract obvious from the wire.
                .header("Accept", "application/json")
                .apply { deviceToken?.let { header("X-Device-Token", it) } }
                .build()

            val response = http.newCall(request).await()

            response.use {
                val payload = it.body?.string().orEmpty()

                if (!it.isSuccessful) {
                    throw GatewayException(it.code, errorMessage(payload, it.code))
                }

                payload
            }
        }

    private fun errorMessage(payload: String, code: Int): String = runCatching {
        json.decodeFromString(ErrorResponse.serializer(), payload).message
    }.getOrNull()?.takeIf { it.isNotBlank() } ?: "HTTP $code"

    /** Bridges OkHttp's callback API onto coroutines without pulling in Retrofit. */
    private suspend fun Call.await(): Response = suspendCoroutine { continuation ->
        enqueue(object : Callback {
            override fun onResponse(call: Call, response: Response) {
                continuation.resume(response)
            }

            override fun onFailure(call: Call, e: IOException) {
                Log.w(TAG, "request failed: ${call.request().url}", e)
                continuation.resumeWithException(e)
            }
        })
    }

    companion object {
        private const val TAG = "GatewayClient"

        private val JSON_MEDIA_TYPE = "application/json; charset=utf-8".toMediaType()

        val json = Json {
            ignoreUnknownKeys = true
            explicitNulls = false
        }

        /**
         * Timeouts are deliberately short: an OTP is worthless late, and a
         * hung request would otherwise eat the whole window FCM gives the
         * messaging service to finish its work.
         */
        private val http = OkHttpClient.Builder()
            .connectTimeout(10, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .build()
    }
}
