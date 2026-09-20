<x-app-layout>
    <x-slot name="title">{{ $project->name }} — устройства</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="label-caps">Проект</p>
                <h1 class="truncate text-2xl font-bold text-ink-900 dark:text-ink-50">{{ $project->name }}</h1>
            </div>

            <form method="POST" action="{{ route('projects.pairing-codes.store', $project) }}">
                @csrf
                <x-primary-button>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM13.5 14.625h3v3h-3v-3ZM19.5 17.625h-3v3h3v-3Z" />
                    </svg>
                    Подключить устройство
                </x-primary-button>
            </form>
        </div>

        <div class="mt-5">
            <x-project-nav :project="$project" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-flash>{{ session('status') }}</x-flash>
        @endif

        @php
            $online = $devices->filter(fn ($device) => $device->effective_status === 'active');
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Всего устройств" :value="$devices->count()"
                    icon="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />

            <x-stat label="На связи" :value="$online->count()"
                    :tone="$online->isNotEmpty() ? 'emerald' : 'rose'"
                    :hint="'heartbeat ≤ '.$onlineThresholdMinutes.' мин'"
                    icon="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />

            <x-stat label="Офлайн" :value="$devices->count() - $online->count()" tone="slate"
                    icon="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />

            <x-stat label="Пропускная способность" :value="$throughputPerMinute.' SMS/мин'"
                    hint="сумма лимитов телефонов на связи"
                    icon="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
        </div>

        @if ($devices->isNotEmpty() && $online->isEmpty())
            <x-flash tone="warning">
                Ни один телефон не выходил на связь больше {{ $onlineThresholdMinutes }} минут — запросы кодов
                сейчас получают <code class="font-mono">503 no active device available</code>.
                Проверьте, запущено ли приложение и снят ли режим экономии батареи.
            </x-flash>
        @endif

        <section class="card-flush">
            <div class="card-header">
                <div>
                    <h3 class="section-title">Устройства</h3>
                    <p class="section-hint">
                        Телефон считается офлайн, если не выходил на связь более
                        {{ $onlineThresholdMinutes }} мин.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                    <thead class="table-head">
                        <tr>
                            <th class="px-6 py-3">Устройство</th>
                            <th class="px-6 py-3">Номер SIM</th>
                            <th class="px-6 py-3">Скорость</th>
                            <th class="px-6 py-3">Статус</th>
                            <th class="px-6 py-3">Заряд</th>
                            <th class="px-6 py-3">Последний онлайн</th>
                            <th class="px-6 py-3 text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @forelse ($devices as $device)
                            <tr class="table-row">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span @class([
                                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                                            'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400' => $device->effective_status === 'active',
                                            'bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500' => $device->effective_status !== 'active',
                                        ])>
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                                            </svg>
                                        </span>
                                        <span class="font-medium text-ink-900 dark:text-ink-50">
                                            {{ $device->name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-mono">
                                    @if ($device->phone_number)
                                        {{ $device->phone_number }}
                                    @else
                                        <span class="font-sans text-xs text-amber-600 dark:text-amber-400">
                                            не указан
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 tabular-nums">
                                    {{ $device->throughput_per_minute }}
                                    <span class="text-xs text-ink-400 dark:text-ink-500">SMS/мин</span>
                                </td>
                                <td class="px-6 py-4">
                                    <x-status-badge :status="$device->effective_status" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 tabular-nums">
                                    @if ($device->battery_level !== null)
                                        <span @class([
                                            'text-rose-600 dark:text-rose-400' => $device->battery_level < 20,
                                            'text-ink-700 dark:text-ink-300' => $device->battery_level >= 20,
                                        ])>{{ $device->battery_level }}%</span>
                                    @else
                                        <span class="text-ink-400 dark:text-ink-500">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 tabular-nums">
                                    @if ($device->last_seen_at)
                                        <span title="{{ $device->last_seen_at->format('d.m.Y H:i:s') }}">
                                            {{ $device->last_seen_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-ink-400 dark:text-ink-500">никогда</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <button type="button"
                                            x-data=""
                                            x-on:click="$dispatch('open-modal', 'edit-device-{{ $device->id }}')"
                                            class="text-sm font-semibold text-brand-600 transition hover:text-brand-500 dark:text-brand-400">
                                        Настроить
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state title="Устройств пока нет"
                                                   description="Установите Android-приложение шлюза на телефон с SIM-картой и отсканируйте код привязки."
                                                   icon="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3">
                                        <form method="POST" action="{{ route('projects.pairing-codes.store', $project) }}">
                                            @csrf
                                            <x-primary-button>Получить код привязки</x-primary-button>
                                        </form>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{--
        Настройки отправителя. Номер SIM телефон прочитать о себе не может, а
        скорость зависит от аппарата и оператора — и то и другое задаёт человек.
    --}}
    @foreach ($devices as $device)
        <x-modal :name="'edit-device-'.$device->id" :show="$errors->any() && old('device_id') == $device->id" focusable>
            <form method="POST" action="{{ route('projects.devices.update', [$project, $device]) }}" class="p-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="device_id" value="{{ $device->id }}">

                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">Настройки устройства</h2>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    Эти значения знает только оператор: телефон не может прочитать свой номер,
                    а сколько SMS в минуту он потянет — зависит от аппарата и тарифа.
                </p>

                <div class="mt-6 space-y-4">
                    <div>
                        <x-input-label :for="'name-'.$device->id" value="Имя устройства" />
                        <x-text-input :id="'name-'.$device->id" name="name" type="text" class="mt-1.5"
                                      :value="old('device_id') == $device->id ? old('name') : $device->name" required />
                        <x-input-error :messages="old('device_id') == $device->id ? $errors->get('name') : []" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label :for="'phone-'.$device->id" value="Номер SIM" />
                        <x-text-input :id="'phone-'.$device->id" name="phone_number" type="tel" class="mt-1.5 font-mono"
                                      placeholder="+99365123456"
                                      :value="old('device_id') == $device->id ? old('phone_number') : $device->phone_number" />
                        <p class="mt-1.5 text-xs text-ink-400 dark:text-ink-500">
                            Номер, который увидит получатель. По нему же вызывающий может
                            зафиксировать отправителя параметром <code class="font-mono">from</code>.
                        </p>
                        <x-input-error :messages="old('device_id') == $device->id ? $errors->get('phone_number') : []" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label :for="'throughput-'.$device->id" value="Скорость отправки, SMS в минуту" />
                        <x-text-input :id="'throughput-'.$device->id" name="throughput_per_minute" type="number"
                                      min="1" :max="$maxThroughput" class="mt-1.5 tabular-nums"
                                      :value="old('device_id') == $device->id ? old('throughput_per_minute') : $device->throughput_per_minute"
                                      required />
                        <p class="mt-1.5 text-xs text-ink-400 dark:text-ink-500">
                            Шлюз не отдаст этому телефону больше указанного за минуту. Одна отправка
                            занимает 2–5 секунд, а Android сам режет фоновую рассылку примерно
                            на 30 сообщениях за полчаса — ставить много смысла нет.
                        </p>
                        <x-input-error :messages="old('device_id') == $device->id ? $errors->get('throughput_per_minute') : []" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Отмена</x-secondary-button>
                    <x-primary-button>Сохранить</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endforeach

    <x-modal name="pair-device" :show="session('show_pairing') || $errors->has('name')" focusable>
        <div class="p-6">
            <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">
                Подключение устройства
            </h2>

            @if ($pairingCode && $pairingCode->isUsable())
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    Откройте Android-приложение шлюза → «Сканировать QR-код». Код действует
                    {{ $pairingCode::LIFETIME_MINUTES }} мин и срабатывает один раз.
                </p>

                <div class="mt-6 flex flex-col items-center gap-4">
                    <div class="rounded-2xl bg-white p-3 ring-1 ring-ink-200 dark:ring-ink-700">
                        {!! $pairingQr !!}
                    </div>

                    <div class="text-center">
                        <p class="label-caps">Код привязки</p>
                        <p class="mt-1 font-mono text-2xl tracking-[0.3em] text-ink-900 dark:text-ink-50">
                            {{ $pairingCode->code }}
                        </p>

                        {{-- Счётчик тикает вживую и сам сообщает, что код протух. --}}
                        <div x-data="{ left: {{ $pairingCode->secondsLeft() }} }"
                             x-init="setInterval(() => left > 0 && left--, 1000)"
                             class="mt-1 text-xs">
                            <p x-show="left > 0" class="text-ink-500 dark:text-ink-400">
                                истекает через
                                <span class="font-medium tabular-nums"
                                      x-text="Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')"></span>
                            </p>
                            <p x-show="left === 0" x-cloak class="font-medium text-rose-600 dark:text-rose-400">
                                Код устарел, сгенерируйте новый.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    @if ($pairingCode?->isUsed())
                        Код уже использован — сгенерируйте новый для следующего телефона.
                    @elseif ($pairingCode)
                        Код устарел, сгенерируйте новый.
                    @else
                        Сгенерируйте код привязки и отсканируйте его в Android-приложении шлюза.
                    @endif
                </p>

                <div class="mt-6 flex justify-center">
                    <div class="flex h-44 w-44 items-center justify-center rounded-2xl border-2 border-dashed border-ink-300 text-center text-xs text-ink-400 dark:border-ink-700 dark:text-ink-500">
                        QR-код<br>появится здесь
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('projects.pairing-codes.store', $project) }}" class="mt-6">
                @csrf
                <x-secondary-button type="submit" class="w-full">
                    @if ($pairingCode && $pairingCode->isUsable())
                        Сгенерировать новый код
                    @else
                        Сгенерировать код
                    @endif
                </x-secondary-button>
            </form>

            <div class="mt-6 border-t border-ink-100 pt-6 dark:border-ink-800">
                <p class="text-xs text-ink-500 dark:text-ink-400">
                    Нет доступа к телефону? Можно завести карточку устройства вручную —
                    она станет активной, когда телефон начнёт слать heartbeat.
                </p>

                <form method="POST" action="{{ route('projects.devices.store', $project) }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="device_name" value="Имя устройства" />
                        <x-text-input id="device_name" name="name" type="text" class="mt-1.5"
                                      placeholder="Redmi Note 12 — офис" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <x-secondary-button type="button" x-on:click="$dispatch('close')">Отмена</x-secondary-button>
                        <x-primary-button>Добавить устройство</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </x-modal>
</x-app-layout>
