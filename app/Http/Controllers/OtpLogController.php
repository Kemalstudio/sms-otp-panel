<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexOtpLogRequest;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OtpLogController extends Controller
{
    public function index(IndexOtpLogRequest $request, Project $project): View
    {
        $logs = $project->otpLogs()
            ->with('device')
            ->status($request->status())
            ->matchingPhone($request->phone())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('projects.logs.index', [
            'project' => $project,
            'logs' => $logs,
            'statuses' => OtpLog::STATUSES,
            'activeStatus' => $request->status(),
            'activePhone' => $request->validated('phone'),
        ]);
    }

    /**
     * Всё, что известно про один код.
     *
     * Страница, которую открывают на вопрос «клиент говорит, код не пришёл»:
     * видно, какому телефону он ушёл, когда тот отчитался, сколько раз код
     * вводили и что ответил вебхук клиента.
     */
    public function show(Project $project, OtpLog $otpLog): View
    {
        return view('projects.logs.show', [
            'project' => $project,
            'log' => $otpLog->load(['device', 'webhookDeliveries']),
        ]);
    }

    /**
     * Выгрузка для сверки расходов и отчётности.
     *
     * Отдаётся потоком: за месяц строк набирается столько, что держать их в
     * памяти целиком незачем.
     */
    public function export(IndexOtpLogRequest $request, Project $project): StreamedResponse
    {
        $query = $project->otpLogs()
            ->with('device')
            ->status($request->status())
            ->matchingPhone($request->phone())
            ->latest();

        $filename = 'otp-'.$project->id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'wb');

            // BOM: без него Excel открывает UTF-8 как кракозябры.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'id', 'Номер', 'Статус', 'Устройство', 'Отправитель',
                'Попыток', 'Создан', 'Истекает',
            ], ';');

            $query->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->phone,
                        $log->status,
                        $log->device?->name ?? '',
                        $log->device?->phone_number ?? '',
                        $log->attempts,
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->expires_at?->format('Y-m-d H:i:s'),
                    ], ';');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
