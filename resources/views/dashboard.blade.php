<x-app-layout>
    <x-slot name="title">Проекты — SMS OTP Gateway</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-ink-900 dark:text-ink-50">Проекты</h1>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                    Каждый проект — свой набор телефонов, ключей и логов.
                </p>
            </div>

            <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-project')">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Создать проект
            </x-primary-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-flash>{{ session('status') }}</x-flash>
        @endif

        @if ($projects->isNotEmpty())
            {{-- Сводка по всем проектам: жив ли шлюз вообще, до выбора проекта. --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat label="Проектов"
                        :value="$projects->count()"
                        icon="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />

                <x-stat label="Телефонов на связи"
                        :value="$projects->sum('online_devices_count')"
                        :tone="$projects->sum('online_devices_count') > 0 ? 'emerald' : 'rose'"
                        :hint="'heartbeat не старше '.$onlineThresholdMinutes.' мин'"
                        icon="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />

                <x-stat label="Кодов сегодня"
                        :value="$projects->sum('otp_today_count')"
                        icon="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($projects as $project)
                <a href="{{ route('projects.show', $project) }}"
                   class="card group flex flex-col p-6 transition hover:-translate-y-0.5 hover:shadow-lift hover:ring-brand-500/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-lg font-semibold text-ink-900 dark:text-ink-50">
                                {{ $project->name }}
                            </h3>
                            <p class="mt-1 text-xs text-ink-400 dark:text-ink-500">
                                создан {{ $project->created_at->format('d.m.Y') }}
                            </p>
                        </div>

                        <x-status-badge :status="$project->online_devices_count > 0 ? 'active' : 'inactive'"
                                        class="shrink-0" />
                    </div>

                    <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-ink-100 pt-5 dark:border-ink-800">
                        <div>
                            <dt class="text-xs text-ink-500 dark:text-ink-400">Телефонов онлайн</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-ink-900 dark:text-ink-50">
                                {{ $project->online_devices_count }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-500 dark:text-ink-400">Кодов сегодня</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-ink-900 dark:text-ink-50">
                                {{ $project->otp_today_count }}
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-4 flex items-center justify-between gap-2 text-xs text-ink-400 dark:text-ink-500">
                        <span>онлайн = heartbeat ≤ {{ $onlineThresholdMinutes }} мин</span>
                        <span class="shrink-0 font-semibold text-brand-600 opacity-0 transition group-hover:opacity-100 dark:text-brand-400">
                            Открыть →
                        </span>
                    </p>
                </a>
            @empty
                <div class="card col-span-full">
                    <x-empty-state title="Проектов пока нет"
                                   description="Проект — это контейнер для телефонов, ключей и логов. Начните с одного.">
                        <x-primary-button x-data=""
                                          x-on:click.prevent="$dispatch('open-modal', 'create-project')">
                            Создать первый проект
                        </x-primary-button>
                    </x-empty-state>
                </div>
            @endforelse
        </div>
    </div>

    <x-modal name="create-project" :show="$errors->has('name')" focusable>
        <form method="POST" action="{{ route('projects.store') }}" class="p-6">
            @csrf

            <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">Новый проект</h2>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                После создания панель проведёт вас по четырём шагам до первого отправленного кода.
            </p>

            <div class="mt-6">
                <x-input-label for="project_name" value="Название" />
                <x-text-input id="project_name" name="name" type="text" class="mt-1.5"
                              placeholder="Интернет-магазин" :value="old('name')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Отмена</x-secondary-button>
                <x-primary-button>Создать</x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
