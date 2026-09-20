<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_logs', function (Blueprint $table) {
            // Reversible encryption, used once to put the code into the SMS text
            // and nulled afterwards. Verification still goes through code_hash.
            $table->text('code_encrypted')->nullable()->after('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('otp_logs', function (Blueprint $table) {
            $table->dropColumn(['code_encrypted', 'attempts']);
        });
    }
};
