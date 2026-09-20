@props(['value', 'label' => null])

{{-- Значение, которое нужно унести к себе: ключ, URL, код привязки. --}}
<div x-data="{ copied: false }" {{ $attributes }}>
    @if ($label)
        <p class="label-caps mb-1.5">{{ $label }}</p>
    @endif

    <div class="flex items-stretch gap-2">
        <code x-ref="value"
              class="flex-1 break-all rounded-xl bg-ink-100 px-3 py-2.5 font-mono text-sm text-ink-900 dark:bg-ink-950 dark:text-ink-100">{{ $value }}</code>

        <button type="button"
                x-on:click="navigator.clipboard.writeText($refs.value.textContent.trim()); copied = true; setTimeout(() => copied = false, 1600)"
                class="shrink-0 rounded-xl border border-ink-300 px-3 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-ink-700 dark:text-ink-200 dark:hover:bg-ink-800">
            <span x-show="! copied">Копировать</span>
            <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400">Готово</span>
        </button>
    </div>
</div>
