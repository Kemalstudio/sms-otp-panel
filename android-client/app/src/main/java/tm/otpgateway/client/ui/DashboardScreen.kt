package tm.otpgateway.client.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import tm.otpgateway.client.data.DeviceStore
import tm.otpgateway.client.data.SmsLogEntry
import tm.otpgateway.client.data.SmsStats

/**
 * The screen an operator glances at to answer one question: is this handset
 * still carrying traffic?
 */
@Composable
fun DashboardScreen(
    registration: DeviceStore.Registration,
    online: Boolean,
    beating: Boolean,
    lastBeatAt: Long,
    throughput: Int,
    stats: SmsStats,
    recent: List<SmsLogEntry>,
    smsPermissionGranted: Boolean,
    notificationsGranted: Boolean,
    batteryUnrestricted: Boolean,
    onRequestPermissions: () -> Unit,
    onRequestBattery: () -> Unit,
    onPing: () -> Unit,
    onRetryReports: () -> Unit,
    onOpenLog: () -> Unit,
) {
    // Re-renders "40 сек назад" without the heartbeat having to push anything.
    var now by remember { mutableLongStateOf(System.currentTimeMillis()) }
    LaunchedEffect(Unit) {
        while (true) {
            delay(1_000)
            now = System.currentTimeMillis()
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Spacer(Modifier.height(2.dp))

        HeroStatus(
            online = online,
            beating = beating,
            lastBeatAt = lastBeatAt,
            now = now,
            deviceName = registration.deviceName.ifBlank { "Это устройство" },
            onPing = onPing,
        )

        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            StatTile(
                label = "Отправлено\nсегодня",
                value = stats.sentToday.toString(),
                tint = gatewayPalette.success,
            )
            StatTile(
                label = "Ошибок\nсегодня",
                value = stats.failedToday.toString(),
                tint = if (stats.failedToday > 0) {
                    MaterialTheme.colorScheme.error
                } else {
                    MaterialTheme.colorScheme.onSurfaceVariant
                },
            )
            StatTile(
                label = "Успешность\nза сутки",
                value = "${stats.successRate}%",
                tint = MaterialTheme.colorScheme.primary,
            )
        }

        if (!smsPermissionGranted) {
            NoticeCard(
                tone = NoticeTone.DANGER,
                title = "Нет разрешения на отправку SMS",
                body = "Телефон принимает команды от шлюза, но физически не может отправить код. " +
                    "Каждый заказ будет падать в ошибку.",
                action = "Выдать разрешение",
                onAction = onRequestPermissions,
            )
        }

        if (!batteryUnrestricted) {
            NoticeCard(
                tone = NoticeTone.WARNING,
                title = "Включена экономия батареи",
                body = "Система может заморозить фоновую службу — через 5 минут панель " +
                    "перестанет считать этот телефон живым и уведёт заказы на другое устройство.",
                action = "Снять ограничение",
                onAction = onRequestBattery,
            )
        }

        if (!notificationsGranted) {
            NoticeCard(
                tone = NoticeTone.WARNING,
                title = "Уведомления отключены",
                body = "Без них вы не увидите, что шлюз потерял сервер или что SMS не ушла.",
                action = "Включить",
                onAction = onRequestPermissions,
            )
        }

        if (stats.pendingReports > 0) {
            NoticeCard(
                tone = NoticeTone.WARNING,
                title = "Не доставлено статусов: ${stats.pendingReports}",
                body = "SMS ушли, но панель об этом ещё не знает — обычно из-за пропавшего " +
                    "интернета. Телефон досылает статусы сам, можно и вручную.",
                action = "Дослать сейчас",
                onAction = onRetryReports,
            )
        }

        SectionCard {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                SectionTitle("Последние отправки")

                TextButton(onClick = onOpenLog) { Text("Весь журнал") }
            }

            if (recent.isEmpty()) {
                Text(
                    text = "Пока ничего не отправлялось. Как только панель выдаст первый код, " +
                        "он появится здесь.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            } else {
                recent.forEach { entry -> LogRow(entry = entry, compact = true) }
            }
        }

        SectionCard {
            SectionTitle("Устройство")
            InfoRow("Имя", registration.deviceName.ifBlank { "—" })
            InfoRow("Номер SIM", registration.phoneNumber ?: "не указан", mono = true)
            InfoRow(
                label = "Лимит скорости",
                value = if (throughput > 0) "$throughput SMS/мин" else "узнаётся при обмене",
            )
            InfoRow("ID в панели", registration.deviceId.toString(), mono = true)
            InfoRow("Сервер", registration.apiUrl, mono = true)
        }

        Spacer(Modifier.height(8.dp))
    }
}

@Composable
private fun HeroStatus(
    online: Boolean,
    beating: Boolean,
    lastBeatAt: Long,
    now: Long,
    deviceName: String,
    onPing: () -> Unit,
) {
    val palette = gatewayPalette

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = MaterialTheme.shapes.extraLarge,
        colors = CardDefaults.cardColors(containerColor = Color.Transparent),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(
            modifier = Modifier
                .background(
                    Brush.linearGradient(
                        colors = if (online) {
                            listOf(palette.heroStart, palette.heroEnd)
                        } else {
                            listOf(Color(0xFF334155), Color(0xFF1E293B))
                        },
                    ),
                )
                .padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                PulseDot(
                    color = if (online) Color(0xFF34D399) else Color(0xFFF87171),
                    animated = online,
                )

                Text(
                    text = if (online) "Шлюз на связи" else "Нет связи со шлюзом",
                    style = MaterialTheme.typography.titleLarge,
                    color = palette.onHero,
                )
            }

            Text(
                text = when {
                    beating -> "Обмен с сервером…"
                    lastBeatAt > 0L ->
                        "Последний обмен: ${formatClock(lastBeatAt)} · ${formatAgo(lastBeatAt, now)}"

                    else -> "Обмена с сервером ещё не было"
                },
                style = MaterialTheme.typography.bodyMedium,
                color = palette.onHeroMuted,
            )

            Text(
                text = deviceName,
                style = MaterialTheme.typography.bodySmall,
                color = palette.onHeroMuted,
                fontFamily = FontFamily.Monospace,
            )

            Spacer(Modifier.height(6.dp))

            OutlinedButton(onClick = onPing) {
                Text("Проверить связь", color = palette.onHero)
            }
        }
    }
}
