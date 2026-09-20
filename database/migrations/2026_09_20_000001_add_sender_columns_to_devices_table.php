<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Номер SIM, вставленной в этот телефон: именно он увидит получатель.
            // Заполняет оператор при привязке — прочитать его у Android нельзя,
            // getLine1Number() на большинстве прошивок отдаёт пустую строку.
            $table->string('phone_number', 32)->nullable()->after('name');

            /*
             * Сколько SMS этот телефон отправляет в минуту. Ограничение не
             * наше, а физическое: одна отправка занимает секунды, а сам Android
             * без подтверждения пользователя не даёт фоновому приложению слать
             * больше ~30 сообщений за полчаса. Диспетчер обязан это уважать,
             * иначе очередь копится на телефоне, а коды протухают.
             */
            $table->unsignedSmallInteger('throughput_per_minute')->default(10)->after('status');

            // Приезжает в heartbeat: стойку из телефонов надо видеть целиком.
            $table->unsignedTinyInteger('battery_level')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'throughput_per_minute', 'battery_level']);
        });
    }
};
