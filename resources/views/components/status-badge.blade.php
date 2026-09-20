@props(['status', 'subject' => 'device'])

@php
    // Тон задаёт и пилюлю, и точку, чтобы смысл нёс не только цвет, но и подпись.
    $tones = [
        'emerald' => ['pill' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/30', 'dot' => 'bg-emerald-500'],
        'slate' => ['pill' => 'bg-ink-50 text-ink-600 ring-ink-500/20 dark:bg-ink-400/10 dark:text-ink-300 dark:ring-ink-400/25', 'dot' => 'bg-ink-400'],
        'rose' => ['pill' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/30', 'dot' => 'bg-rose-500'],
        'amber' => ['pill' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30', 'dot' => 'bg-amber-500'],
        'sky' => ['pill' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30', 'dot' => 'bg-sky-500'],
    ];

    /*
     * У телефона и у ключа статус называется одинаково ('active'), но означает
     * разное: телефон «на связи», ключ «действует». Подпись выбирается по
     * subject, иначе таблица ключей утверждала бы, что ключ «онлайн».
     */
    $map = [
        'device' => [
            'active' => ['Онлайн', 'emerald', true],
            'inactive' => ['Офлайн', 'slate', false],
        ],
        'key' => [
            'active' => ['Активен', 'emerald', false],
            'revoked' => ['Отозван', 'rose', false],
        ],
        'webhook' => [
            'pending' => ['В очереди', 'amber', true],
            'delivered' => ['Доставлено', 'emerald', false],
            'failed' => ['Не доставлено', 'rose', false],
        ],
        'otp' => [
            'pending' => ['Отправляется', 'amber', true],
            'sent' => ['Отправлен', 'sky', false],
            'delivered' => ['Подтверждён', 'emerald', false],
            'failed' => ['Ошибка', 'rose', false],
            'expired' => ['Истёк', 'slate', false],
        ],
    ];

    $labels = $map[$subject] ?? $map['device'];

    // Значения, общие для всех сущностей, чтобы вызов без subject не ломался.
    $fallback = array_merge($map['otp'], $map['key'], $map['device']);

    [$label, $tone, $pulses] = $labels[$status] ?? $fallback[$status] ?? [$status, 'slate', false];
    $t = $tones[$tone];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset '.$t['pill']]) }}
      title="{{ $status }}">
    <span class="relative flex h-1.5 w-1.5">
        @if ($pulses)
            {{-- Живые состояния пульсируют: строка «в процессе» видна с одного взгляда. --}}
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75 {{ $t['dot'] }}"></span>
        @endif
        <span class="relative inline-flex h-1.5 w-1.5 rounded-full {{ $t['dot'] }}"></span>
    </span>
    {{ $label }}
</span>
