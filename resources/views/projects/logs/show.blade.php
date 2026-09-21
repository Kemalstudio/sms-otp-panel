@php
    $timeline = collect([
        ['at' => $log->created_at, 'title' => 'Код создан', 'body' => 'Запрос принят, поставлен в очередь на отправку.'],
    ]);

    if ($log->device) {
        $timeline->push([
            'at' => $log->created_at,
            'title' => 'Назначен телефон',
            'body' => $log->device->name.($log->device->phone_number ? ' · отправитель '.$log->device->phone_number : ''),
        ]);
    }

    if (in_array($log->status, ['sent', 'delivered'], true)) {
        $timeline->push([
            'at' => $log->updated_at,
            'title' => 'Телефон отчитался: отправлено',
            'body' => 'Оператор принял сообщение. Это не то же самое, что «абонент получил».',
        ]);
    }

    if ($log->status === 'delivered') {
        $timeline->push([
            'at' => $log->updated_at,
            'title' => 'Код подтверждён',
            'body' => 'Клиент ввёл правильные цифры — номер подтверждён.',
        ]);
    }

    if ($log->status === 'failed') {
        $timeline->push([
            'at' => $log->updated_at,
            'title' => 'Отправить не удалось',
            'body' => 'Смотрите доставки вебхука ниже и журнал приложения на телефоне.',
        ]);
    }

    if ($log->status === 'expired') {
        $timeline->push([
            'at' => $log->expires_at,
            'title' => 'Код истёк',
            'body' => 'Никто не ввёл его за отведённое время. Это решение клиента, а не сбой шлюза.',
        ]);
    }
@endphp

<x-app-layout>
    <x-slot name="title">Код #{{ $log->id }} — {{ $project->name }}</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="label-caps">
                    <a href="{{ route('projects.logs.index', $project) }}"
                       class="transition hover:text-brand-600 dark:hover:text-brand-400">Логи OTP</a>
                    · {{ $project->name }}
                </p>
                <h1 class="truncate text-2xl font-bold text-ink-900 dark:text-ink-50">Код #{{ $log->id }}</h1>
            </div>

            <div class="flex items-center gap-3">
                <x-status-badge :status="$log->status" subject="otp" />
                <a href="{{ route('projects.logs.index', $project) }}"
                   class="text-sm font-medium text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-ink-100">
                    ← К журналу
                </a>
            </div>
        </div>

        <div class="mt-5">
            <x-project-nav :project="$project" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="card-flush">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Что происходило</h3>
                            <p class="section-hint">
                                Сам код нигде не хранится в открытом виде — ни здесь, ни в базе.
                            </p>
                        </div>
                    </div>

                    <ol class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($timeline as $event)
                            <li class="flex gap-4 p-6">
                                <span class="mt-1 flex h-2.5 w-2.5 shrink-0 rounded-full bg-brand-500"></span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <p class="font-medium text-ink-900 dark:text-ink-100">{{ $event['title'] }}</p>
                                        <p class="text-xs tabular-nums text-ink-400 dark:text-ink-500">
                                            {{ $event['at']?->format('d.m.Y H:i:s') }}
                                        </p>
                                    </div>
                                    <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">{{ $event['body'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="card-flush">
                    <div class="card-header">
                        <div>
                            <h3 class="section-title">Вебхуки по этому коду</h3>
                            <p class="section-hint">
                                Что уходило вашему бэкенду и как он ответил.
                            </p>
                        </div>
                    </div>

                    @forelse ($log->webhookDeliveries as $delivery)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-6 py-4 last:border-0 dark:border-ink-800">
                            <div class="min-w-0">
                                <p class="font-mono text-sm text-ink-900 dark:text-ink-100">{{ $delivery->event }}</p>
                                <p class="text-xs text-ink-400 dark:text-ink-500">
                                    {{ $delivery->created_at->format('d.m.Y H:i:s') }} · попыток: {{ $delivery->attempts }}
                                    @if ($delivery->response_status)
                                        · ответ {{ $delivery->response_status }}
                                    @endif
                                </p>
                                @if ($delivery->error)
                                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $delivery->error }}</p>
                                @endif
                            </div>

                            <x-status-badge :status="$delivery->status" subject="webhook" />
                        </div>
                    @empty
                        <x-empty-state title="Вебхуков не было"
                                       description="Либо они выключены у проекта, либо статус кода ещё не менялся."
                                       icon="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    @endforelse
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card p-6">
                    <h3 class="section-title">Данные кода</h3>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-3" x-data="{ revealed: false }">
                            <dt class="text-ink-500 dark:text-ink-400">Номер</dt>
                            <dd class="text-right">
                                <span x-show="! revealed" class="font-mono">{{ $log->masked_phone }}</span>
                                <span x-show="revealed" x-cloak class="font-mono">{{ $log->phone }}</span>
                                <button type="button" x-on:click="revealed = ! revealed"
                                        class="ml-1 text-xs font-medium text-brand-600 dark:text-brand-400">
                                    <span x-show="! revealed">показать</span>
                                    <span x-show="revealed" x-cloak>скрыть</span>
                                </button>
                            </dd>
                        </div>

                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Статус</dt>
                            <dd><x-status-badge :status="$log->status" subject="otp" /></dd>
                        </div>

                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Попыток ввода</dt>
                            <dd class="tabular-nums text-ink-900 dark:text-ink-100">
                                {{ $log->attempts }} из {{ $log::MAX_VERIFY_ATTEMPTS }}
                            </dd>
                        </div>

                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Создан</dt>
                            <dd class="tabular-nums text-ink-900 dark:text-ink-100">{{ $log->created_at->format('d.m.Y H:i:s') }}</dd>
                        </div>

                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 dark:text-ink-400">Истекает</dt>
                            <dd class="tabular-nums text-ink-900 dark:text-ink-100">{{ $log->expires_at->format('H:i:s') }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="card p-6">
                    <h3 class="section-title">Отправитель</h3>

                    @if ($log->device)
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-500 dark:text-ink-400">Телефон</dt>
                                <dd class="text-ink-900 dark:text-ink-100">{{ $log->device->name }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-500 dark:text-ink-400">Номер SIM</dt>
                                <dd class="font-mono text-ink-900 dark:text-ink-100">
                                    {{ $log->device->phone_number ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-500 dark:text-ink-400">Сейчас</dt>
                                <dd><x-status-badge :status="$log->device->effective_status" /></dd>
                            </div>
                        </dl>

                        <a href="{{ route('projects.devices.index', $project) }}"
                           class="mt-4 inline-block text-sm font-medium text-brand-600 transition hover:text-brand-500 dark:text-brand-400">
                            Все устройства →
                        </a>
                    @else
                        <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">
                            Телефон не назначен: в момент запроса ни одного живого устройства не было,
                            и код сразу стал <code class="font-mono">failed</code>.
                        </p>
                    @endif
                </section>

                <section class="card p-6">
                    <h3 class="section-title">Проверить по API</h3>
                    <p class="section-hint">Тот же статус, что на этой странице.</p>

                    <x-copy-field class="mt-4" :value="'GET '.rtrim(config('app.url'), '/').'/api/v1/otp/'.$log->id" />
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
