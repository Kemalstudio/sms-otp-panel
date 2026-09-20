package tm.otpgateway.client.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

/*
 * The handset is a piece of infrastructure an operator glances at, often from
 * an arm's length away on a charging shelf. So: one saturated brand colour for
 * identity, three unmistakable status colours, generous type, and no dynamic
 * theming — "is it green" has to mean the same thing on every phone in the
 * rack, whatever wallpaper it happens to have.
 */

private val Brand = Color(0xFF4F46E5)
private val BrandBright = Color(0xFF818CF8)
private val Accent = Color(0xFF06B6D4)

private val LightColors = lightColorScheme(
    primary = Brand,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFE0E7FF),
    onPrimaryContainer = Color(0xFF1E1B4B),
    secondary = Color(0xFF0E7490),
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFCFFAFE),
    onSecondaryContainer = Color(0xFF083344),
    background = Color(0xFFF6F7FB),
    onBackground = Color(0xFF111827),
    surface = Color.White,
    onSurface = Color(0xFF111827),
    surfaceVariant = Color(0xFFEEF0F6),
    onSurfaceVariant = Color(0xFF5B6478),
    outline = Color(0xFFD5D9E4),
    outlineVariant = Color(0xFFE6E9F0),
    error = Color(0xFFDC2626),
    onError = Color.White,
    errorContainer = Color(0xFFFEE2E2),
    onErrorContainer = Color(0xFF7F1D1D),
)

private val DarkColors = darkColorScheme(
    primary = BrandBright,
    onPrimary = Color(0xFF1E1B4B),
    primaryContainer = Color(0xFF312E81),
    onPrimaryContainer = Color(0xFFE0E7FF),
    secondary = Color(0xFF67E8F9),
    onSecondary = Color(0xFF083344),
    secondaryContainer = Color(0xFF155E75),
    onSecondaryContainer = Color(0xFFCFFAFE),
    background = Color(0xFF0B0F1A),
    onBackground = Color(0xFFE8EAF2),
    surface = Color(0xFF141926),
    onSurface = Color(0xFFE8EAF2),
    surfaceVariant = Color(0xFF1C2230),
    onSurfaceVariant = Color(0xFF9AA3B8),
    outline = Color(0xFF2C3446),
    outlineVariant = Color(0xFF232A39),
    error = Color(0xFFF87171),
    onError = Color(0xFF450A0A),
    errorContainer = Color(0xFF4C1D1D),
    onErrorContainer = Color(0xFFFEE2E2),
)

/** Status colours Material does not carry, plus the hero gradient. */
@Immutable
data class GatewayPalette(
    val success: Color,
    val successContainer: Color,
    val onSuccessContainer: Color,
    val warning: Color,
    val warningContainer: Color,
    val onWarningContainer: Color,
    val heroStart: Color,
    val heroEnd: Color,
    val onHero: Color,
    val onHeroMuted: Color,
)

private val LightPalette = GatewayPalette(
    success = Color(0xFF059669),
    successContainer = Color(0xFFD1FAE5),
    onSuccessContainer = Color(0xFF064E3B),
    warning = Color(0xFFD97706),
    warningContainer = Color(0xFFFEF3C7),
    onWarningContainer = Color(0xFF78350F),
    heroStart = Brand,
    heroEnd = Color(0xFF7C3AED),
    onHero = Color.White,
    onHeroMuted = Color(0xCCFFFFFF),
)

private val DarkPalette = GatewayPalette(
    success = Color(0xFF34D399),
    successContainer = Color(0xFF064E3B),
    onSuccessContainer = Color(0xFFD1FAE5),
    warning = Color(0xFFFBBF24),
    warningContainer = Color(0xFF4A2F0A),
    onWarningContainer = Color(0xFFFEF3C7),
    heroStart = Color(0xFF312E81),
    heroEnd = Color(0xFF5B21B6),
    onHero = Color.White,
    onHeroMuted = Color(0xB3FFFFFF),
)

val LocalGatewayPalette = staticCompositionLocalOf { LightPalette }

/** Shorthand for the palette above, read the way `MaterialTheme.colorScheme` is. */
val gatewayPalette: GatewayPalette
    @Composable get() = LocalGatewayPalette.current

/** The one accent that is not status-driven, used for links and highlights. */
val AccentColor: Color get() = Accent

private val GatewayShapes = Shapes(
    extraSmall = RoundedCornerShape(8.dp),
    small = RoundedCornerShape(12.dp),
    medium = RoundedCornerShape(16.dp),
    large = RoundedCornerShape(22.dp),
    extraLarge = RoundedCornerShape(28.dp),
)

private val GatewayTypography = Typography().run {
    copy(
        headlineSmall = headlineSmall.copy(fontWeight = FontWeight.Bold, letterSpacing = (-0.5).sp),
        titleLarge = titleLarge.copy(fontWeight = FontWeight.Bold),
        titleMedium = titleMedium.copy(fontWeight = FontWeight.SemiBold),
        labelLarge = labelLarge.copy(fontWeight = FontWeight.SemiBold),
        labelMedium = labelMedium.copy(fontWeight = FontWeight.Medium, letterSpacing = 0.4.sp),
    )
}

/** Big, tabular-looking number for the stat tiles. */
val StatNumberStyle = TextStyle(
    fontSize = 28.sp,
    fontWeight = FontWeight.Bold,
    letterSpacing = (-1).sp,
)

@Composable
fun GatewayTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    CompositionLocalProvider(
        LocalGatewayPalette provides if (darkTheme) DarkPalette else LightPalette,
    ) {
        MaterialTheme(
            colorScheme = if (darkTheme) DarkColors else LightColors,
            shapes = GatewayShapes,
            typography = GatewayTypography,
            content = content,
        )
    }
}
