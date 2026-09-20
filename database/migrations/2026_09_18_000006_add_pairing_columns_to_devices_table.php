<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // sha256 of the device token handed out once at pairing time.
            $table->string('token_hash', 64)->nullable()->unique()->after('fcm_token');
            // When this device was last picked to deliver an OTP, for round-robin.
            $table->timestamp('last_dispatched_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['token_hash', 'last_dispatched_at']);
        });
    }
};
