<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\OtpLog;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProjectController extends Controller
{
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Проект создан. Следующий шаг — подключить телефон.');
    }

    /**
     * The project hub: is this gateway able to deliver right now, what is still
     * missing, and the exact code to call it with.
     */
    public function show(Project $project): View
    {
        $devicesCount = $project->devices()->count();
        $onlineCount = $project->onlineDevicesCount();
        $activeKeysCount = $project->apiKeys()->active()->count();

        // One grouped query instead of five counts, since the dashboard shows
        // every status side by side.
        $today = $project->otpLogs()
            ->whereDate('created_at', now()->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sentToday = (int) $today->sum();
        $deliveredToday = (int) ($today['delivered'] ?? 0) + (int) ($today['sent'] ?? 0);
        $failedToday = (int) ($today['failed'] ?? 0) + (int) ($today['expired'] ?? 0);

        $latestKey = $project->apiKeys()->active()->latest()->first();

        /*
         * The checklist is the whole onboarding: four steps, each either done
         * or carrying the one action that finishes it.
         */
        $threshold = Device::ONLINE_THRESHOLD_MINUTES;

        $steps = [
            [
                'title' => 'Проект создан',
                'done' => true,
                'body' => 'Проект объединяет телефоны, ключи и логи.',
                'action' => null,
                'href' => null,
            ],
            [
                // Привязан — ещё не значит «работает»: отдельное состояние для
                // телефона, который перестал выходить на связь, иначе зелёная
                // галочка спорила бы с вердиктом наверху.
                'title' => 'Телефон подключён',
                'done' => $devicesCount > 0 && $onlineCount > 0,
                'stale' => $devicesCount > 0 && $onlineCount === 0,
                'body' => match (true) {
                    $devicesCount === 0 => 'Установите Android-приложение шлюза и отсканируйте QR-код привязки.',
                    $onlineCount === 0 => "Устройств в проекте: {$devicesCount}, но ни одно не выходило на связь больше {$threshold} мин.",
                    default => "Устройств в проекте: {$devicesCount}, сейчас на связи: {$onlineCount}.",
                },
                'action' => $devicesCount > 0 ? 'Все устройства' : 'Подключить телефон',
                'href' => route('projects.devices.index', $project),
            ],
            [
                'title' => 'API-ключ выпущен',
                'done' => $activeKeysCount > 0,
                'body' => $activeKeysCount > 0
                    ? "Активных ключей: {$activeKeysCount}. Полное значение показывается один раз при создании."
                    : 'Ключ нужен вашему бэкенду, чтобы запрашивать коды.',
                'action' => $activeKeysCount > 0 ? 'Управлять ключами' : 'Создать ключ',
                'href' => route('projects.api-keys.index', $project),
            ],
            [
                'title' => 'Первый код отправлен',
                'done' => $project->otpLogs()->exists(),
                'body' => $project->otpLogs()->exists()
                    ? 'Шлюз уже отправлял коды — смотрите логи.'
                    : 'Вызовите POST /api/v1/otp/send с вашим ключом — или проверьте прямо здесь, ниже.',
                'action' => 'Логи OTP',
                'href' => route('projects.logs.index', $project),
            ],
        ];

        return view('projects.overview', [
            'project' => $project,
            'devicesCount' => $devicesCount,
            'onlineCount' => $onlineCount,
            'activeKeysCount' => $activeKeysCount,
            'sentToday' => $sentToday,
            'deliveredToday' => $deliveredToday,
            'failedToday' => $failedToday,
            'pendingToday' => (int) ($today['pending'] ?? 0),
            'lastOtp' => $project->otpLogs()->latest()->first(),
            // Открытые аварии — то, что уже сломано, в отличие от чек-листа,
            // который говорит о том, что ещё не настроено.
            'alerts' => $project->alerts()->active()->latest('started_at')->get(),
            'recentDevices' => $project->devices()->latest('last_seen_at')->take(3)->get(),
            'steps' => $steps,
            'keyPrefix' => $latestKey?->key_prefix,
            'baseUrl' => rtrim(config('app.url'), '/'),
            'onlineThresholdMinutes' => Device::ONLINE_THRESHOLD_MINUTES,
            'throughputPerMinute' => $project->throughputPerMinute(),
            'otpLifetimeMinutes' => OtpLog::LIFETIME_MINUTES,
            'keyPlaceholder' => ApiKey::PREFIX.'...',
        ]);
    }
}
