@php
    // The switcher is the fastest way between projects, so it lives in the bar
    // rather than behind a trip to the dashboard.
    $navProjects = auth()->user()?->projects()->latest()->get() ?? collect();
    $currentProject = request()->route('project');
@endphp

<nav x-data="{ open: false }"
     class="sticky top-0 z-40 border-b border-ink-200/70 bg-white/80 backdrop-blur-md dark:border-ink-800 dark:bg-ink-950/80">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-white shadow-lift">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75h7.5A2.25 2.25 0 0 1 18 6v12a2.25 2.25 0 0 1-2.25 2.25h-7.5A2.25 2.25 0 0 1 6 18V6a2.25 2.25 0 0 1 2.25-2.25Zm2.25 13.5h3" />
                        </svg>
                    </span>
                    <span class="hidden text-sm font-semibold text-ink-900 dark:text-ink-50 sm:block">
                        SMS OTP Gateway
                    </span>
                </a>

                @if ($navProjects->isNotEmpty())
                    <span class="hidden text-ink-300 dark:text-ink-700 sm:block">/</span>

                    <x-dropdown align="left" width="w-64" contentClasses="py-1 bg-white dark:bg-ink-900">
                        <x-slot name="trigger">
                            <button class="flex min-w-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-ink-700 transition hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">
                                <span class="truncate">
                                    {{ $currentProject?->name ?? 'Все проекты' }}
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 15 3.75 3.75L15.75 15m-7.5-6L12 5.25 15.75 9" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <p class="px-4 py-2 label-caps">Проекты</p>

                            @foreach ($navProjects as $navProject)
                                <a href="{{ route('projects.show', $navProject) }}"
                                   @class([
                                       'flex items-center justify-between gap-2 px-4 py-2 text-sm transition',
                                       'bg-brand-50 font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' => $currentProject?->is($navProject),
                                       'text-ink-700 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800' => ! $currentProject?->is($navProject),
                                   ])>
                                    <span class="truncate">{{ $navProject->name }}</span>
                                    @if ($currentProject?->is($navProject))
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    @endif
                                </a>
                            @endforeach

                            <div class="my-1 border-t border-ink-100 dark:border-ink-800"></div>

                            <a href="{{ route('dashboard') }}"
                               class="block px-4 py-2 text-sm text-ink-500 transition hover:bg-ink-50 dark:text-ink-400 dark:hover:bg-ink-800">
                                Все проекты
                            </a>
                        </x-slot>
                    </x-dropdown>
                @endif
            </div>

            <div class="flex items-center gap-1">
                <a href="{{ route('dashboard') }}"
                   @class([
                       'hidden rounded-lg px-3 py-2 text-sm font-medium transition sm:block',
                       'bg-ink-100 text-ink-900 dark:bg-ink-800 dark:text-ink-50' => request()->routeIs('dashboard'),
                       'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-ink-100' => ! request()->routeIs('dashboard'),
                   ])>
                    Проекты
                </a>

                @foreach ([['system.docs', 'Документация'], ['system.health', 'Здоровье']] as [$routeName, $label])
                    <a href="{{ route($routeName) }}"
                       @class([
                           'hidden rounded-lg px-3 py-2 text-sm font-medium transition lg:block',
                           'bg-ink-100 text-ink-900 dark:bg-ink-800 dark:text-ink-50' => request()->routeIs($routeName),
                           'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-ink-100' => ! request()->routeIs($routeName),
                       ])>
                        {{ $label }}
                    </a>
                @endforeach

                <x-theme-toggle />

                <x-dropdown align="right" width="48" contentClasses="py-1 bg-white dark:bg-ink-900">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-ink-600 transition hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold uppercase text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                {{ mb_substr(Auth::user()->name, 0, 1) }}
                            </span>
                            <span class="hidden max-w-[10rem] truncate sm:block">{{ Auth::user()->name }}</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-ink-100 px-4 py-3 dark:border-ink-800">
                            <p class="truncate text-sm font-medium text-ink-900 dark:text-ink-100">{{ Auth::user()->name }}</p>
                            <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ Auth::user()->email }}</p>
                        </div>

                        <x-dropdown-link :href="route('profile.edit')">Профиль</x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                             onclick="event.preventDefault(); this.closest('form').submit();">
                                Выйти
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>
