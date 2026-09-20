@props(['project'])

@php
    $tabs = [
        [
            'route' => 'projects.show',
            'label' => 'Обзор',
            'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
        ],
        [
            'route' => 'projects.devices.index',
            'label' => 'Устройства',
            'icon' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
        ],
        [
            'route' => 'projects.api-keys.index',
            'label' => 'API-ключи',
            'icon' => 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z',
        ],
        [
            'route' => 'projects.webhooks.index',
            'label' => 'Вебхуки',
            'icon' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
        ],
        [
            'route' => 'projects.logs.index',
            'label' => 'Логи OTP',
            'icon' => 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
        ],
    ];
@endphp

{{-- Вкладки-пилюли: активная читается с первого взгляда и в светлой, и в тёмной теме. --}}
<nav {{ $attributes->merge(['class' => 'flex gap-1 overflow-x-auto rounded-xl bg-ink-100/70 p-1 dark:bg-ink-900/70']) }}>
    @foreach ($tabs as $tab)
        @php($current = request()->routeIs($tab['route']))

        <a href="{{ route($tab['route'], $project) }}"
           @if ($current) aria-current="page" @endif
           @class([
               'flex items-center gap-2 whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition',
               'bg-white text-ink-900 shadow-card dark:bg-ink-800 dark:text-ink-50' => $current,
               'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-ink-100' => ! $current,
           ])>
            <svg class="h-4 w-4 {{ $current ? 'text-brand-600 dark:text-brand-400' : '' }}"
                 fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}" />
            </svg>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
