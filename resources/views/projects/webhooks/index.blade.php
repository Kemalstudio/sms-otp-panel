@php
    $secret = $project->webhook_secret;

    $phpSnippet = <<<CODE
// Laravel: маршрут приёмника
Route::post('/hooks/otp', function (Request \$request) {
    \$payload = \$request->getContent();
    \$header = \$request->header('{$signatureHeader}', '');

    // t=<unix>,v1=<hex>
    parse_str(str_replace(',', '&', \$header), \$parts);
    \$timestamp = (int) (\$parts['t'] ?? 0);

    // Метка времени обязательна: без неё подписанный запрос можно записать
    // и воспроизвести через месяц.
    if (abs(time() - \$timestamp) > {$toleranceSeconds}) {
        abort(400, 'stale signature');
    }

    \$expected = hash_hmac('sha256', \$timestamp . '.' . \$payload, config('services.otp.webhook_secret'));

    if (! hash_equals(\$expected, \$parts['v1'] ?? '')) {
        abort(400, 'bad signature');
    }

    \$event = json_decode(\$payload, true);

    // event: otp.sent | otp.failed | otp.verified | otp.expired
    match (\$event['event']) {
        'otp.failed' => Order::markSmsFailed(\$event['data']['otp_id']),
        'otp.verified' => Order::markPhoneConfirmed(\$event['data']['otp_id']),
        default => null,
    };

    // Отвечайте 2xx быстро: всё тяжёлое — в очередь.
    return response()->noContent();
});
CODE;

    $nodeSnippet = <<<CODE
import crypto from 'node:crypto';

app.post('/hooks/otp', express.raw({ type: 'application/json' }), (req, res) => {
  const header = req.get('{$signatureHeader}') ?? '';
  const parts = Object.fromEntries(header.split(',').map((p) => p.split('=')));
  const timestamp = Number(parts.t ?? 0);

  if (Math.abs(Date.now() / 1000 - timestamp) > {$toleranceSeconds}) {
    return res.status(400).send('stale signature');
  }

  const expected = crypto
    .createHmac('sha256', process.env.OTP_WEBHOOK_SECRET)
    .update(`\${timestamp}.\${req.body}`)
    .digest('hex');

  if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(parts.v1 ?? ''))) {
    return res.status(400).send('bad signature');
  }

  const event = JSON.parse(req.body);
  console.log(event.event, event.data.otp_id, event.data.status);

  res.sendStatus(204);
});
CODE;

    $payloadSample = <<<CODE
{
  "event": "otp.sent",
  "occurred_at": "2026-09-20T12:00:03+05:00",
  "data": {
    "otp_id": 42,
    "phone": "+99361234567",
    "status": "sent",
    "attempts": 0,
    "device_id": 7,
    "from": "+99365000111",
    "created_at": "2026-09-20T12:00:00+05:00",
    "expires_at": "2026-09-20T12:05:00+05:00"
  }
}
CODE;
@endphp

<x-app-layout>
    <x-slot name="title">{{ $project->name }} — вебхуки</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="label-caps">Проект</p>
                <h1 class="truncate text-2xl font-bold text-ink-900 dark:text-ink-50">{{ $project->name }}</h1>
            </div>

            @if ($project->hasWebhook())
                <form method="POST" action="{{ route('projects.webhooks.test', $project) }}">
                    @csrf
                    <x-secondary-button type="submit">Отправить тестовое событие</x-secondary-button>
                </form>
            @endif
        </div>

        <div class="mt-5">
            <x-project-nav :project="$project" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-flash>{{ session('status') }}</x-flash>
        @endif

        @unless ($project->hasWebhook())
            <x-flash tone="warning">
                Вебхуки выключены. Пока так, ваш бэкенд не узнает, ушла ли SMS и ввёл ли
                клиент код — ответ <code class="font-mono">202</code> на
                <code class="font-mono">/otp/send</code> означает только «принято в очередь».
            </x-flash>
        @endunless

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="card-flush">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Адрес приёмника</h3>
                            <p class="section-hint">
                                Сюда уходит POST с JSON на каждое изменение статуса кода.
                                Пустое поле выключает вебхуки.
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('projects.webhooks.update', $project) }}" class="space-y-4 p-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="webhook_url" value="URL" />
                            <x-text-input id="webhook_url" name="webhook_url" type="url" class="mt-1.5 font-mono"
                                          placeholder="https://api.example.com/hooks/otp"
                                          :value="old('webhook_url', $project->webhook_url)" />
                            <x-input-error :messages="$errors->get('webhook_url')" class="mt-2" />

                            @if ($project->webhook_url && ! str_starts_with($project->webhook_url, 'https://'))
                                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                    Адрес без HTTPS: содержимое события — номер получателя и статус —
                                    пойдёт по сети открытым текстом.
                                </p>
                            @endif
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Сохранить</x-primary-button>
                        </div>
                    </form>
                </section>

                @if ($secret)
                    <section class="card p-6">
                        <h3 class="section-title">Секрет подписи</h3>
                        <p class="section-hint">
                            Им подписывается каждое событие. Положите его в переменные окружения
                            вашего приложения — по нему приёмник отличает наш запрос от чужого.
                        </p>

                        <div x-data="{ shown: false }" class="mt-4">
                            <div x-show="! shown">
                                <x-secondary-button type="button" x-on:click="shown = true">
                                    Показать секрет
                                </x-secondary-button>
                            </div>

                            <div x-show="shown" x-cloak>
                                <x-copy-field :value="$secret" />
                            </div>
                        </div>

                        <form method="POST" action="{{ route('projects.webhooks.rotate', $project) }}" class="mt-4"
                              onsubmit="return confirm('Заменить секрет? Старая подпись перестанет действовать немедленно, и события начнут отвергаться, пока вы не обновите секрет у себя.')">
                            @csrf
                            <button type="submit"
                                    class="text-sm font-semibold text-rose-600 transition hover:text-rose-500 dark:text-rose-400">
                                Заменить секрет
                            </button>
                        </form>
                    </section>
                @endif

                <section class="card-flush">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Проверка подписи</h3>
                            <p class="section-hint">
                                Подписывается строка <code class="font-mono">timestamp.тело</code>, алгоритм —
                                HMAC-SHA256. Запросы старше {{ $toleranceSeconds }} секунд отвергайте.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-6 p-6">
                        <x-code-block :snippets="[
                            ['label' => 'PHP / Laravel', 'code' => $phpSnippet],
                            ['label' => 'Node.js', 'code' => $nodeSnippet],
                        ]" />

                        <div>
                            <p class="mb-2 text-sm font-medium text-ink-900 dark:text-ink-100">Тело события</p>
                            <x-code-block :snippets="[['label' => 'JSON', 'code' => $payloadSample]]" />
                        </div>
                    </div>
                </section>

                <section class="card-flush">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Последние доставки</h3>
                            <p class="section-hint">
                                Ответ на «нам ничего не приходило»: здесь видно каждую попытку.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                            <thead class="table-head">
                                <tr>
                                    <th class="px-6 py-3">Событие</th>
                                    <th class="px-6 py-3">Статус</th>
                                    <th class="px-6 py-3">Попыток</th>
                                    <th class="px-6 py-3">Ответ</th>
                                    <th class="px-6 py-3">Когда</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                                @forelse ($deliveries as $delivery)
                                    <tr class="table-row">
                                        <td class="whitespace-nowrap px-6 py-4 font-mono text-xs">
                                            {{ $delivery->event }}
                                            @if ($delivery->otp_log_id)
                                                <span class="text-ink-400">#{{ $delivery->otp_log_id }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <x-status-badge :status="$delivery->status" subject="webhook" />
                                        </td>
                                        <td class="px-6 py-4 tabular-nums">{{ $delivery->attempts }}</td>
                                        <td class="px-6 py-4">
                                            @if ($delivery->response_status)
                                                <span class="font-mono text-xs">{{ $delivery->response_status }}</span>
                                            @endif
                                            @if ($delivery->error)
                                                <span class="block max-w-xs truncate text-xs text-rose-600 dark:text-rose-400"
                                                      title="{{ $delivery->error }}">{{ $delivery->error }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 tabular-nums text-ink-500 dark:text-ink-400">
                                            {{ $delivery->created_at->format('d.m.Y H:i:s') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <x-empty-state title="Доставок пока не было"
                                                           description="Событие появится здесь, как только у кода сменится статус — или отправьте тестовое."
                                                           icon="M12 8.25v3.75m0 3.75h.007v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($deliveries->hasPages())
                        <div class="border-t border-ink-100 p-4 dark:border-ink-800">
                            {{ $deliveries->links() }}
                        </div>
                    @endif
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card p-6">
                    <h3 class="section-title">События</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ([
                            'otp.sent' => 'Телефон отдал SMS оператору',
                            'otp.failed' => 'Отправить не удалось',
                            'otp.verified' => 'Клиент ввёл правильный код',
                            'otp.expired' => 'Код истёк, его никто не подтвердил',
                            'webhook.test' => 'Проверка приёмника из панели',
                        ] as $event => $description)
                            <li>
                                <code class="font-mono text-xs text-brand-600 dark:text-brand-400">{{ $event }}</code>
                                <p class="text-ink-500 dark:text-ink-400">{{ $description }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="card p-6">
                    <h3 class="section-title">Как мы доставляем</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Попыток</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">5</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Паузы</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">10с → 15м</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Таймаут</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">10 секунд</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Успех</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">любой 2xx</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs text-ink-400 dark:text-ink-500">
                        Отвечайте быстро и обрабатывайте событие в фоне: пока ваш обработчик думает,
                        наш воркер занят и следующие события ждут.
                    </p>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
