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

            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('projects.logs.index', $project) }}"
                      class="flex flex-wrap items-center gap-2">
                    {{-- Поиск по номеру: с чего начинается почти любой разбор жалобы. --}}
                    <input type="search" name="phone" value="{{ $activePhone }}"
                           placeholder="Поиск по номеру"
                           class="w-44 rounded-xl border-ink-300 bg-white text-sm text-ink-900 shadow-sm placeholder:text-ink-400 focus:border-brand-500 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-100 dark:placeholder:text-ink-600">

                    <select name="status" onchange="this.form.submit()"
                            class="rounded-xl border-ink-300 bg-white text-sm text-ink-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-100">
                        <option value="">все статусы</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($activeStatus === $status)>
                                {{ $statusLabels[$status] ?? $status }}
                            </option>
                        @endforeach
                    </select>

                    <x-secondary-button type="submit">Найти</x-secondary-button>

                    @if ($activePhone || $activeStatus)
                        <a href="{{ route('projects.logs.index', $project) }}"
                           class="text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-ink-100">
                            сбросить
                        </a>
                    @endif
                </form>

                <a href="{{ route('projects.logs.export', [$project, 'status' => $activeStatus, 'phone' => $activePhone]) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-ink-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-ink-700 shadow-sm transition hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200 dark:hover:bg-ink-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    CSV
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
                        @if ($activePhone)
                            <span class="ml-1 text-sm font-normal text-ink-500 dark:text-ink-400">
                                · номер содержит «{{ $activePhone }}»
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
                            <th class="px-6 py-3"></th>
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
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <a href="{{ route('projects.logs.show', [$project, $log]) }}"
                                       class="text-sm font-semibold text-brand-600 transition hover:text-brand-500 dark:text-brand-400">
                                        Подробно
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
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
