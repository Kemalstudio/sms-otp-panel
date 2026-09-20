package tm.otpgateway.client.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import tm.otpgateway.client.BuildConfig

/**
 * First run: hand this handset to the gateway.
 *
 * Scanning is the happy path; the manual form exists because a phone without
 * Play services, or with a dead camera, still has to be able to join.
 */
@Composable
fun PairingScreen(
    state: GatewayViewModel.UiState,
    onDeviceNameChange: (String) -> Unit,
    onSimNumberChange: (String) -> Unit,
    onScan: () -> Unit,
    onManualPair: (String, String?) -> Unit,
    onDismissError: () -> Unit,
) {
    val snackbars = remember { SnackbarHostState() }
    val palette = gatewayPalette

    var serverUrl by rememberSaveable { mutableStateOf("") }
    var manualCode by rememberSaveable { mutableStateOf("") }
    var manualOpen by rememberSaveable { mutableStateOf(false) }

    LaunchedEffect(state.error) {
        state.error?.let {
            snackbars.showSnackbar(it)
            onDismissError()
        }
    }

    Scaffold(snackbarHost = { SnackbarHost(snackbars) }) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        Brush.linearGradient(listOf(palette.heroStart, palette.heroEnd)),
                    )
                    .padding(horizontal = 24.dp, vertical = 36.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                Text(
                    text = "SMS-шлюз",
                    style = MaterialTheme.typography.labelMedium,
                    color = palette.onHeroMuted,
                )
                Text(
                    text = "Подключите телефон к панели",
                    style = MaterialTheme.typography.headlineSmall,
                    color = palette.onHero,
                )
                Text(
                    text = "После привязки телефон будет получать команды от шлюза и " +
                        "отправлять одноразовые коды клиентам.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = palette.onHeroMuted,
                )
            }

            Column(
                modifier = Modifier.padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                if (!BuildConfig.HAS_FIREBASE_CONFIG) {
                    NoticeCard(
                        tone = NoticeTone.DANGER,
                        title = "Сборка без google-services.json",
                        body = "Push-команды приходить не будут, привязка заблокирована. " +
                            "Соберите приложение с файлом из вашего Firebase-проекта.",
                        action = "Понятно",
                        onAction = {},
                    )
                }

                SectionCard {
                    SectionTitle("Как подключить")

                    Step(1, "Откройте панель → проект → «Устройства»")
                    Step(2, "Нажмите «Подключить устройство» — появится QR-код")
                    Step(3, "Отсканируйте его здесь. Код живёт 5 минут и срабатывает один раз")
                }

                OutlinedTextField(
                    value = state.deviceName,
                    onValueChange = onDeviceNameChange,
                    label = { Text("Имя устройства") },
                    supportingText = { Text("Под этим именем телефон появится в панели") },
                    singleLine = true,
                    enabled = !state.busy,
                    modifier = Modifier.fillMaxWidth(),
                )

                OutlinedTextField(
                    value = state.simNumber,
                    onValueChange = onSimNumberChange,
                    label = { Text("Номер этой SIM") },
                    supportingText = {
                        Text("Его увидит получатель кода. Можно указать позже в панели")
                    },
                    singleLine = true,
                    enabled = !state.busy,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                    modifier = Modifier.fillMaxWidth(),
                )

                Button(
                    onClick = onScan,
                    enabled = !state.busy,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(54.dp),
                    colors = ButtonDefaults.buttonColors(),
                ) {
                    if (state.busy) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onPrimary,
                        )
                        Spacer(Modifier.width(10.dp))
                    }
                    Text(
                        text = if (state.busy) "Подключение…" else "Сканировать QR-код",
                        style = MaterialTheme.typography.labelLarge,
                    )
                }

                if (!manualOpen) {
                    OutlinedButton(
                        onClick = { manualOpen = true },
                        enabled = !state.busy,
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text("Ввести код вручную")
                    }
                } else {
                    SectionCard {
                        SectionTitle(
                            "Ввод вручную",
                            "Если камера или Play Services недоступны",
                        )

                        OutlinedTextField(
                            value = serverUrl,
                            onValueChange = { serverUrl = it },
                            label = { Text("Адрес сервера") },
                            placeholder = { Text("gateway.example.com") },
                            singleLine = true,
                            enabled = !state.busy,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                            modifier = Modifier.fillMaxWidth(),
                        )

                        OutlinedTextField(
                            value = manualCode,
                            onValueChange = { manualCode = it.take(6) },
                            label = { Text("Код привязки") },
                            placeholder = { Text("ABC123") },
                            singleLine = true,
                            enabled = !state.busy,
                            keyboardOptions = KeyboardOptions(
                                capitalization = KeyboardCapitalization.Characters,
                            ),
                            modifier = Modifier.fillMaxWidth(),
                        )

                        Button(
                            onClick = { onManualPair(manualCode, serverUrl) },
                            enabled = !state.busy &&
                                manualCode.length == 6 &&
                                serverUrl.isNotBlank(),
                            modifier = Modifier.align(Alignment.End),
                        ) {
                            Text("Подключить")
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))
            }
        }
    }
}

@Composable
private fun Step(number: Int, text: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(26.dp)
                .background(MaterialTheme.colorScheme.primaryContainer, CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                text = number.toString(),
                style = MaterialTheme.typography.labelMedium,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onPrimaryContainer,
            )
        }

        Text(
            text = text,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurface,
        )
    }
}
