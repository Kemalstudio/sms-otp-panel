package tm.otpgateway.client.sms

import android.Manifest
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.os.Build
import android.telephony.SmsManager
import android.util.Log
import androidx.core.content.ContextCompat
import kotlinx.coroutines.suspendCancellableCoroutine
import tm.otpgateway.client.data.GatewaySettings
import tm.otpgateway.client.data.SendSmsCommand
import java.util.concurrent.atomic.AtomicBoolean
import java.util.concurrent.atomic.AtomicInteger
import kotlin.coroutines.resume

/**
 * Hands one OTP to the carrier and waits for the platform's verdict.
 *
 * The gateway treats `sent` as "the radio accepted it", which is the strongest
 * signal Android gives without requesting delivery reports from the network, so
 * the send result — not the mere absence of an exception — is what gets
 * reported back.
 */
object SmsSender {

    private const val TAG = "SmsSender"
    private const val ACTION_SENT = "tm.otpgateway.client.SMS_SENT"

    /** Keeps PendingIntent request codes distinct across concurrent sends. */
    private val requestCodes = AtomicInteger(1)

    sealed interface Result {
        data object Sent : Result
        data class Failed(val reason: String) : Result
    }

    fun hasPermission(context: Context): Boolean =
        ContextCompat.checkSelfPermission(context, Manifest.permission.SEND_SMS) ==
            PackageManager.PERMISSION_GRANTED

    suspend fun send(
        context: Context,
        command: SendSmsCommand,
        subscriptionId: Int = GatewaySettings.SIM_DEFAULT,
    ): Result = send(context, command.phone, command.message, subscriptionId)

    suspend fun send(
        context: Context,
        phone: String,
        message: String,
        subscriptionId: Int = GatewaySettings.SIM_DEFAULT,
    ): Result {
        if (!hasPermission(context)) {
            return Result.Failed("нет разрешения на отправку SMS")
        }

        val manager = smsManager(context, subscriptionId)
            ?: return Result.Failed("SmsManager недоступен на этом устройстве")

        return suspendCancellableCoroutine { continuation ->
            val requestCode = requestCodes.getAndIncrement()
            val action = "$ACTION_SENT.$requestCode"
            val settled = AtomicBoolean(false)

            // Long messages go out as several parts and each one reports back
            // separately: the first failure decides the outcome, otherwise the
            // send counts as successful only once every part is accepted.
            val parts = runCatching { manager.divideMessage(message) }.getOrNull()
                ?: arrayListOf(message)
            val remaining = AtomicInteger(parts.size)

            lateinit var receiver: BroadcastReceiver

            fun finish(result: Result) {
                if (!settled.compareAndSet(false, true)) return

                runCatching { context.unregisterReceiver(receiver) }
                if (continuation.isActive) continuation.resume(result)
            }

            receiver = object : BroadcastReceiver() {
                override fun onReceive(ctx: Context, intent: Intent) {
                    when (val code = resultCode) {
                        android.app.Activity.RESULT_OK ->
                            if (remaining.decrementAndGet() <= 0) finish(Result.Sent)

                        else -> finish(Result.Failed(describe(code)))
                    }
                }
            }

            ContextCompat.registerReceiver(
                context,
                receiver,
                IntentFilter(action),
                ContextCompat.RECEIVER_NOT_EXPORTED,
            )

            continuation.invokeOnCancellation {
                settled.set(true)
                runCatching { context.unregisterReceiver(receiver) }
            }

            val sentIntent = PendingIntent.getBroadcast(
                context,
                requestCode,
                Intent(action).setPackage(context.packageName),
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
            )

            try {
                if (parts.size > 1) {
                    val sentIntents = ArrayList<PendingIntent>(parts.size).apply {
                        repeat(parts.size) { add(sentIntent) }
                    }
                    manager.sendMultipartTextMessage(phone, null, parts, sentIntents, null)
                } else {
                    manager.sendTextMessage(phone, null, message, sentIntent, null)
                }
            } catch (e: Exception) {
                Log.e(TAG, "sending to $phone threw", e)
                finish(Result.Failed(e.message ?: "ошибка отправки: ${e::class.simpleName}"))
            }
        }
    }

    private fun describe(code: Int): String = when (code) {
        SmsManager.RESULT_ERROR_NO_SERVICE -> "нет сети оператора"
        SmsManager.RESULT_ERROR_RADIO_OFF -> "радиомодуль выключен"
        SmsManager.RESULT_ERROR_NULL_PDU -> "пустой PDU"
        SmsManager.RESULT_ERROR_GENERIC_FAILURE -> "общая ошибка оператора"
        SmsManager.RESULT_ERROR_LIMIT_EXCEEDED -> "превышен лимит отправки"
        else -> "ошибка отправки, код $code"
    }

    /**
     * [GatewaySettings.SIM_DEFAULT] leaves the choice to the system; anything
     * else pins the send to that SIM even if it is not the default one.
     */
    private fun smsManager(context: Context, subscriptionId: Int): SmsManager? {
        val base = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            context.getSystemService(SmsManager::class.java)
        } else {
            @Suppress("DEPRECATION")
            SmsManager.getDefault()
        } ?: return null

        if (subscriptionId == GatewaySettings.SIM_DEFAULT) return base

        return runCatching { base.createForSubscriptionId(subscriptionId) }
            .onFailure { Log.w(TAG, "SIM $subscriptionId is unavailable, using the default", it) }
            .getOrDefault(base)
    }
}
