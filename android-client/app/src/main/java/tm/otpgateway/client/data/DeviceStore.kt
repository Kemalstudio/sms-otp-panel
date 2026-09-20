package tm.otpgateway.client.data

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Where the device token lives.
 *
 * The token is the handset's only credential and is irrecoverable once lost, so
 * it goes into an AES-backed keystore-wrapped preference file rather than plain
 * SharedPreferences.
 */
class DeviceStore private constructor(private val prefs: SharedPreferences) {

    private val _state = MutableStateFlow(readRegistration())

    /** Null until the handset has been paired. */
    val registration: StateFlow<Registration?> = _state.asStateFlow()

    data class Registration(
        val apiUrl: String,
        val deviceId: Long,
        val deviceToken: String,
        val deviceName: String,
        val phoneNumber: String?,
    )

    fun save(
        apiUrl: String,
        response: PairResponse,
        fallbackName: String,
        fallbackPhoneNumber: String?,
    ) {
        prefs.edit()
            .putString(KEY_API_URL, apiUrl.trimEnd('/'))
            .putLong(KEY_DEVICE_ID, response.deviceId)
            .putString(KEY_DEVICE_TOKEN, response.deviceToken)
            .putString(KEY_DEVICE_NAME, response.deviceName ?: fallbackName)
            .putString(KEY_PHONE_NUMBER, response.phoneNumber ?: fallbackPhoneNumber)
            .apply()

        _state.value = readRegistration()
    }

    /**
     * Wipes the pairing. The server row stays behind and simply stops reporting
     * in — re-pairing creates a new device rather than reviving the old one.
     */
    fun clear() {
        prefs.edit().clear().apply()
        _state.value = null
    }

    fun current(): Registration? = _state.value

    private fun readRegistration(): Registration? {
        val apiUrl = prefs.getString(KEY_API_URL, null) ?: return null
        val token = prefs.getString(KEY_DEVICE_TOKEN, null) ?: return null
        val id = prefs.getLong(KEY_DEVICE_ID, 0L).takeIf { it > 0L } ?: return null

        return Registration(
            apiUrl = apiUrl,
            deviceId = id,
            deviceToken = token,
            deviceName = prefs.getString(KEY_DEVICE_NAME, null).orEmpty(),
            phoneNumber = prefs.getString(KEY_PHONE_NUMBER, null),
        )
    }

    companion object {
        private const val FILE = "gateway_device"
        private const val KEY_API_URL = "api_url"
        private const val KEY_DEVICE_ID = "device_id"
        private const val KEY_DEVICE_TOKEN = "device_token"
        private const val KEY_DEVICE_NAME = "device_name"
        private const val KEY_PHONE_NUMBER = "phone_number"

        @Volatile
        private var instance: DeviceStore? = null

        fun get(context: Context): DeviceStore =
            instance ?: synchronized(this) {
                instance ?: DeviceStore(openPrefs(context.applicationContext)).also { instance = it }
            }

        private fun openPrefs(context: Context): SharedPreferences {
            val masterKey = MasterKey.Builder(context)
                .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                .build()

            return EncryptedSharedPreferences.create(
                context,
                FILE,
                masterKey,
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
            )
        }
    }
}
