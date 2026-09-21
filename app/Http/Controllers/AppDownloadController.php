<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отдаёт APK оператору.
 *
 * Без этого установка выглядит так: найти файл в outputs сборки, перекинуть его
 * на телефон кабелем или мессенджером. С кнопкой в панели телефон просто
 * открывает её сам — и сразу оказывается там, где генерируется код привязки.
 */
class AppDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $path = self::path();

        abort_if($path === null, Response::HTTP_NOT_FOUND, 'Приложение ещё не собрано.');

        return response()->download($path, config('gateway.app.apk_name'), [
            // Без этого Android не предложит установку, а браузер попытается
            // открыть файл как неизвестный тип.
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    /** Полный путь к APK или null, если файла нет. */
    public static function path(): ?string
    {
        $configured = (string) config('gateway.app.apk_path');

        if ($configured === '') {
            return null;
        }

        $path = is_file($configured) ? $configured : base_path($configured);

        return is_file($path) ? $path : null;
    }

    /**
     * Данные для карточки в панели: есть ли файл, какого размера и когда собран.
     *
     * @return array{available: bool, size: string|null, built_at: \Illuminate\Support\Carbon|null}
     */
    public static function info(): array
    {
        $path = self::path();

        if ($path === null) {
            return ['available' => false, 'size' => null, 'built_at' => null];
        }

        $bytes = filesize($path);

        return [
            'available' => true,
            'size' => number_format($bytes / 1048576, 1, ',', ' ').' МБ',
            'built_at' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($path)),
        ];
    }
}
