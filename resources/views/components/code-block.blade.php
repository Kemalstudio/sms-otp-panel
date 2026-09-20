@props(['snippets', 'height' => null])

{{--
    Фрагменты кода с вкладками по языкам и копированием одним нажатием.
    $snippets — массив ['label' => 'cURL', 'code' => '...'].
--}}

<div x-data="{
        tab: 0,
        copied: false,
        copy() {
            navigator.clipboard.writeText($refs['code' + this.tab].textContent.trim());
            this.copied = true;
            setTimeout(() => this.copied = false, 1600);
        },
     }"
     {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl ring-1 ring-ink-900/10 dark:ring-white/10']) }}>
    <div class="flex items-center justify-between gap-2 border-b border-white/5 bg-ink-900 px-2 dark:bg-ink-900">
        <div class="flex overflow-x-auto">
            @foreach ($snippets as $i => $snippet)
                <button type="button"
                        x-on:click="tab = {{ $i }}"
                        :class="tab === {{ $i }}
                            ? 'border-brand-400 text-white'
                            : 'border-transparent text-ink-400 hover:text-ink-200'"
                        class="whitespace-nowrap border-b-2 px-3 py-2.5 text-xs font-medium transition">
                    {{ $snippet['label'] }}
                </button>
            @endforeach
        </div>

        <button type="button"
                x-on:click="copy()"
                class="mr-1 shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink-400 transition hover:bg-white/5 hover:text-white">
            <span x-show="! copied">Копировать</span>
            <span x-show="copied" x-cloak class="text-emerald-400">Скопировано</span>
        </button>
    </div>

    @foreach ($snippets as $i => $snippet)
        <pre x-show="tab === {{ $i }}"
             @if ($i > 0) x-cloak @endif
             class="overflow-x-auto bg-ink-950 p-4 font-mono text-[13px] leading-relaxed text-ink-100"
             @if ($height) style="max-height: {{ $height }}" @endif><code x-ref="code{{ $i }}">{{ $snippet['code'] }}</code></pre>
    @endforeach
</div>
