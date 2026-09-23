<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Models\PairingCode;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DeviceController extends Controller
{
    public function index(Project $project): View
    {
        // The newest code, used or expired ones included: the view has to be
        // able to say "code expired, generate a new one".
        $pairingCode = $project->pairingCodes()->latest('id')->first();

        return view('projects.devices.index', [
            'project' => $project,
            // Счётчики за сутки по каждому аппарату: умирающую SIM видно
            // раньше, чем сработает алёрт по проекту целиком.
            'devices' => $project->devices()
                ->withCount([
                    'otpLogs as sent_today_count' => fn ($query) => $query
                        ->whereDate('created_at', now()->toDateString()),
                    'otpLogs as failed_today_count' => fn ($query) => $query
                        ->whereDate('created_at', now()->toDateString())
                        ->where('status', 'failed'),
                ])
                ->latest()
                ->get(),
            'onlineThresholdMinutes' => Device::ONLINE_THRESHOLD_MINUTES,
            'throughputPerMinute' => $project->throughputPerMinute(),
            'maxThroughput' => Device::MAX_THROUGHPUT_PER_MINUTE,
            'apk' => AppDownloadController::info(),
            // QR со ссылкой на скачивание: оператор сидит за компьютером,
            // а ставить приложение нужно на телефон.
            'apkQr' => AppDownloadController::path() ? $this->qrFor(route('app.download')) : null,
            'pairingCode' => $pairingCode,
            'pairingQr' => $pairingCode?->isUsable() ? $this->qrSvg($pairingCode) : null,
        ]);
    }

    /**
     * Registers a device row by hand. Normally a phone creates its own row by
     * claiming a pairing code over /api/v1/devices/pair; this stays for
     * placeholders added before the handset is in reach.
     */
    public function store(StoreDeviceRequest $request, Project $project): RedirectResponse
    {
        $project->devices()->create([
            'name' => $request->validated('name'),
            'status' => 'inactive',
        ]);

        return redirect()
            ->route('projects.devices.index', $project)
            ->with('status', 'Устройство добавлено. Подключите телефон по коду привязки.');
    }

    /**
     * Номер SIM и пропускная способность — операторские настройки: телефон
     * свой номер прочитать не может, а сколько SMS в минуту он потянет,
     * зависит от аппарата, тарифа и оператора связи.
     */
    public function update(UpdateDeviceRequest $request, Project $project, Device $device): RedirectResponse
    {
        $device->update($request->validated());

        return redirect()
            ->route('projects.devices.index', $project)
            ->with('status', 'Устройство «'.$device->name.'» обновлено.');
    }

    /**
     * Убирает телефон из проекта.
     *
     * Логи остаются: внешний ключ обнуляется, а не каскадит — история отправок
     * нужна для разбора жалоб и сверки расходов даже после того, как аппарат
     * сдали или потеряли.
     *
     * Токен устройства умирает вместе со строкой, поэтому сам телефон начнёт
     * получать 401 на heartbeat. Это и есть отвязка со стороны панели.
     */
    public function destroy(Project $project, Device $device): RedirectResponse
    {
        $name = $device->name;
        $device->delete();

        return redirect()
            ->route('projects.devices.index', $project)
            ->with('status', 'Устройство «'.$name.'» удалено. Логи его отправок сохранены.');
    }

    /**
     * SVG so nothing depends on imagick/gd being present.
     */
    private function qrSvg(PairingCode $pairingCode): string
    {
        return $this->qrFor($pairingCode->qrPayload());
    }

    private function qrFor(string $payload, int $size = 200): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($payload);
    }
}
