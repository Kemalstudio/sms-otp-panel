package tm.otpgateway.client.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import tm.otpgateway.client.data.DeviceStore
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * The screen an operator glances at to answer one question: is this handset
 * still carrying traffic?
 */
@Composable
fun HomeScreen(
    registration: DeviceStore.Registration,
    online: Boolean,
    lastBeatAt: Long,
    smsPermissionGranted: Boolean,
    batteryUnrestricted: Boolean,
    onRequestPermissions: () -> Unit,
    onRequestBattery: () -> Unit,
    onUnpair: () -> Unit,
) {
    var confirmUnpair by remember { mutableStateOf(false) }

    Scaffold { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            StatusCard(online = online, lastBeatAt = lastBeatAt)

            Card(modifier = Modifier.fillMaxWidth()) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Text("Устройство", style = MaterialTheme.typography.titleMedium)
                    Field("Имя", registration.deviceName.ifBlank { "—" })
                    Field("ID в панели", registration.deviceId.toString())
                    Field("Сервер", registration.apiUrl)
                }
            }

            // Both of these silently break delivery, so they are surfaced as
            // actionable warnings rather than buried in settings.
            if (!smsPermissionGranted) {
                WarningCard(
                    title = "Нет разрешения на отправку SMS",
                    body = "Без него телефон принимает команды, но не может отправить код.",
                    action = "Выдать разрешение",
                    onAction = onRequestPermissions,
                )
            }

            if (!batteryUnrestricted) {
                WarningCard(
                    title = "Включена экономия батареи",
                    body = "Система может заморозить фоновую службу, и шлюз перестанет " +
                        "считать этот телефон живым.",
                    action = "Снять ограничение",
                    onAction = onRequestBattery,
                )
            }

            OutlinedButton(
                onClick = { confirmUnpair = true },
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text("Отвязать устройство")
            }
        }
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
                ) {
                    Text("Отвязать")
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmUnpair = false }) {
                    Text("Отмена")
                }
            },
        )
    }
}

@Composable
private fun StatusCard(online: Boolean, lastBeatAt: Long) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = if (online) {
                MaterialTheme.colorScheme.primaryContainer
            } else {
                MaterialTheme.colorScheme.errorContainer
            },
        ),
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                Box(
                    modifier = Modifier
                        .size(10.dp),
                ) {
                    Surface(
                        shape = CircleShape,
                        color = if (online) Color(0xFF10B981) else Color(0xFFEF4444),
                        modifier = Modifier.fillMaxSize(),
                    ) {}
                }

                Text(
                    text = if (online) "Шлюз на связи" else "Нет связи со шлюзом",
                    style = MaterialTheme.typography.titleLarge,
                )
            }

            Text(
                text = if (lastBeatAt > 0L) {
                    "Последний heartbeat: " + TIME.format(Date(lastBeatAt))
                } else {
                    "Heartbeat ещё не уходил"
                },
                style = MaterialTheme.typography.bodyMedium,
            )
        }
    }
}

@Composable
private fun WarningCard(
    title: String,
    body: String,
    action: String,
    onAction: () -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.errorContainer,
        ),
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            Text(body, style = MaterialTheme.typography.bodySmall)

            OutlinedButton(
                onClick = onAction,
                modifier = Modifier.align(Alignment.End),
            ) {
                Text(action)
            }
        }
    }
}

@Composable
private fun Field(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(label, style = MaterialTheme.typography.bodyMedium)
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            fontFamily = FontFamily.Monospace,
        )
    }
}

private val TIME = SimpleDateFormat("HH:mm:ss", Locale.getDefault())
