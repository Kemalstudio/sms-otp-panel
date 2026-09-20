<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();

            // Пусто у общесистемных аварий вроде вставшей очереди: у них нет
            // одного проекта-владельца.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type', 40);
            $table->string('message', 500);

            $table->enum('status', ['active', 'resolved'])->default('active');
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            /*
             * Одна активная авария каждого типа на проект. Без этого проверка
             * раз в минуту плодила бы по алёрту в минуту, и в шуме утонуло бы
             * то, ради чего всё затевалось.
             */
            $table->index(['project_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
