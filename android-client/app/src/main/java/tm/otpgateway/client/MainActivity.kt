package tm.otpgateway.client

import android.Manifest
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.core.app.NotificationManagerCompat
import androidx.lifecycle.viewmodel.compose.viewModel
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.codescanner.GmsBarcodeScannerOptions
import com.google.mlkit.vision.codescanner.GmsBarcodeScanning
import tm.otpgateway.client.data.SimCatalog
import tm.otpgateway.client.sms.SmsSender
import tm.otpgateway.client.ui.GatewayApp
import tm.otpgateway.client.ui.GatewayTheme
import tm.otpgateway.client.ui.GatewayViewModel

/**
 * The platform half of the app: permissions, the battery dialog and Google's
 * QR scanner. Everything else lives in [GatewayApp].
 */
class MainActivity : ComponentActivity() {

    /** Bumped whenever a permission answer may have changed, to force a re-read. */
    private var permissionTick by mutableIntStateOf(0)

    private val requestPermissions = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions(),
    ) { permissionTick++ }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        setContent {
            GatewayTheme {
                val model: GatewayViewModel = viewModel()

                val tick = permissionTick
                val smsGranted = remember(tick) { SmsSender.hasPermission(this) }
                val notificationsGranted = remember(tick) {
                    NotificationManagerCompat.from(this).areNotificationsEnabled()
                }
                val batteryFree = remember(tick) { isIgnoringBatteryOptimizations() }
                val canReadSims = remember(tick) { SimCatalog.canRead(this) }
                val sims = remember(tick) { SimCatalog.available(this) }

                GatewayApp(
                    model = model,
                    sims = sims,
                    canReadSims = canReadSims,
                    smsPermissionGranted = smsGranted,
                    notificationsGranted = notificationsGranted,
                    batteryUnrestricted = batteryFree,
                    onScan = { startScan(model) },
                    onRequestPermissions = ::askForPermissions,
                    onRequestBattery = ::askToIgnoreBatteryOptimizations,
                    onRequestSimPermission = ::askForSimAccess,
                )
            }
        }

        askForPermissions()
    }

    override fun onResume() {
        super.onResume()
        permissionTick++
    }

    private fun askForPermissions() {
        val wanted = buildList {
            add(Manifest.permission.SEND_SMS)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                add(Manifest.permission.POST_NOTIFICATIONS)
            }
        }

        requestPermissions.launch(wanted.toTypedArray())
    }

    /**
     * Only needed to name the SIMs in settings — delivery works without it, so
     * it is asked for separately rather than at first launch.
     */
    private fun askForSimAccess() {
        requestPermissions.launch(arrayOf(Manifest.permission.READ_PHONE_STATE))
    }

    /**
     * Without this the OEM battery manager eventually freezes the heartbeat and
     * the gateway quietly drops this handset from its dispatch pool.
     */
    private fun askToIgnoreBatteryOptimizations() {
        if (isIgnoringBatteryOptimizations()) return

        runCatching {
            startActivity(
                Intent(
                    Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS,
                    Uri.parse("package:$packageName"),
                ),
            )
        }.onFailure {
            runCatching {
                startActivity(Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS))
            }
        }
    }

    private fun isIgnoringBatteryOptimizations(): Boolean {
        val power = getSystemService(PowerManager::class.java) ?: return true
        return power.isIgnoringBatteryOptimizations(packageName)
    }

    /**
     * Google's scanner UI, delivered through Play services — no CAMERA
     * permission and no scanner code of our own to keep working. On a device
     * without Play services the operator falls back to typing the code.
     */
    private fun startScan(model: GatewayViewModel) {
        val options = GmsBarcodeScannerOptions.Builder()
            .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
            .enableAutoZoom()
            .build()

        GmsBarcodeScanning.getClient(this, options)
            .startScan()
            .addOnSuccessListener { barcode ->
                val raw = barcode.rawValue

                if (raw.isNullOrBlank()) {
                    model.onScanFailed("QR-код пустой")
                } else {
                    model.pair(raw, null)
                }
            }
            .addOnCanceledListener {
                // Operator backed out; nothing to report.
            }
            .addOnFailureListener { e ->
                Log.w(TAG, "scan failed", e)
                model.onScanFailed("Сканер недоступен — введите код вручную")
            }
    }

    private companion object {
        const val TAG = "MainActivity"
    }
}
