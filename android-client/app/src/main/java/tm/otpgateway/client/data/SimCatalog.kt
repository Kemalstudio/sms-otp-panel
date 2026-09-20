package tm.otpgateway.client.data

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.telephony.SubscriptionManager
import android.util.Log
import androidx.core.content.ContextCompat

/**
 * The SIMs this handset can send from.
 *
 * A gateway phone often carries two SIMs — one for traffic, one that must stay
 * untouched — so the operator gets to pin the one that pays for the OTPs.
 * Reading the list needs READ_PHONE_STATE; without it the app simply falls back
 * to the system default SIM, which still sends.
 */
object SimCatalog {

    private const val TAG = "SimCatalog"

    data class SimOption(val subscriptionId: Int, val label: String)

    fun canRead(context: Context): Boolean =
        ContextCompat.checkSelfPermission(context, Manifest.permission.READ_PHONE_STATE) ==
            PackageManager.PERMISSION_GRANTED

    // canRead() is the permission check; lint cannot see through it.
    @SuppressLint("MissingPermission")
    fun available(context: Context): List<SimOption> {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP_MR1) return emptyList()
        if (!canRead(context)) return emptyList()

        val manager = context.getSystemService(SubscriptionManager::class.java) ?: return emptyList()

        return runCatching {
            manager.activeSubscriptionInfoList.orEmpty().map { info ->
                val carrier = info.carrierName?.toString().orEmpty().ifBlank { "SIM" }
                val slot = info.simSlotIndex + 1

                SimOption(
                    subscriptionId = info.subscriptionId,
                    label = "$carrier · слот $slot",
                )
            }
        }.onFailure { Log.w(TAG, "could not list the SIMs", it) }.getOrDefault(emptyList())
    }

    /** The label to show for what is currently selected. */
    fun labelFor(context: Context, subscriptionId: Int): String {
        if (subscriptionId == GatewaySettings.SIM_DEFAULT) return "SIM по умолчанию"

        return available(context).firstOrNull { it.subscriptionId == subscriptionId }?.label
            ?: "SIM #$subscriptionId (недоступна)"
    }
}
