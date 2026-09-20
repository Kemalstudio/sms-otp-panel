package tm.otpgateway.client.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
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
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import tm.otpgateway.client.data.SmsLogEntry

private enum class LogFilter(val label: String) {
    ALL("Все"),
    SENT("Отправлены"),
    FAILED("Ошибки"),
}

/**
 * The handset's own record of what it sent.
 *
 * The panel has the authoritative log; this one answers the question an
 * operator has while holding the phone, including for the sends the gateway
 * has not been told about yet.
 */
@Composable
fun LogScreen(
    entries: List<SmsLogEntry>,
    onClear: () -> Unit,
) {
    var filter by rememberSaveable { mutableStateOf(LogFilter.ALL) }
    var confirmClear by remember { mutableStateOf(false) }

    val visible = remember(entries, filter) {
        when (filter) {
            LogFilter.ALL -> entries
            LogFilter.SENT -> entries.filter { it.isSent }
            LogFilter.FAILED -> entries.filter { !it.isSent }
        }
    }

    Column(modifier = Modifier.fillMaxSize()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            LogFilter.entries.forEach { option ->
                FilterChip(
                    selected = filter == option,
                    onClick = { filter = option },
                    label = { Text(option.label) },
                    colors = FilterChipDefaults.filterChipColors(),
                )
            }
        }

        if (visible.isEmpty()) {
            EmptyLog(filter)
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = androidx.compose.foundation.layout.PaddingValues(
                    start = 16.dp,
                    end = 16.dp,
                    bottom = 24.dp,
                ),
                verticalArrangement = Arrangement.spacedBy(2.dp),
            ) {
                items(visible, key = { "${it.otpId}-${it.at}" }) { entry ->
                    LogRow(entry = entry, compact = false)
                    HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
                }

                item {
                    Spacer(Modifier.height(8.dp))

                    TextButton(
                        onClick = { confirmClear = true },
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text("Очистить журнал на телефоне")
                    }
                }
            }
        }
    }

    if (confirmClear) {
        AlertDialog(
            onDismissRequest = { confirmClear = false },
            title = { Text("Очистить журнал?") },
            text = {
                Text(
                    "Записи будут удалены только с телефона — в панели логи останутся. " +
                        "Недоставленные статусы после очистки больше не уйдут.",
                )
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        confirmClear = false
                        onClear()
                    },
                ) { Text("Очистить") }
            },
            dismissButton = {
                TextButton(onClick = { confirmClear = false }) { Text("Отмена") }
            },
        )
    }
}

@Composable
private fun EmptyLog(filter: LogFilter) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text(
            text = when (filter) {
                LogFilter.ALL -> "Журнал пуст"
                LogFilter.SENT -> "Успешных отправок пока нет"
                LogFilter.FAILED -> "Ошибок нет — так и должно быть"
            },
            style = MaterialTheme.typography.titleMedium,
        )
        Spacer(Modifier.height(6.dp))
        Text(
            text = "Здесь появляется каждая SMS, которую этот телефон отправил по команде шлюза.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

/** One send. Shared with the dashboard, where it is rendered a little tighter. */
@Composable
fun LogRow(entry: SmsLogEntry, compact: Boolean) {
    val palette = gatewayPalette

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = if (compact) 6.dp else 12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(2.dp),
        ) {
            Text(
                text = entry.phone,
                style = MaterialTheme.typography.bodyMedium,
                fontFamily = FontFamily.Monospace,
            )

            Text(
                text = buildString {
                    append(formatDateTime(entry.at))
                    if (entry.isTest) append(" · тест")
                    if (!entry.reported && !entry.isTest) append(" · статус не доставлен")
                    entry.reason?.let { append(" · $it") }
                },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        if (entry.isSent) {
            StatusPill(
                text = "отправлено",
                content = palette.onSuccessContainer,
                container = palette.successContainer,
            )
        } else {
            StatusPill(
                text = "ошибка",
                content = MaterialTheme.colorScheme.onErrorContainer,
                container = MaterialTheme.colorScheme.errorContainer,
            )
        }
    }
}
