package tm.otpgateway.client.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.text.KeyboardOptions
import tm.otpgateway.client.BuildConfig
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.GatewaySettings
import tm.otpgateway.client.data.SimCatalog
import tm.otpgateway.client.data.SmsStats

/**
 * Everything an operator changes once and then forgets: which SIM pays for the
 * traffic, whether the phone is allowed to shout when something breaks, and the
 * one destructive action — unpairing.
 */
@Composable
fun SettingsScreen(
    registration: DeviceStore.Registration,
    settings: GatewaySettings,
    stats: SmsStats,
    throughput: Int,
    sims: List<SimCatalog.SimOption>,
    canReadSims: Boolean,
    smsPermissionGranted: Boolean,
    notificationsGranted: Boolean,
    batteryUnrestricted: Boolean,
    testBusy: Boolean,
    onSelectSim: (Int) -> Unit,
    onAlertsChanged: (Boolean) -> Unit,
    onRequestSimPermission: () -> Unit,
    onRequestPermissions: () -> Unit,
    onRequestBattery: () -> Unit,
    onSendTest: (String) -> Unit,
    onRetryReports: () -> Unit,
    onUnpair: () -> Unit,
) {
    var confirmUnpair by remember { mutableStateOf(false) }
    var testPhone by rememberSaveable { mutableStateOf("+993") }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Spacer(Modifier.height(2.dp))

        SectionCard {
            SectionTitle("SIM для отправки", "С какой карты уходят коды клиентам")

            if (!canReadSims) {
                Text(
                    text = "Чтобы выбрать конкретную SIM, нужен доступ к состоянию телефона. " +
                        "Без него отправка идёт с карты по умолчанию.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                OutlinedButton(onClick = onRequestSimPermission) {
                    Text("Разрешить чтение SIM")
                }
            } else if (sims.isEmpty()) {
                Text(
                    text = "Активных SIM не найдено. Проверьте, вставлена ли карта.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            } else {
                SimChoice(
                    label = "По умолчанию (как в системе)",
                    selected = settings.subscriptionId == GatewaySettings.SIM_DEFAULT,
                    onSelect = { onSelectSim(GatewaySettings.SIM_DEFAULT) },
                )

                sims.forEach { sim ->
                    SimChoice(
                        label = sim.label,
                        selected = settings.subscriptionId == sim.subscriptionId,
                        onSelect = { onSelectSim(sim.subscriptionId) },
                    )
                }
            }
        }

        SectionCard {
            SectionTitle(
                "Тестовое SMS",
                "Уходит прямо с этого телефона, минуя шлюз — быстрый способ проверить SIM",
            )

            OutlinedTextField(
                value = testPhone,
                onValueChange = { testPhone = it.take(20) },
                label = { Text("Номер получателя") },
                singleLine = true,
                enabled = !testBusy,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                modifier = Modifier.fillMaxWidth(),
            )

            Button(
                onClick = { onSendTest(testPhone) },
                enabled = !testBusy && smsPermissionGranted,
                modifier = Modifier.align(Alignment.End),
            ) {
                if (testBusy) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(16.dp),
                        strokeWidth = 2.dp,
                    )
                    Spacer(Modifier.width(8.dp))
                }
                Text(if (testBusy) "Отправляю…" else "Отправить тест")
            }

            if (!smsPermissionGranted) {
                Text(
                    text = "Недоступно, пока не выдано разрешение на отправку SMS.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error,
                )
            }
        }

        SectionCard {
            SectionTitle("Уведомления")

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("Сообщать о сбоях", style = MaterialTheme.typography.bodyMedium)
                    Text(
                        text = "Потеря связи со шлюзом и неотправленные SMS",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }

                Switch(checked = settings.alertsEnabled, onCheckedChange = onAlertsChanged)
            }
        }

        SectionCard {
            SectionTitle("Готовность телефона", "Всё это молча ломает доставку")

            CheckRow("Отправка SMS", smsPermissionGranted, onRequestPermissions)
            CheckRow("Уведомления", notificationsGranted, onRequestPermissions)
            CheckRow("Без ограничений батареи", batteryUnrestricted, onRequestBattery)
            CheckRow("Firebase подключён", BuildConfig.HAS_FIREBASE_CONFIG, null)
        }

        SectionCard {
            SectionTitle("Синхронизация с панелью")

            InfoRow("Отправлено всего", stats.sentTotal.toString())
            InfoRow("Ошибок всего", stats.failedTotal.toString())
            InfoRow("Статусов в очереди", stats.pendingReports.toString())

            OutlinedButton(
                onClick = onRetryReports,
                modifier = Modifier.align(Alignment.End),
            ) {
                Text("Дослать статусы")
            }
        }

        SectionCard {
            SectionTitle("Устройство", "Имя, номер SIM и лимит скорости меняются в панели")

            InfoRow("Имя", registration.deviceName.ifBlank { "—" })
            InfoRow("Номер SIM", registration.phoneNumber ?: "не указан", mono = true)
            InfoRow(
                label = "Лимит скорости",
                value = if (throughput > 0) "$throughput SMS/мин" else "узнаётся при обмене",
            )
            InfoRow("ID в панели", registration.deviceId.toString(), mono = true)
            InfoRow("Сервер", registration.apiUrl, mono = true)
            InfoRow("Версия", "${BuildConfig.VERSION_NAME} (${BuildConfig.VERSION_CODE})")

            OutlinedButton(
                onClick = { confirmUnpair = true },
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Отвязать устройство", color = MaterialTheme.colorScheme.error)
            }
        }

        Spacer(Modifier.height(8.dp))
    }

    if (confirmUnpair) {
        AlertDialog(
            onDismissRequest = { confirmUnpair = false },
            title = { Text("Отвязать устройство?") },
            text = {
                Text(
                    "Токен будет удалён с телефона безвозвратно — вернуть его нельзя, " +
                        "понадобится новый код привязки из панели.",
                )
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        confirmUnpair = false
                        onUnpair()
                    },
                ) { Text("Отвязать") }
            },
            dismissButton = {
                TextButton(onClick = { confirmUnpair = false }) { Text("Отмена") }
            },
        )
    }
}

@Composable
private fun SimChoice(label: String, selected: Boolean, onSelect: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .selectable(selected = selected, onClick = onSelect)
            .padding(vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        RadioButton(selected = selected, onClick = onSelect)
        Text(label, style = MaterialTheme.typography.bodyMedium)
    }
}

@Composable
private fun CheckRow(label: String, ok: Boolean, onFix: (() -> Unit)?) {
    val palette = gatewayPalette

    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            modifier = Modifier.weight(1f),
        )

        if (ok) {
            StatusPill(
                text = "готово",
                content = palette.onSuccessContainer,
                container = palette.successContainer,
            )
        } else if (onFix != null) {
            TextButton(onClick = onFix) { Text("Исправить") }
        } else {
            StatusPill(
                text = "нет",
                content = MaterialTheme.colorScheme.onErrorContainer,
                container = MaterialTheme.colorScheme.errorContainer,
            )
        }
    }
}
