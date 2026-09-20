<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Куда слать события о судьбе кода. Пусто — вебхуки выключены.
            $table->string('webhook_url')->nullable()->after('name');

            // Общий секрет для подписи. Живёт открытым текстом намеренно:
            // интегратору он нужен столько раз, сколько он перечитает свою
            // реализацию проверки, а прав у него не больше, чем «подтвердить,
            // что запрос пришёл от нас».
            $table->string('webhook_secret', 64)->nullable()->after('webhook_url');
        });

        /*
         * Журнал доставок. Без него вебхуки — это «мы отправили и надеемся»:
         * интегратор говорит «нам ничего не приходило», и проверить нечем.
         */
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('otp_log_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event', 40);
            $table->string('url');
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error', 500)->nullable();

            // Ровно то тело, которое ушло: по нему считается подпись, и по нему
            // же потом разбирают «а что вы прислали».
            $table->text('payload');

            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret']);
        });
    }
};
