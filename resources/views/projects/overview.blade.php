@php
    $ready = $onlineCount > 0 && $activeKeysCount > 0;
    $keyForSnippets = $keyPrefix ? $keyPrefix.'…' : $keyPlaceholder;

    $sendSnippets = [
        [
            'label' => 'cURL',
            'code' => <<<CODE
curl -X POST {$baseUrl}/api/v1/otp/send \\
  -H "X-Api-Key: {$keyForSnippets}" \\
  -H "Content-Type: application/json" \\
  -d '{"phone": "+99365123456"}'

# 202 Accepted
# {"otp_id": 42, "status": "pending", "expires_at": "2026-09-20T12:05:00+05:00"}
CODE,
        ],
        [
            'label' => 'PHP / Laravel',
            'code' => <<<CODE
use Illuminate\\Support\\Facades\\Http;

\$response = Http::withHeaders(['X-Api-Key' => config('services.otp.key')])
    ->acceptJson()
    ->post('{$baseUrl}/api/v1/otp/send', ['phone' => \$phone]);

// Сохраните otp_id — он понадобится, чтобы проверить введённый код.
\$otpId = \$response->json('otp_id');
CODE,
        ],
        [
            'label' => 'JavaScript',
            'code' => <<<CODE
const res = await fetch('{$baseUrl}/api/v1/otp/send', {
  method: 'POST',
  headers: {
    'X-Api-Key': process.env.OTP_API_KEY,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ phone: '+99365123456' }),
});

const { otp_id: otpId } = await res.json();
CODE,
        ],
        [
            'label' => 'Python',
            'code' => <<<CODE
import os, requests

r = requests.post(
    "{$baseUrl}/api/v1/otp/send",
    headers={"X-Api-Key": os.environ["OTP_API_KEY"]},
    json={"phone": "+99365123456"},
    timeout=10,
)

otp_id = r.json()["otp_id"]
CODE,
        ],
    ];

    $verifySnippets = [
        [
            'label' => 'cURL',
            'code' => <<<CODE
curl -X POST {$baseUrl}/api/v1/otp/verify \\
  -H "X-Api-Key: {$keyForSnippets}" \\
  -H "Content-Type: application/json" \\
  -d '{"otp_id": 42, "code": "123456"}'

# 200 OK  → {"verified": true}
# 200 OK  → {"verified": false, "attempts_left": 4}
# 410 Gone → {"message": "expired"}
CODE,
        ],
        [
            'label' => 'PHP / Laravel',
            'code' => <<<CODE
\$response = Http::withHeaders(['X-Api-Key' => config('services.otp.key')])
    ->acceptJson()
    ->post('{$baseUrl}/api/v1/otp/verify', [
        'otp_id' => \$otpId,
        'code' => \$request->input('code'),
    ]);

if (\$response->json('verified') === true) {
    // Номер подтверждён — пускаем пользователя.
}
CODE,
        ],
        [
            'label' => 'JavaScript',
            'code' => <<<CODE
const res = await fetch('{$baseUrl}/api/v1/otp/verify', {
  method: 'POST',
  headers: {
    'X-Api-Key': process.env.OTP_API_KEY,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ otp_id: otpId, code }),
});

const { verified, attempts_left: attemptsLeft } = await res.json();
CODE,
        ],
        [
            'label' => 'Python',
            'code' => <<<CODE
r = requests.post(
    "{$baseUrl}/api/v1/otp/verify",
    headers={"X-Api-Key": os.environ["OTP_API_KEY"]},
    json={"otp_id": otp_id, "code": code},
    timeout=10,
)

verified = r.json().get("verified", False)
CODE,
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="title">{{ $project->name }} — обзор</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="label-caps">Проект</p>
                <h1 class="truncate text-2xl font-bold text-ink-900 dark:text-ink-50">{{ $project->name }}</h1>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('projects.api-keys.index', $project) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-ink-300 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700 shadow-sm transition hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200 dark:hover:bg-ink-800">
                    API-ключи
                </a>
                <a href="{{ route('projects.devices.index', $project) }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500">
                    Подключить телефон
                </a>
            </div>
        </div>

        <div class="mt-5">
            <x-project-nav :project="$project" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-flash>{{ session('status') }}</x-flash>
        @endif

        {{-- Главный ответ страницы: может ли шлюз прямо сейчас доставить код. --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $ready ? 'from-brand-600 to-violet-700' : 'from-ink-700 to-ink-800' }} p-6 shadow-lift sm:p-8">
            <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-white/10 blur-2xl"></div>

            <div class="relative flex flex-wrap items-start justify-between gap-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-2.5">
                        <span class="relative flex h-2.5 w-2.5">
                            @if ($ready)
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-75"></span>
                            @endif
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $ready ? 'bg-emerald-300' : 'bg-rose-400' }}"></span>
                        </span>
                        <h2 class="text-xl font-bold text-white sm:text-2xl">
                            {{ $ready ? 'Шлюз готов отправлять коды' : 'Шлюз ещё не готов' }}
                        </h2>
                    </div>

                    <p class="mt-2 max-w-xl text-sm text-white/80">
                        @if ($ready)
                            Телефонов на связи: {{ $onlineCount }}, суммарно {{ $throughputPerMinute }} SMS в минуту.
                            Вызовите <code class="rounded bg-white/15 px-1.5 py-0.5 font-mono">POST /api/v1/otp/send</code> — код уйдёт по SMS.
                        @elseif ($onlineCount === 0 && $devicesCount > 0)
                            Ни один телефон не выходил на связь больше {{ $onlineThresholdMinutes }} мин.
                            Пока так, каждый запрос кода будет получать <code class="rounded bg-white/15 px-1.5 py-0.5 font-mono">503</code>.
                        @elseif ($devicesCount === 0)
                            Нет ни одного подключённого телефона — отправлять SMS физически некому.
                        @else
                            Нет активного API-ключа: вашему бэкенду нечем авторизоваться.
                        @endif
                    </p>
                </div>

                <dl class="grid shrink-0 grid-cols-3 gap-6 text-white">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-white/60">Онлайн</dt>
                        <dd class="mt-1 text-3xl font-bold tabular-nums">{{ $onlineCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-white/60">Коды сегодня</dt>
                        <dd class="mt-1 text-3xl font-bold tabular-nums">{{ $sentToday }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-white/60">Ошибки</dt>
                        <dd class="mt-1 text-3xl font-bold tabular-nums">{{ $failedToday }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if ($alerts->isNotEmpty())
            {{-- Аварии выше всего остального: это то, что сломано сейчас. --}}
            <section class="overflow-hidden rounded-2xl bg-rose-50 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:ring-rose-400/25">
                <div class="flex items-center gap-2 border-b border-rose-600/10 px-6 py-3 dark:border-rose-400/20">
                    <svg class="h-4 w-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <h2 class="text-sm font-semibold text-rose-800 dark:text-rose-200">
                        Открытые аварии: {{ $alerts->count() }}
                    </h2>
                </div>

                <ul class="divide-y divide-rose-600/10 dark:divide-rose-400/15">
                    @foreach ($alerts as $alert)
                        <li class="px-6 py-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="font-medium text-rose-900 dark:text-rose-100">{{ $alert->title }}</p>
                                <p class="text-xs text-rose-700/80 dark:text-rose-300/80">
                                    с {{ $alert->started_at->format('d.m.Y H:i') }}
                                    · {{ $alert->started_at->diffForHumans() }}
                                </p>
                            </div>
                            <p class="mt-1 text-sm text-rose-800 dark:text-rose-200">{{ $alert->message }}</p>
                        </li>
                    @endforeach
                </ul>

                <p class="border-t border-rose-600/10 px-6 py-3 text-xs text-rose-700/80 dark:border-rose-400/20 dark:text-rose-300/80">
                    Письмо об аварии ушло один раз — повторных не будет, пока она не закроется.
                    Проверка гоняется раз в минуту командой <code class="font-mono">gateway:check-health</code>.
                </p>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Чек-лист запуска: четыре шага от пустого проекта до первого кода. --}}
                <section class="card-flush">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Запуск за четыре шага</h3>
                            <p class="section-hint">Шаг подсвечен зелёным, когда он действительно выполнен.</p>
                        </div>
                    </div>

                    <ol class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($steps as $i => $step)
                            <li class="flex items-start gap-4 p-6">
                                @php($stale = $step['stale'] ?? false)

                                <span @class([
                                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $step['done'],
                                    'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300' => $stale,
                                    'bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400' => ! $step['done'] && ! $stale,
                                ])>
                                    @if ($step['done'])
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    @elseif ($stale)
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                        </svg>
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-ink-900 dark:text-ink-100">{{ $step['title'] }}</p>
                                    <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">{{ $step['body'] }}</p>
                                </div>

                                @if ($step['action'] && $step['href'])
                                    <a href="{{ $step['href'] }}"
                                       class="shrink-0 whitespace-nowrap text-sm font-semibold text-brand-600 transition hover:text-brand-500 dark:text-brand-400">
                                        {{ $step['action'] }} →
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>

                {{--
                    Живая проверка. Запрос уходит из браузера на тот же публичный API,
                    которым пользуется бэкенд клиента, — с настоящим ключом,
                    настоящими лимитами и настоящим телефоном на том конце.
                --}}
                <section class="card-flush"
                         x-data="{
                            key: '',
                            init() {
                                // Ключ мог приехать из модалки создания — забираем
                                // и сразу стираем, чтобы он не жил во вкладке дольше нужного.
                                const handed = sessionStorage.getItem('otp_test_key');
                                if (handed) {
                                    this.key = handed;
                                    sessionStorage.removeItem('otp_test_key');
                                }
                            },
                            phone: '+993',
                            otpId: '',
                            code: '',
                            sending: false,
                            verifying: false,
                            sendResult: null,
                            verifyResult: null,
                            async call(path, payload) {
                                const res = await fetch(path, {
                                    method: 'POST',
                                    headers: {
                                        'X-Api-Key': this.key.trim(),
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify(payload),
                                });

                                let body;
                                try { body = await res.json(); } catch (e) { body = { message: 'не JSON' }; }

                                return { status: res.status, ok: res.ok, body: JSON.stringify(body, null, 2) };
                            },
                            async send() {
                                if (! this.key.trim()) { this.sendResult = { status: 0, ok: false, body: 'Вставьте API-ключ' }; return; }
                                this.sending = true;
                                this.sendResult = null;
                                try {
                                    const result = await this.call('/api/v1/otp/send', { phone: this.phone.trim() });
                                    this.sendResult = result;
                                    const parsed = JSON.parse(result.body);
                                    if (parsed.otp_id) this.otpId = parsed.otp_id;
                                } catch (e) {
                                    this.sendResult = { status: 0, ok: false, body: String(e) };
                                } finally {
                                    this.sending = false;
                                }
                            },
                            async verify() {
                                this.verifying = true;
                                this.verifyResult = null;
                                try {
                                    this.verifyResult = await this.call('/api/v1/otp/verify', {
                                        otp_id: Number(this.otpId),
                                        code: this.code.trim(),
                                    });
                                } catch (e) {
                                    this.verifyResult = { status: 0, ok: false, body: String(e) };
                                } finally {
                                    this.verifying = false;
                                }
                            },
                         }">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Проверить прямо сейчас</h3>
                            <p class="section-hint">
                                Запрос уходит на тот же публичный API, что и из вашего кода: с ключом,
                                лимитами и живым телефоном на том конце.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-5 p-6">
                        <div>
                            <x-input-label for="test_key" value="API-ключ" />
                            <x-text-input id="test_key" type="text" x-model="key" class="mt-1.5 font-mono"
                                          placeholder="{{ $keyPlaceholder }}" autocomplete="off" />
                            <p class="mt-1.5 text-xs text-ink-400 dark:text-ink-500">
                                Ключ не сохраняется — он живёт только на этой странице.
                                Потеряли? <a href="{{ route('projects.api-keys.index', $project) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">выпустите новый</a>.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                            <div>
                                <x-input-label for="test_phone" value="Номер получателя" />
                                <x-text-input id="test_phone" type="tel" x-model="phone" class="mt-1.5 font-mono"
                                              placeholder="+99365123456" />
                            </div>

                            <x-primary-button type="button" x-on:click="send()" ::disabled="sending">
                                <svg x-show="sending" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                </svg>
                                <span x-text="sending ? 'Отправляю…' : 'Отправить код'"></span>
                            </x-primary-button>
                        </div>

                        <template x-if="sendResult">
                            <div>
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="label-caps">Ответ</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="sendResult.ok
                                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300'
                                              : 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-300'"
                                          x-text="'HTTP ' + sendResult.status"></span>
                                </div>
                                <pre class="code-block"><code x-text="sendResult.body"></code></pre>
                            </div>
                        </template>

                        <div class="border-t border-ink-100 pt-5 dark:border-ink-800">
                            <p class="text-sm font-medium text-ink-900 dark:text-ink-100">Шаг 2 — проверка кода</p>
                            <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                                Введите цифры из SMS. Код живёт {{ $otpLifetimeMinutes }} мин, попыток — 5.
                            </p>

                            <div class="mt-3 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                <div>
                                    <x-input-label for="test_otp_id" value="otp_id" />
                                    <x-text-input id="test_otp_id" type="text" x-model="otpId" class="mt-1.5 font-mono" placeholder="42" />
                                </div>
                                <div>
                                    <x-input-label for="test_code" value="Код из SMS" />
                                    <x-text-input id="test_code" type="text" x-model="code" class="mt-1.5 font-mono tracking-[0.3em]" placeholder="123456" maxlength="6" />
                                </div>

                                <x-secondary-button type="button" x-on:click="verify()" ::disabled="verifying">
                                    <span x-text="verifying ? 'Проверяю…' : 'Проверить код'"></span>
                                </x-secondary-button>
                            </div>

                            <template x-if="verifyResult">
                                <div class="mt-4">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="label-caps">Ответ</span>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                              :class="verifyResult.ok
                                                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300'
                                                  : 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-300'"
                                              x-text="'HTTP ' + verifyResult.status"></span>
                                    </div>
                                    <pre class="code-block"><code x-text="verifyResult.body"></code></pre>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <section class="card-flush">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Готовый код для вашего бэкенда</h3>
                            <p class="section-hint">
                                Подставлен адрес этого шлюза. Вместо
                                <code class="font-mono text-xs">{{ $keyForSnippets }}</code>
                                — ваш полный ключ, он показывается один раз при создании.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-6 p-6">
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink-900 dark:text-ink-100">1. Запросить код</p>
                            <x-code-block :snippets="$sendSnippets" />
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium text-ink-900 dark:text-ink-100">2. Проверить введённый код</p>
                            <x-code-block :snippets="$verifySnippets" />
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card p-6">
                    <h3 class="section-title">Адрес шлюза</h3>
                    <p class="section-hint">Базовый URL для всех вызовов API.</p>
                    <x-copy-field :value="$baseUrl.'/api/v1'" class="mt-4" />
                </section>

                <section class="card-flush">
                    <div class="border-b border-ink-100 p-6 dark:border-ink-800">
                        <h3 class="section-title">Телефоны</h3>
                        <p class="section-hint">
                            Онлайн — heartbeat не старше {{ $onlineThresholdMinutes }} мин.
                        </p>
                    </div>

                    @forelse ($recentDevices as $device)
                        <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-6 py-4 last:border-0 dark:border-ink-800">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink-900 dark:text-ink-100">{{ $device->name }}</p>
                                <p class="text-xs text-ink-400 dark:text-ink-500">
                                    {{ $device->last_seen_at?->diffForHumans() ?? 'ни разу не выходил на связь' }}
                                </p>
                            </div>
                            <x-status-badge :status="$device->effective_status" />
                        </div>
                    @empty
                        <x-empty-state title="Телефонов нет"
                                       description="Без подключённого телефона отправлять SMS некому."
                                       icon="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3">
                            <a href="{{ route('projects.devices.index', $project) }}"
                               class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500">
                                Подключить телефон
                            </a>
                        </x-empty-state>
                    @endforelse

                    @if ($recentDevices->isNotEmpty())
                        <a href="{{ route('projects.devices.index', $project) }}"
                           class="block border-t border-ink-100 px-6 py-3 text-sm font-medium text-brand-600 transition hover:bg-ink-50 dark:border-ink-800 dark:text-brand-400 dark:hover:bg-ink-800/50">
                            Все устройства →
                        </a>
                    @endif
                </section>

                <section class="card p-6">
                    <h3 class="section-title">Правила шлюза</h3>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Скорость проекта</dt>
                            <dd class="font-medium tabular-nums text-ink-900 dark:text-ink-100">
                                {{ $throughputPerMinute }} SMS/мин
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Жизнь кода</dt>
                            <dd class="font-medium tabular-nums text-ink-900 dark:text-ink-100">{{ $otpLifetimeMinutes }} мин</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Попыток ввода</dt>
                            <dd class="font-medium tabular-nums text-ink-900 dark:text-ink-100">5</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">На один номер</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">1 / мин</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">На один ключ</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">20 / мин</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Формат номера</dt>
                            <dd class="font-mono text-xs text-ink-900 dark:text-ink-100">+993 + 8 цифр</dd>
                        </div>
                    </dl>
                </section>

                @if ($lastOtp)
                    <section class="card p-6">
                        <h3 class="section-title">Последний код</h3>

                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-ink-500 dark:text-ink-400">Статус</span>
                                <x-status-badge :status="$lastOtp->status" subject="otp" />
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-ink-500 dark:text-ink-400">Когда</span>
                                <span class="text-ink-900 dark:text-ink-100">{{ $lastOtp->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        <a href="{{ route('projects.logs.index', $project) }}"
                           class="mt-4 inline-block text-sm font-medium text-brand-600 transition hover:text-brand-500 dark:text-brand-400">
                            Все логи →
                        </a>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
