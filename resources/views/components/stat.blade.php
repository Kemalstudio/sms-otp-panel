@props(['label', 'value', 'hint' => null, 'tone' => 'brand', 'icon' => null])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-400',
        'slate' => 'bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'card flex items-center gap-4 p-5']) }}>
    @if ($icon)
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
            </svg>
        </span>
    @endif

    <div class="min-w-0">
        <p class="truncate text-sm text-ink-500 dark:text-ink-400">{{ $label }}</p>
        <p class="text-2xl font-bold tabular-nums text-ink-900 dark:text-ink-50">{{ $value }}</p>
        @if ($hint)
            <p class="mt-0.5 truncate text-xs text-ink-400 dark:text-ink-500">{{ $hint }}</p>
        @endif
    </div>
</div>
