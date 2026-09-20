package tm.otpgateway.client.data

import android.content.Context
import android.os.BatteryManager

/**
 * Заряд этого телефона.
 *
 * Шлюз — это стойка из аппаратов на зарядке, и разряжающийся отвалится из пула
 * через несколько часов. Панель должна узнать об этом заранее, поэтому уровень
 * едет вместе с heartbeat.
 */
object BatteryGauge {

    /** Null, если система не отдаёт уровень: отсутствие данных лучше нуля. */
    fun level(context: Context): Int? {
        val manager = context.getSystemService(BatteryManager::class.java) ?: return null

        return manager.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
            .takeIf { it in 0..100 }
    }
}
