package tm.otpgateway.client.data

import android.content.Context
import android.content.SharedPreferences
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Operator preferences. Nothing secret lives here, so plain preferences are
 * enough — the credential is in [DeviceStore].
 */
class SettingsStore private constructor(private val prefs: SharedPreferences) {

    private val _state = MutableStateFlow(read())
    val state: StateFlow<GatewaySettings> = _state.asStateFlow()

    fun current(): GatewaySettings = _state.value

    /** [GatewaySettings.SIM_DEFAULT] hands the choice back to the system. */
    fun setSubscriptionId(id: Int) = put { putInt(KEY_SUBSCRIPTION, id) }

    fun setAlertsEnabled(enabled: Boolean) = put { putBoolean(KEY_ALERTS, enabled) }

    private fun put(edit: SharedPreferences.Editor.() -> Unit) {
        prefs.edit().apply(edit).apply()
        _state.value = read()
    }

    private fun read() = GatewaySettings(
        subscriptionId = prefs.getInt(KEY_SUBSCRIPTION, GatewaySettings.SIM_DEFAULT),
        alertsEnabled = prefs.getBoolean(KEY_ALERTS, true),
    )

    companion object {
        private const val FILE = "gateway_settings"
        private const val KEY_SUBSCRIPTION = "subscription_id"
        private const val KEY_ALERTS = "alerts_enabled"

        @Volatile
        private var instance: SettingsStore? = null

        fun get(context: Context): SettingsStore =
            instance ?: synchronized(this) {
                instance ?: SettingsStore(
                    context.applicationContext.getSharedPreferences(FILE, Context.MODE_PRIVATE),
                ).also { instance = it }
            }
    }
}

data class GatewaySettings(
    val subscriptionId: Int = SIM_DEFAULT,
    val alertsEnabled: Boolean = true,
) {
    companion object {
        const val SIM_DEFAULT = -1
    }
}
