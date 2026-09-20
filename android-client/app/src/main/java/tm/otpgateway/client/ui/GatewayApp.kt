package tm.otpgateway.client.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import tm.otpgateway.client.data.SimCatalog
import tm.otpgateway.client.data.statsOf
import tm.otpgateway.client.service.HeartbeatService

private enum class Tab(val title: String, val label: String, val icon: ImageVector) {
    DASHBOARD("SMS-шлюз", "Обзор", Icons.Filled.Home),
    LOG("Журнал отправок", "Журнал", Icons.AutoMirrored.Filled.List),
    SETTINGS("Настройки", "Настройки", Icons.Filled.Settings),
}

/**
 * The whole app below the platform layer: pairing when there is no device
 * token, and the three-tab console once there is.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GatewayApp(
    model: GatewayViewModel,
    sims: List<SimCatalog.SimOption>,
    canReadSims: Boolean,
    smsPermissionGranted: Boolean,
    notificationsGranted: Boolean,
    batteryUnrestricted: Boolean,
    onScan: () -> Unit,
    onRequestPermissions: () -> Unit,
    onRequestBattery: () -> Unit,
    onRequestSimPermission: () -> Unit,
) {
    val registration by model.registration.collectAsStateWithLifecycle()
    val ui by model.ui.collectAsStateWithLifecycle()

    val current = registration

    if (current == null) {
        PairingScreen(
            state = ui,
            onDeviceNameChange = model::onDeviceNameChanged,
            onSimNumberChange = model::onSimNumberChanged,
            onScan = onScan,
            onManualPair = model::pair,
            onDismissError = model::dismissError,
        )
        return
    }

    val entries by model.entries.collectAsStateWithLifecycle()
    val settings by model.settings.collectAsStateWithLifecycle()
    val online by HeartbeatService.online.collectAsStateWithLifecycle()
    val beating by HeartbeatService.beating.collectAsStateWithLifecycle()
    val lastBeatAt by HeartbeatService.lastBeatAt.collectAsStateWithLifecycle()
    val throughput by HeartbeatService.throughput.collectAsStateWithLifecycle()

    // Recomputed whenever the log changes, which is the only thing that moves
    // the counters.
    val stats = remember(entries) { statsOf(entries) }

    var tab by rememberSaveable { mutableStateOf(Tab.DASHBOARD) }
    val snackbars = remember { SnackbarHostState() }

    LaunchedEffect(ui.error) {
        ui.error?.let {
            snackbars.showSnackbar(it)
            model.dismissError()
        }
    }

    LaunchedEffect(ui.notice) {
        ui.notice?.let {
            snackbars.showSnackbar(it)
            model.dismissNotice()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(tab.title) },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background,
                ),
            )
        },
        bottomBar = {
            NavigationBar(containerColor = MaterialTheme.colorScheme.surface) {
                Tab.entries.forEach { option ->
                    NavigationBarItem(
                        selected = tab == option,
                        onClick = { tab = option },
                        icon = { Icon(option.icon, contentDescription = option.label) },
                        label = { Text(option.label) },
                    )
                }
            }
        },
        snackbarHost = { SnackbarHost(snackbars) },
        containerColor = MaterialTheme.colorScheme.background,
    ) { padding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
        ) {
            when (tab) {
                Tab.DASHBOARD -> DashboardScreen(
                    registration = current,
                    online = online,
                    beating = beating,
                    lastBeatAt = lastBeatAt,
                    throughput = throughput,
                    stats = stats,
                    recent = entries.take(5),
                    smsPermissionGranted = smsPermissionGranted,
                    notificationsGranted = notificationsGranted,
                    batteryUnrestricted = batteryUnrestricted,
                    onRequestPermissions = onRequestPermissions,
                    onRequestBattery = onRequestBattery,
                    onPing = model::ping,
                    onRetryReports = model::retryPendingReports,
                    onOpenLog = { tab = Tab.LOG },
                )

                Tab.LOG -> LogScreen(
                    entries = entries,
                    onClear = model::clearLog,
                )

                Tab.SETTINGS -> SettingsScreen(
                    registration = current,
                    settings = settings,
                    stats = stats,
                    throughput = throughput,
                    sims = sims,
                    canReadSims = canReadSims,
                    smsPermissionGranted = smsPermissionGranted,
                    notificationsGranted = notificationsGranted,
                    batteryUnrestricted = batteryUnrestricted,
                    testBusy = ui.testBusy,
                    onSelectSim = model::setSubscriptionId,
                    onAlertsChanged = model::setAlertsEnabled,
                    onRequestSimPermission = onRequestSimPermission,
                    onRequestPermissions = onRequestPermissions,
                    onRequestBattery = onRequestBattery,
                    onSendTest = model::sendTestSms,
                    onRetryReports = model::retryPendingReports,
                    onUnpair = model::unpair,
                )
            }
        }
    }
}
