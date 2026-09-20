<?php

namespace App\Support\Alerts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Дублирование алёртов в Telegram.
 *
 * Почту ночью никто не читает, а шлюз ломается ровно ночью. Канал
 * необязательный: не настроен — молча пропускается.
 */
class Telegram
{
    public static function configured(): bool
    {
        return filled(config('gateway.telegram.bot_token'))
            && filled(config('gateway.telegram.chat_id'));
    }

    public static function send(string $text): bool
    {
        if (! self::configured()) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->post('https://api.telegram.org/bot'.config('gateway.telegram.bot_token').'/sendMessage', [
                    'chat_id' => config('gateway.telegram.chat_id'),
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('telegram alert rejected', ['status' => $response->status()]);
        } catch (Throwable $e) {
            // Упавший канал уведомлений не должен ронять проверку здоровья:
            // иначе одна авария прячет все остальные.
            Log::warning('telegram alert failed', ['error' => $e->getMessage()]);
        }

        return false;
    }
}
