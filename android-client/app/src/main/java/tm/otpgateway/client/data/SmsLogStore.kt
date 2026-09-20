package tm.otpgateway.client.data

import android.content.Context
import android.util.Log
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.json.Json
import java.io.File
import java.util.concurrent.Executors

/**
 * Every SMS this handset was asked to send, kept on the phone.
 *
 * The panel has its own log, but an operator standing next to the device needs
 * to answer "did this thing actually send anything in the last hour" without a
 * laptop — and the unreported entries double as the retry queue for
 * `/devices/report-status`, so a status is never lost to a flaky connection.
 */
class SmsLogStore private constructor(private val file: File) {

    private val writer = Executors.newSingleThreadExecutor()
    private val lock = Any()

    private val _entries = MutableStateFlow(load())
    val entries: StateFlow<List<SmsLogEntry>> = _entries.asStateFlow()

    /** Newest first. A repeated OTP id replaces its earlier attempt. */
    fun record(entry: SmsLogEntry) {
        mutate { current ->
            val withoutEarlierAttempt = current.filterNot {
                !entry.isTest && it.otpId == entry.otpId
            }

            (listOf(entry) + withoutEarlierAttempt).take(LIMIT)
        }
    }

    fun markReported(otpId: Long) {
        mutate { current ->
            current.map { if (it.otpId == otpId) it.copy(reported = true) else it }
        }
    }

    /** Statuses the gateway has not acknowledged yet, oldest first. */
    fun pendingReports(): List<SmsLogEntry> =
        _entries.value.filter { !it.reported && it.otpId != 0L }.sortedBy { it.at }

    fun clear() {
        mutate { emptyList() }
    }

    fun stats(now: Long = System.currentTimeMillis()): SmsStats = statsOf(_entries.value, now)

    private fun mutate(transform: (List<SmsLogEntry>) -> List<SmsLogEntry>) {
        val next = synchronized(lock) {
            transform(_entries.value).also { _entries.value = it }
        }

        writer.execute {
            runCatching { file.writeText(JSON.encodeToString(SERIALIZER, next)) }
                .onFailure { Log.w(TAG, "could not persist the sms log", it) }
        }
    }

    private fun load(): List<SmsLogEntry> {
        if (!file.exists()) return emptyList()

        return runCatching { JSON.decodeFromString(SERIALIZER, file.readText()) }
            .onFailure { Log.w(TAG, "sms log is unreadable, starting a new one", it) }
            .getOrDefault(emptyList())
    }

    companion object {
        private const val TAG = "SmsLogStore"
        private const val FILE = "sms-log.json"

        /** Roughly a month of traffic for a single handset; older entries drop off. */
        private const val LIMIT = 500

        private val JSON = Json { ignoreUnknownKeys = true }
        private val SERIALIZER = ListSerializer(SmsLogEntry.serializer())

        @Volatile
        private var instance: SmsLogStore? = null

        fun get(context: Context): SmsLogStore =
            instance ?: synchronized(this) {
                instance ?: SmsLogStore(File(context.applicationContext.filesDir, FILE))
                    .also { instance = it }
            }

    }
}
