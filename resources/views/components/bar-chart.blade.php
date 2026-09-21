@props([
    'series',
    'height' => 140,
    'labelEvery' => 3,
])

@php
    $points = collect($series);
    $peak = max(1, (int) $points->max('total'));

    // Ширина столбца в процентах: график тянется на всю карточку, поэтому
    // считаем в долях, а не в пикселях.
    $slot = $points->count() > 0 ? 100 / $points->count() : 100;
    $barWidth = $slot * 0.62;
    $empty = $points->sum('total') === 0;
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($empty)
        <div class="flex items-center justify-center rounded-xl border border-dashed border-ink-200 py-10 text-sm text-ink-400 dark:border-ink-800 dark:text-ink-500"
             style="height: {{ $height }}px">
            За этот период кодов не было
        </div>
    @else
        {{--
            Инлайновый SVG вместо библиотеки: данных десятки точек, а лишние
            300 КБ JavaScript на странице, которую держат открытой весь день,
            ничем не окупаются.
        --}}
        <svg viewBox="0 0 100 {{ $height }}" preserveAspectRatio="none"
             class="w-full" style="height: {{ $height }}px" role="img"
             aria-label="Отправки за период">
            @foreach ($points as $i => $point)
                @php
                    $x = $i * $slot + ($slot - $barWidth) / 2;
                    $sentHeight = $point['sent'] / $peak * ($height - 4);
                    $failedHeight = $point['failed'] / $peak * ($height - 4);
                @endphp

                {{-- Невидимая дорожка на всю высоту: по ней ловится наведение. --}}
                <rect x="{{ $i * $slot }}" y="0" width="{{ $slot }}" height="{{ $height }}"
                      class="fill-transparent hover:fill-ink-500/5">
                    <title>{{ $point['label'] }} — всего {{ $point['total'] }}, ошибок {{ $point['failed'] }}</title>
                </rect>

                @if ($point['failed'] > 0)
                    <rect x="{{ $x }}" y="{{ $height - $failedHeight }}"
                          width="{{ $barWidth }}" height="{{ $failedHeight }}"
                          class="fill-rose-500" rx="0.6" />
                @endif

                @if ($point['sent'] > 0)
                    <rect x="{{ $x }}" y="{{ $height - $failedHeight - $sentHeight }}"
                          width="{{ $barWidth }}" height="{{ $sentHeight }}"
                          class="fill-brand-500 dark:fill-brand-400" rx="0.6" />
                @endif
            @endforeach
        </svg>

        <div class="mt-2 flex justify-between text-[10px] tabular-nums text-ink-400 dark:text-ink-500">
            @foreach ($points as $i => $point)
                <span class="{{ $i % $labelEvery === 0 ? '' : 'invisible' }}">{{ $point['label'] }}</span>
            @endforeach
        </div>

        <div class="mt-3 flex items-center gap-4 text-xs text-ink-500 dark:text-ink-400">
            <span class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-sm bg-brand-500 dark:bg-brand-400"></span>
                отправлено: {{ $points->sum('sent') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-sm bg-rose-500"></span>
                ошибок: {{ $points->sum('failed') }}
            </span>
            <span class="ml-auto">пик: {{ $peak }}</span>
        </div>
    @endif
</div>
