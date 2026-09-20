<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWebhookRequest;
use App\Models\Project;
use App\Support\Webhooks\Webhooks;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class WebhookController extends Controller
{
    public function index(Project $project): View
    {
        return view('projects.webhooks.index', [
            'project' => $project,
            'deliveries' => $project->webhookDeliveries()->latest()->paginate(20),
            'events' => Webhooks::EVENTS,
            'signatureHeader' => WebhookSignature::HEADER,
            'toleranceSeconds' => WebhookSignature::TOLERANCE_SECONDS,
        ]);
    }

    /**
     * Секрет рождается вместе с первым адресом: вебхук без подписи бесполезен —
     * получатель не сможет отличить наш запрос от чужого.
     */
    public function update(UpdateWebhookRequest $request, Project $project): RedirectResponse
    {
        $url = $request->validated('webhook_url');

        $project->update(['webhook_url' => $url]);

        if ($url !== null && blank($project->webhook_secret)) {
            $project->rotateWebhookSecret();
        }

        return redirect()
            ->route('projects.webhooks.index', $project)
            ->with('status', $url === null
                ? 'Вебхуки выключены.'
                : 'Адрес сохранён. События пойдут на него сразу.');
    }

    public function rotate(Project $project): RedirectResponse
    {
        $project->rotateWebhookSecret();

        return redirect()
            ->route('projects.webhooks.index', $project)
            ->with('status', 'Секрет заменён — старая подпись перестала действовать. Обновите его у себя.');
    }

    /**
     * Тестовое событие: единственный способ проверить приёмник до того, как
     * через него пойдут настоящие коды.
     */
    public function test(Project $project): RedirectResponse
    {
        if (! $project->hasWebhook()) {
            return redirect()
                ->route('projects.webhooks.index', $project)
                ->with('status', 'Сначала укажите адрес.');
        }

        Webhooks::send($project, 'webhook.test', [
            'message' => 'Если вы это читаете, приёмник работает.',
            'project_id' => $project->id,
        ]);

        return redirect()
            ->route('projects.webhooks.index', $project)
            ->with('status', 'Тестовое событие поставлено в очередь.');
    }
}
