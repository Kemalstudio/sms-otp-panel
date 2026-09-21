@php
    $tones = [
        'ok' => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700 dark:text-emerald-300', 'label' => 'в норме'],
        'warn' => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700 dark:text-amber-300', 'label' => 'внимание'],
        'fail' => ['dot' => 'bg-rose-500', 'text' => 'text-rose-700 dark:text-rose-300', 'label' => 'сбой'],
    ];

    $worst = collect($checks)->pluck('state');
    $overall = $worst->contains('fail') ? 'fail' : ($worst->contains('warn') ? 'warn' : 'ok');
@endphp

<x-app-layout>
    <x-slot name="title">Здоровье системы</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="label-caps">Инфраструктура</p>
                <h1 class="text-2xl font-bold text-ink-900 dark:text-ink-50">Здоровье системы</h1>
            </div>

            <p class="text-sm text-ink-500 dark:text-ink-400">
                Проверено {{ now()->format('d.m.Y H:i:s') }}
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        {{-- Общий вердикт: то, ради чего страницу открывают в первую секунду. --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br
                    {{ $overall === 'ok' ? 'from-emerald-600 to-teal-700' : ($overall === 'warn' ? 'from-amber-500 to-orange-600' : 'from-rose-600 to-red-700') }}
                    p-6 shadow-lift sm:p-8">
            <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-white/10 blur-2xl"></div>

            <div class="relative">
                <h2 class="text-xl font-bold text-white sm:text-2xl">
                    @if ($overall === 'ok')
                        Инфраструктура в порядке
                    @elseif ($overall === 'warn')
                        Есть на что посмотреть
                    @else
                        Что-то сломано
                    @endif
                </h2>
                <p class="mt-2 max-w-2xl text-sm text-white/80">
                    @if ($overall === 'ok')
                        База, очередь и планировщик отвечают, копии свежие. Коды уходят.
                    @else
                        Ниже отмечено красным и жёлтым то, что требует внимания. Пока это не
                        починено, отправка кодов может останавливаться без предупреждения.
                    @endif
                </p>
            </div>
        </div>

        <section class="card-flush">
            <div class="card-header">
                <div>
                    <h3 class="section-title">Проверки</h3>
                    <p class="section-hint">
                        Алёрты приходят постфактум и только владельцу проекта. Здесь видно
                        состояние прямо сейчас — в том числе то, что ещё не сломалось.
                    </p>
                </div>
            </div>

            <ul class="divide-y divide-ink-100 dark:divide-ink-800">
                @foreach ($checks as $check)
                    @php($tone = $tones[$check['state']])

                    <li class="flex flex-wrap items-center gap-4 px-6 py-4">
                        <span class="flex h-2.5 w-2.5 shrink-0 rounded-full {{ $tone['dot'] }}"></span>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink-900 dark:text-ink-100">{{ $check['name'] }}</p>
                            <p class="text-sm text-ink-500 dark:text-ink-400">{{ $check['hint'] }}</p>
                        </div>

                        <div class="text-right">
                            <p class="font-mono text-sm text-ink-900 dark:text-ink-100">{{ $check['value'] }}</p>
                            <p class="text-xs {{ $tone['text'] }}">{{ $tone['label'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card-flush">
                <div class="card-header">
                    <div>
                        <h3 class="section-title">Телефоны</h3>
                        <p class="section-hint">Все аппараты всех проектов.</p>
                    </div>
                </div>

                @forelse ($devices as $device)
                    <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-6 py-4 last:border-0 dark:border-ink-800">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink-900 dark:text-ink-100">{{ $device->name }}</p>
                            <p class="text-xs text-ink-400 dark:text-ink-500">
                                {{ $device->project?->name }}
                                @if ($device->battery_level !== null)
                                    · заряд {{ $device->battery_level }}%
                                @endif
                                · {{ $device->last_seen_at?->diffForHumans() ?? 'ни разу не выходил на связь' }}
                            </p>
                        </div>

                        <x-status-badge :status="$device->effective_status" />
                    </div>
                @empty
                    <x-empty-state title="Телефонов нет"
                                   description="Без единого устройства шлюз не отправит ни одного кода."
                                   icon="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                @endforelse
            </section>

            <section class="card-flush">
                <div class="card-header">
                    <div>
                        <h3 class="section-title">История аварий</h3>
                        <p class="section-hint">Последние 15, включая закрытые.</p>
                    </div>
                </div>

                @forelse ($alerts as $alert)
                    <div class="border-b border-ink-100 px-6 py-4 last:border-0 dark:border-ink-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-medium text-ink-900 dark:text-ink-100">
                                {{ $alert->title }}
                                @if ($alert->project)
                                    <span class="font-normal text-ink-400">· {{ $alert->project->name }}</span>
                                @endif
                            </p>

                            @if ($alert->isActive())
                                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-400/15 dark:text-rose-300">
                                    открыта
                                </span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300">
                                    закрыта
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-xs text-ink-400 dark:text-ink-500">
                            {{ $alert->started_at->format('d.m.Y H:i') }}
                            @if ($alert->resolved_at)
                                → {{ $alert->resolved_at->format('H:i') }}
                                ({{ $alert->started_at->diffForHumans($alert->resolved_at, true) }})
                            @else
                                · {{ $alert->started_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                @empty
                    <x-empty-state title="Аварий не было"
                                   description="Ни одной с момента запуска — так и должно быть."
                                   icon="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                @endforelse
            </section>
        </div>
    </div>
</x-app-layout>
