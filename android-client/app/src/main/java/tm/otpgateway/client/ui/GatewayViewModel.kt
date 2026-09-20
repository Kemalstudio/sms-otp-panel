package tm.otpgateway.client.ui

import android.app.Application
import android.os.Build
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import tm.otpgateway.client.BuildConfig
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.FcmTokens
import tm.otpgateway.client.data.GatewayClient
import tm.otpgateway.client.data.PairingPayload
import tm.otpgateway.client.data.SettingsStore
import tm.otpgateway.client.data.SmsLogEntry
import tm.otpgateway.client.data.SmsLogStore
import tm.otpgateway.client.service.HeartbeatService
import tm.otpgateway.client.service.ReportRetryWorker
import tm.otpgateway.client.service.WatchdogWorker
import tm.otpgateway.client.sms.SmsSender

/**
 * Everything the screens need: the pairing, what has been sent, and the
 * operator's own preferences.
 *
 * One view model rather than three, because every screen is a view onto the
 * same single-device state and splitting it would only add plumbing.
 */
class GatewayViewModel(app: Application) : AndroidViewModel(app) {

    private val store = DeviceStore.get(app)
    private val client = GatewayClient(store)
    private val logs = SmsLogStore.get(app)
    private val settingsStore = SettingsStore.get(app)

    data class UiState(
        val busy: Boolean = false,
        val testBusy: Boolean = false,
        val error: String? = null,
        val notice: String? = null,
        val deviceName: String = defaultDeviceName(),
        val simNumber: String = "+993",
    )

    private val _ui = MutableStateFlow(UiState())
    val ui: StateFlow<UiState> = _ui.asStateFlow()

    val registration = store.registration
    val entries = logs.entries
    val settings = settingsStore.state

    fun onDeviceNameChanged(value: String) {
        _ui.value = _ui.value.copy(deviceName = value.take(120))
    }

    /**
     * Номер этой SIM. Спрашиваем у человека, потому что Android его не отдаёт:
     * getLine1Number() почти везде возвращает пустую строку.
     */
    fun onSimNumberChanged(value: String) {
        _ui.value = _ui.value.copy(simNumber = value.filter { it.isDigit() || it == '+' }.take(20))
    }

    fun dismissError() {
        _ui.value = _ui.value.copy(error = null)
    }

    fun dismissNotice() {
        _ui.value = _ui.value.copy(notice = null)
    }

    fun onScanFailed(message: String) {
        _ui.value = _ui.value.copy(busy = false, error = message)
    }

    /**
     * Accepts both what the QR carries and what an operator can type by hand:
     * the raw JSON payload, or a bare 6-character code once [fallbackApiUrl] is
     * known.
     */
    fun pair(scanned: String, fallbackApiUrl: String?) {
        val payload = parse(scanned, fallbackApiUrl)

        if (payload == null) {
            _ui.value = _ui.value.copy(error = "Не удалось разобрать код привязки")
            return
        }

        startPairing(payload)
    }

    private fun startPairing(payload: PairingPayload) {
        if (_ui.value.busy) return

        if (!BuildConfig.HAS_FIREBASE_CONFIG) {
            _ui.value = _ui.value.copy(
                error = "google-services.json не добавлен в сборку — push-сообщения работать не будут",
            )
            return
        }

        _ui.value = _ui.value.copy(busy = true, error = null)

        viewModelScope.launch {
            val name = _ui.value.deviceName.ifBlank { defaultDeviceName() }

            val sim = _ui.value.simNumber.trim().takeIf { it.length > 4 }

            val result = runCatching {
                client.pair(payload.apiUrl, payload.pairingCode, name, FcmTokens.require(), sim)
            }

            result.onSuccess { response ->
                store.save(payload.apiUrl, response, name, sim)

                val context = getApplication<Application>()
                HeartbeatService.start(context)
                WatchdogWorker.schedule(context)

                _ui.value = _ui.value.copy(busy = false, error = null, notice = "Телефон подключён к шлюзу")
            }.onFailure { e ->
                _ui.value = _ui.value.copy(busy = false, error = describe(e))
            }
        }
    }

    fun unpair() {
        val context = getApplication<Application>()

        HeartbeatService.stop(context)
        WatchdogWorker.cancel(context)
        store.clear()
    }

    /** "Проверить связь": beat right now instead of at the next interval. */
    fun ping() {
        HeartbeatService.ping(getApplication())
        _ui.value = _ui.value.copy(notice = "Отправляю heartbeat…")
    }

    fun setSubscriptionId(id: Int) = settingsStore.setSubscriptionId(id)

    fun setAlertsEnabled(enabled: Boolean) = settingsStore.setAlertsEnabled(enabled)

    fun clearLog() {
        logs.clear()
        _ui.value = _ui.value.copy(notice = "Журнал очищен")
    }

    fun retryPendingReports() {
        val pending = logs.pendingReports().size

        if (pending == 0) {
            _ui.value = _ui.value.copy(notice = "Все статусы уже доставлены в панель")
            return
        }

        ReportRetryWorker.schedule(getApplication())
        _ui.value = _ui.value.copy(notice = "Досылаю статусы: $pending")
    }

    /**
     * Sends a real SMS from the selected SIM without involving the gateway —
     * the fastest way to tell "the panel is not sending" from "this SIM cannot
     * send at all".
     */
    fun sendTestSms(phone: String) {
        val target = phone.trim()

        if (target.length < 5) {
            _ui.value = _ui.value.copy(error = "Введите номер получателя")
            return
        }

        if (_ui.value.testBusy) return
        _ui.value = _ui.value.copy(testBusy = true)

        viewModelScope.launch {
            val context = getApplication<Application>()
            val result = SmsSender.send(
                context = context,
                phone = target,
                message = "OTP Gateway: тестовое сообщение. Шлюз настроен верно.",
                subscriptionId = settingsStore.current().subscriptionId,
            )

            when (result) {
                is SmsSender.Result.Sent -> {
                    logs.record(SmsLogEntry.sent(0L, target, reported = true))
                    _ui.value = _ui.value.copy(testBusy = false, notice = "Тестовое SMS отправлено")
                }

                is SmsSender.Result.Failed -> {
                    logs.record(SmsLogEntry.failed(0L, target, result.reason, reported = true))
                    _ui.value = _ui.value.copy(
                        testBusy = false,
                        error = "Не отправлено: ${result.reason}",
                    )
                }
            }
        }
    }

    private fun describe(e: Throwable): String = when (e) {
        is GatewayClient.GatewayException -> when (e.code) {
            404 -> "Код недействителен или истёк — сгенерируйте новый в панели"
            422 -> "Панель отклонила данные: ${e.message}"
            else -> e.message
        }

        else -> "Нет связи с сервером: ${e.message ?: e::class.simpleName}"
    }

    private fun parse(scanned: String, fallbackApiUrl: String?): PairingPayload? {
        val trimmed = scanned.trim()

        // What the QR carries.
        val fromJson = runCatching {
            GatewayClient.json.decodeFromString(PairingPayload.serializer(), trimmed)
        }.getOrNull()

        if (fromJson != null && fromJson.apiUrl.isNotBlank() && fromJson.pairingCode.isNotBlank()) {
            return fromJson
        }

        // Typed by hand: the panel prints the 6-character code next to the QR,
        // and the operator supplies the server address separately.
        val code = trimmed.uppercase()
        if (code.length == 6 && code.all { it.isLetterOrDigit() } && !fallbackApiUrl.isNullOrBlank()) {
            return PairingPayload(code, normalizeUrl(fallbackApiUrl))
        }

        return null
    }

    /** Accepts "gateway.example.com" as readily as a full URL. */
    private fun normalizeUrl(raw: String): String {
        val trimmed = raw.trim().trimEnd('/')

        return when {
            trimmed.startsWith("http://") || trimmed.startsWith("https://") -> trimmed
            else -> "https://$trimmed"
        }
    }

    private companion object {
        fun defaultDeviceName(): String =
            listOf(Build.MANUFACTURER, Build.MODEL)
                .filter { it.isNotBlank() }
                .joinToString(" ")
                .trim()
                .replaceFirstChar { it.uppercase() }
                .ifBlank { "Android" }
                .take(255)
    }
}
