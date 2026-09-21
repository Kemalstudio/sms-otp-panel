@php
    $base = rtrim(config('app.url'), '/').'/api/v1';

    $endpoints = [
        [
            'method' => 'POST',
            'path' => '/otp/send',
            'auth' => 'X-Api-Key',
            'summary' => 'Запросить код на номер',
            'body' => '{ "phone": "+99365123456", "from": "+99365000111" }',
            'note' => '`from` необязателен — фиксирует SIM, с которой уйдёт SMS.',
            'responses' => [
                ['202', 'Принято в очередь', '{ "otp_id": 42, "status": "pending", "from": "+993…", "expires_at": "…" }'],
                ['422', 'Неверный номер или неизвестный отправитель', '{ "message": "unknown sender number" }'],
                ['429', 'Лимит вызывающего или пропускная способность телефонов', '{ "message": "too many requests for this phone number", "retry_after": 47 }'],
                ['503', 'Нет ни одного телефона на связи', '{ "otp_id": 43, "status": "failed", "message": "no active device available" }'],
            ],
        ],
        [
            'method' => 'POST',
            'path' => '/otp/verify',
            'auth' => 'X-Api-Key',
            'summary' => 'Проверить введённый код',
            'body' => '{ "otp_id": 42, "code": "123456" }',
            'note' => 'Код живёт '.\App\Models\OtpLog::LIFETIME_MINUTES.' мин, попыток — '.\App\Models\OtpLog::MAX_VERIFY_ATTEMPTS.'.',
            'responses' => [
                ['200', 'Верный код', '{ "verified": true }'],
                ['200', 'Неверный код', '{ "verified": false, "attempts_left": 4 }'],
                ['410', 'Истёк или уже подтверждён', '{ "message": "expired" }'],
                ['429', 'Попытки исчерпаны', '{ "verified": false, "attempts_left": 0, "message": "too many attempts" }'],
            ],
        ],
        [
            'method' => 'GET',
            'path' => '/otp/{id}',
            'auth' => 'X-Api-Key',
            'summary' => 'Статус кода',
            'body' => null,
            'note' => 'Запасной путь к вебхукам, если приёмник лежал дольше, чем живут ретраи.',
            'responses' => [
                ['200', 'Текущее состояние', '{ "otp_id": 42, "status": "sent", "attempts_left": 5, "from": "+993…" }'],
                ['404', 'Кода нет у этого проекта', '{ "message": "otp not found" }'],
            ],
        ],
        [
            'method' => 'POST',
            'path' => '/devices/pair',
            'auth' => 'код привязки',
            'summary' => 'Привязать телефон',
            'body' => '{ "pairing_code": "ABC123", "device_name": "Samsung A54", "fcm_token": "…", "phone_number": "+993…" }',
            'note' => 'Единственный открытый эндпоинт: сам код — одноразовый и живёт 5 минут.',
            'responses' => [
                ['201', 'Телефон привязан', '{ "device_id": 7, "device_token": "<64 символа, один раз>" }'],
                ['404', 'Код истёк или уже использован', '{ "message": "invalid or expired code" }'],
            ],
        ],
        [
            'method' => 'POST',
            'path' => '/devices/heartbeat',
            'auth' => 'X-Device-Token',
            'summary' => 'Телефон сообщает, что жив',
            'body' => '{ "fcm_token": "…", "battery_level": 87 }',
            'note' => 'Реже раза в '.\App\Models\Device::ONLINE_THRESHOLD_MINUTES.' мин — и телефон выпадает из пула.',
            'responses' => [
                ['200', 'Принято', '{ "device_id": 7, "status": "active", "throughput_per_minute": 2 }'],
            ],
        ],
        [
            'method' => 'POST',
            'path' => '/devices/report-status',
            'auth' => 'X-Device-Token',
            'summary' => 'Телефон отчитывается об SMS',
            'body' => '{ "otp_id": 42, "status": "sent" }',
            'note' => 'Отчитаться можно только по коду, который был выдан этому телефону.',
            'responses' => [
                ['200', 'Записано', '{ "otp_id": 42, "status": "sent" }'],
                ['404', 'Чужой или несуществующий код', '{ "message": "otp not found" }'],
            ],
        ],
    ];

    $errors = [
        ['401', 'missing api key / invalid api key', 'Заголовок `X-Api-Key` не прислан или ключ отозван.'],
        ['422', 'unknown sender number', '`from` не принадлежит ни одному телефону проекта.'],
        ['429', 'too many requests for this phone number', 'Один код на номер в минуту. Смотрите `Retry-After`.'],
        ['429', 'too many requests for this api key', '20 запросов в минуту на ключ.'],
        ['429', 'all devices are at their throughput limit', 'Телефоны выбрали свою скорость. Ответ временный — повторите.'],
        ['503', 'no active device available', 'Ни один телефон не на связи. Это поломка шлюза, а не вызывающего.'],
        ['409', 'a request with this idempotency key is still in flight', 'Дубль пришёл, пока первый запрос ещё выполняется.'],
    ];
@endphp

<x-app-layout>
    <x-slot name="title">Документация API</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="label-caps">Для разработчика</p>
                <h1 class="text-2xl font-bold text-ink-900 dark:text-ink-50">Документация API</h1>
            </div>

            <x-copy-field :value="$base" label="Базовый URL" class="w-full sm:w-auto" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section class="card p-6">
            <h2 class="section-title">Как это работает</h2>
            <ol class="mt-4 space-y-3 text-sm text-ink-600 dark:text-ink-300">
                <li class="flex gap-3">
                    <span class="font-mono text-brand-600 dark:text-brand-400">1.</span>
                    Ваш бэкенд просит код: <code class="font-mono">POST /otp/send</code> с номером клиента.
                    В ответ — <code class="font-mono">otp_id</code>, его нужно сохранить.
                </li>
                <li class="flex gap-3">
                    <span class="font-mono text-brand-600 dark:text-brand-400">2.</span>
                    Шлюз выбирает телефон из пула и отправляет ему команду. SMS уходит с обычной SIM.
                </li>
                <li class="flex gap-3">
                    <span class="font-mono text-brand-600 dark:text-brand-400">3.</span>
                    Клиент вводит цифры — вы проверяете их через <code class="font-mono">POST /otp/verify</code>
                    с тем же <code class="font-mono">otp_id</code>.
                </li>
                <li class="flex gap-3">
                    <span class="font-mono text-brand-600 dark:text-brand-400">4.</span>
                    О судьбе кода узнаёте из вебхуков — или опросом <code class="font-mono">GET /otp/{id}</code>.
                </li>
            </ol>

            <div class="mt-5 rounded-xl bg-ink-50 p-4 text-sm text-ink-600 dark:bg-ink-950/50 dark:text-ink-300">
                <p class="font-medium text-ink-900 dark:text-ink-100">Ответ 202 — это не «SMS доставлена»</p>
                <p class="mt-1">
                    Он означает «принято в очередь». Реальный результат приходит вебхуком
                    (<code class="font-mono">otp.sent</code> или <code class="font-mono">otp.failed</code>)
                    через секунды. Не показывайте клиенту «код отправлен» раньше этого момента.
                </p>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="section-title">Аутентификация</h2>
            <div class="mt-4 space-y-4 text-sm">
                <div>
                    <p class="font-medium text-ink-900 dark:text-ink-100">
                        <code class="font-mono">X-Api-Key</code> — для вашего бэкенда
                    </p>
                    <p class="mt-1 text-ink-500 dark:text-ink-400">
                        Ключ привязан к проекту: чужие устройства и логи он не видит. В базе хранится
                        только sha256-хеш, полное значение показывается один раз при создании.
                    </p>
                </div>
                <div>
                    <p class="font-medium text-ink-900 dark:text-ink-100">
                        <code class="font-mono">X-Device-Token</code> — для телефона
                    </p>
                    <p class="mt-1 text-ink-500 dark:text-ink-400">
                        Выдаётся один раз при привязке. Ваш код им не пользуется.
                    </p>
                </div>
                <div>
                    <p class="font-medium text-ink-900 dark:text-ink-100">
                        <code class="font-mono">Idempotency-Key</code> — необязательный
                    </p>
                    <p class="mt-1 text-ink-500 dark:text-ink-400">
                        Повтор запроса с тем же ключом вернёт тот же ответ и не отправит вторую SMS.
                        Ставьте его на всех повторяемых вызовах: таймаут сети ничего не говорит
                        о том, выполнился запрос или нет.
                    </p>
                </div>
            </div>
        </section>

        @foreach ($endpoints as $endpoint)
            <section class="card-flush">
                <div class="card-header">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'rounded-lg px-2 py-0.5 font-mono text-xs font-semibold',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $endpoint['method'] === 'GET',
                                'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => $endpoint['method'] === 'POST',
                            ])>{{ $endpoint['method'] }}</span>

                            <code class="font-mono text-sm text-ink-900 dark:text-ink-100">{{ $endpoint['path'] }}</code>

                            <span class="rounded-lg bg-ink-100 px-2 py-0.5 font-mono text-xs text-ink-500 dark:bg-ink-800 dark:text-ink-400">
                                {{ $endpoint['auth'] }}
                            </span>
                        </div>

                        <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">{{ $endpoint['summary'] }}</p>
                        <p class="mt-1 text-xs text-ink-400 dark:text-ink-500">{!! $endpoint['note'] !!}</p>
                    </div>
                </div>

                <div class="space-y-4 p-6">
                    @if ($endpoint['body'])
                        <div>
                            <p class="label-caps mb-1.5">Тело запроса</p>
                            <pre class="code-block"><code>{{ $endpoint['body'] }}</code></pre>
                        </div>
                    @endif

                    <div>
                        <p class="label-caps mb-1.5">Ответы</p>
                        <div class="space-y-2">
                            @foreach ($endpoint['responses'] as [$code, $meaning, $sample])
                                <div class="rounded-xl border border-ink-200 p-3 dark:border-ink-800">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span @class([
                                            'rounded px-1.5 py-0.5 font-mono text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $code < 300,
                                            'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300' => $code >= 400 && $code < 500,
                                            'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-300' => $code >= 500,
                                        ])>{{ $code }}</span>
                                        <span class="text-sm text-ink-600 dark:text-ink-300">{{ $meaning }}</span>
                                    </div>
                                    <pre class="mt-2 overflow-x-auto font-mono text-xs text-ink-500 dark:text-ink-400"><code>{{ $sample }}</code></pre>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endforeach

        <section class="card-flush">
            <div class="card-header">
                <div>
                    <h3 class="section-title">Справочник ошибок</h3>
                    <p class="section-hint">Что означает каждый отказ и что с ним делать.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                    <thead class="table-head">
                        <tr>
                            <th class="px-6 py-3">Код</th>
                            <th class="px-6 py-3">message</th>
                            <th class="px-6 py-3">Что это значит</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($errors as [$code, $message, $meaning])
                            <tr class="table-row">
                                <td class="whitespace-nowrap px-6 py-3 font-mono">{{ $code }}</td>
                                <td class="px-6 py-3 font-mono text-xs">{{ $message }}</td>
                                <td class="px-6 py-3">{!! $meaning !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="section-title">Вебхуки</h2>
            <p class="section-hint">
                События <code class="font-mono">otp.sent</code>, <code class="font-mono">otp.failed</code>,
                <code class="font-mono">otp.verified</code>, <code class="font-mono">otp.expired</code>.
                Подпись — HMAC-SHA256 от <code class="font-mono">timestamp.тело</code> в заголовке
                <code class="font-mono">X-Gateway-Signature</code>.
            </p>
            <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">
                Адрес приёмника, секрет и готовый код проверки — на вкладке «Вебхуки» вашего проекта.
            </p>
        </section>
    </div>
</x-app-layout>
