<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Повтор запроса после сетевого таймаута не должен стоить второй SMS.
         * Клиент присылает Idempotency-Key, мы запоминаем ответ и на повтор
         * отдаём тот же самый, ничего не выполняя заново.
         */
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            // Ключ уникален в пределах API-ключа, а не всей базы: два клиента
            // с одинаковым "order-42" не должны видеть ответы друг друга.
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('key');

            // sha256 от метода, пути и тела. Тот же ключ с другим телом — почти
            // всегда ошибка на стороне клиента, и отвечать «как в прошлый раз»
            // значило бы молча проглотить её.
            $table->char('fingerprint', 64);

            // Пусто, пока запрос в полёте: так видно параллельный дубль.
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();

            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['api_key_id', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
