package tm.otpgateway.client.ui

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * A live indicator, not a coloured dot: an operator has to be able to tell a
 * running gateway from a frozen screenshot of one from across the room.
 */
@Composable
fun PulseDot(color: Color, animated: Boolean, size: Int = 12) {
    val transition = rememberInfiniteTransition(label = "pulse")

    val scale by transition.animateFloat(
        initialValue = 1f,
        targetValue = if (animated) 2.4f else 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(1600, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Restart,
        ),
        label = "scale",
    )

    val fade by transition.animateFloat(
        initialValue = if (animated) 0.45f else 0f,
        targetValue = 0f,
        animationSpec = infiniteRepeatable(
            animation = tween(1600, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Restart,
        ),
        label = "fade",
    )

    Box(
        modifier = Modifier.size(size.dp * 2.6f),
        contentAlignment = Alignment.Center,
    ) {
        if (animated) {
            Box(
                modifier = Modifier
                    .size(size.dp)
                    .scale(scale)
                    .alpha(fade)
                    .background(color, CircleShape),
            )
        }

        Box(
            modifier = Modifier
                .size(size.dp)
                .background(color, CircleShape),
        )
    }
}

/** Card with a hairline border instead of a shadow — quieter in a long scroll. */
@Composable
fun SectionCard(
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = MaterialTheme.shapes.large,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(
            modifier = Modifier
                .border(
                    width = 1.dp,
                    color = MaterialTheme.colorScheme.outlineVariant,
                    shape = MaterialTheme.shapes.large,
                )
                .padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            content = content,
        )
    }
}

@Composable
fun SectionTitle(text: String, hint: String? = null) {
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(text, style = MaterialTheme.typography.titleMedium)

        if (hint != null) {
            Text(
                text = hint,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

/** One number the operator actually cares about, sized to be read at a glance. */
@Composable
fun RowScope.StatTile(
    label: String,
    value: String,
    tint: Color,
) {
    Card(
        modifier = Modifier.weight(1f),
        shape = MaterialTheme.shapes.medium,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(
            modifier = Modifier
                .border(
                    width = 1.dp,
                    color = MaterialTheme.colorScheme.outlineVariant,
                    shape = MaterialTheme.shapes.medium,
                )
                .padding(vertical = 16.dp, horizontal = 12.dp)
                .fillMaxWidth(),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            Text(text = value, style = StatNumberStyle, color = tint)
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
            )
        }
    }
}

@Composable
fun StatusPill(text: String, content: Color, container: Color) {
    Text(
        text = text,
        style = MaterialTheme.typography.labelMedium,
        color = content,
        modifier = Modifier
            .background(container, RoundedCornerShape(999.dp))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

@Composable
fun InfoRow(label: String, value: String, mono: Boolean = false) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(16.dp),
        verticalAlignment = Alignment.Top,
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            fontFamily = if (mono) FontFamily.Monospace else null,
            textAlign = TextAlign.End,
            modifier = Modifier.weight(1f),
        )
    }
}

enum class NoticeTone { DANGER, WARNING }

/**
 * Something that silently breaks delivery, phrased as what will happen and how
 * to stop it — never as a bare "permission missing".
 */
@Composable
fun NoticeCard(
    tone: NoticeTone,
    title: String,
    body: String,
    action: String,
    onAction: () -> Unit,
) {
    val palette = gatewayPalette

    val container = when (tone) {
        NoticeTone.DANGER -> MaterialTheme.colorScheme.errorContainer
        NoticeTone.WARNING -> palette.warningContainer
    }
    val onContainer = when (tone) {
        NoticeTone.DANGER -> MaterialTheme.colorScheme.onErrorContainer
        NoticeTone.WARNING -> palette.onWarningContainer
    }

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = MaterialTheme.shapes.large,
        colors = CardDefaults.cardColors(containerColor = container),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Text(title, style = MaterialTheme.typography.titleMedium, color = onContainer)
            Text(body, style = MaterialTheme.typography.bodySmall, color = onContainer)

            OutlinedButton(
                onClick = onAction,
                modifier = Modifier.align(Alignment.End),
            ) {
                Text(action, color = onContainer)
            }
        }
    }
}

private val CLOCK = SimpleDateFormat("HH:mm:ss", Locale.getDefault())
private val CLOCK_SHORT = SimpleDateFormat("HH:mm", Locale.getDefault())
private val DATE = SimpleDateFormat("d MMM, HH:mm", Locale("ru"))

fun formatClock(at: Long): String = CLOCK.format(Date(at))

fun formatShortClock(at: Long): String = CLOCK_SHORT.format(Date(at))

fun formatDateTime(at: Long): String = DATE.format(Date(at))

/** "12 сек назад" reads as alive; a timestamp alone does not. */
fun formatAgo(at: Long, now: Long = System.currentTimeMillis()): String {
    if (at <= 0L) return "ещё не было"

    val seconds = ((now - at) / 1000).coerceAtLeast(0)

    return when {
        seconds < 10 -> "только что"
        seconds < 60 -> "$seconds сек назад"
        seconds < 3600 -> "${seconds / 60} мин назад"
        seconds < 86_400 -> "${seconds / 3600} ч назад"
        else -> formatDateTime(at)
    }
}
