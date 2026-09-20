@php
    // Те же формулировки, что и в бейдже статуса, чтобы фильтр и строки не спорили.
    $statusLabels = [
        'pending' => 'Отправляется',
        'sent' => 'Отправлен',
        'delivered' => 'Подтверждён',
        'failed' => 'Ошибка',
        'expired' => 'Истёк',
    ];
@endphp

<x-app-layout>
    <x-slot name="title">{{ $project->name }} — логи OTP</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="label-caps">Проект</p>
                <h1 class="truncate text-2xl font-bold text-ink-900 dark:text-ink-50">{{ $project->name }}</h1>
            </div>

            <form method="GET" action="{{ route('projects.logs.index', $project) }}" class="flex items-center gap-2">
                <label for="status" class="text-sm text-ink-500 dark:text-ink-400">Статус</label>
                <select id="status" name="status" onchange="this.form.submit()"
                        class="rounded-xl border-ink-300 bg-white text-sm text-ink-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-100">
                    <option value="">все</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($activeStatus === $status)>
                            {{ $statusLabels[$status] ?? $status }}
                        </option>
                    @endforeach
                </select>
                <noscript>
                    <x-primary-button>Фильтр</x-primary-button>
                </noscript>
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

        <section class="card-flush">
            <div class="card-header">
                <div>
                    <h3 class="section-title">
                        Логи OTP
                        @if ($activeStatus)
                            <span class="ml-1 text-sm font-normal text-ink-500 dark:text-ink-400">
                                · {{ $statusLabels[$activeStatus] ?? $activeStatus }}
                            </span>
                        @endif
                    </h3>
                    <p class="section-hint">
                        Номера замаскированы. Полный номер — по клику на «показать».
                        Сам код нигде не хранится в открытом виде.
                    </p>
                </div>

                <p class="text-sm tabular-nums text-ink-500 dark:text-ink-400">
                    Всего: <span class="font-semibold text-ink-900 dark:text-ink-100">{{ $logs->total() }}</span>
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                    <thead class="table-head">
                        <tr>
                            <th class="px-6 py-3">Телефон</th>
                            <th class="px-6 py-3">Устройство</th>
                            <th class="px-6 py-3">Статус</th>
                            <th class="px-6 py-3">Создан</th>
                            <th class="px-6 py-3">Истекает</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @forelse ($logs as $log)
                            <tr class="table-row">
                                {{--
                                    Раскрытие пока клиентское, то есть полный номер лежит
                                    в разметке страницы. Когда появится отдельный
                                    аудируемый эндпоинт — тянуть номер по запросу.
                                --}}
                                <td class="whitespace-nowrap px-6 py-4 font-mono" x-data="{ revealed: false }">
                                    <span x-show="! revealed">{{ $log->masked_phone }}</span>
                                    <span x-show="revealed" x-cloak>{{ $log->phone }}</span>
                                    <button type="button" x-on:click="revealed = ! revealed"
                                            class="ml-2 font-sans text-xs font-medium text-brand-600 transition hover:text-brand-500 dark:text-brand-400">
                                        <span x-show="! revealed">показать</span>
                                        <span x-show="revealed" x-cloak>скрыть</span>
                                    </button>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($log->device)
                                        {{ $log->device->name }}
                                    @else
                                        <span class="text-ink-400 dark:text-ink-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <x-status-badge :status="$log->status" subject="otp" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 tabular-nums">
                                    {{ $log->created_at->format('d.m.Y H:i:s') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 tabular-nums text-ink-500 dark:text-ink-400">
                                    {{ $log->expires_at->format('H:i:s') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state
                                        :title="$activeStatus ? 'С таким статусом записей нет' : 'Записей пока нет'"
                                        description="Каждый вызов /api/v1/otp/send оставляет здесь строку — от постановки в очередь до подтверждения кода."
                                        icon="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-ink-100 p-4 dark:border-ink-800">
                    {{ $logs->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
