<?php

use App\Models\Device;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Прежнее значение по умолчанию. */
    private const PREVIOUS_DEFAULT = 10;

    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            /*
             * 10 SMS в минуту — это оптимистично. Одна отправка занимает 2-5
             * секунд, а Android режет фоновую рассылку примерно на 30
             * сообщениях за полчаса, после чего показывает пользователю
             * системный запрос. Осторожный дефолт бережёт SIM от внимания
             * антиспама оператора; кому нужно больше — поднимает в панели.
             */
            $table->unsignedSmallInteger('throughput_per_minute')
                ->default(Device::DEFAULT_THROUGHPUT_PER_MINUTE)
                ->change();
        });

        // Телефоны, которым лимит не меняли руками, переводятся на новый
        // дефолт. Осознанно выставленные значения не трогаем.
        DB::table('devices')
            ->where('throughput_per_minute', self::PREVIOUS_DEFAULT)
            ->update(['throughput_per_minute' => Device::DEFAULT_THROUGHPUT_PER_MINUTE]);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedSmallInteger('throughput_per_minute')
                ->default(self::PREVIOUS_DEFAULT)
                ->change();
        });
    }
};
