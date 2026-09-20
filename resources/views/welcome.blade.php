@php
    $baseUrl = rtrim(config('app.url'), '/');

    $snippet = <<<CODE
curl -X POST {$baseUrl}/api/v1/otp/send \
  -H 'X-Api-Key: sk_live_…' \
  -H 'Content-Type: application/json' \
  -d '{"phone": "+99365123456"}'

# 202 Accepted
# {"otp_id": 42, "status": "pending"}
CODE;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — OTP через собственные телефоны</title>

    <script>
        (() => {
            const stored = localStorage.getItem('theme');
            const dark = stored
                ? stored === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;

            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans">
    <div class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[32rem] bg-gradient-to-b from-brand-500/15 via-brand-500/5 to-transparent"></div>

    <div class="flex min-h-full flex-col">
        <header class="border-b border-ink-200/70 dark:border-ink-800">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-white shadow-lift">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75h7.5A2.25 2.25 0 0 1 18 6v12a2.25 2.25 0 0 1-2.25 2.25h-7.5A2.25 2.25 0 0 1 6 18V6a2.25 2.25 0 0 1 2.25-2.25Zm2.25 13.5h3" />
                        </svg>
                    </span>
                    <span class="font-bold text-ink-900 dark:text-ink-50">SMS OTP Gateway</span>
                </a>

                <nav class="flex items-center gap-2 text-sm">
                    <x-theme-toggle />

                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="rounded-xl bg-brand-600 px-4 py-2.5 font-semibold text-white transition hover:bg-brand-500">
                            В панель
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="rounded-xl px-4 py-2.5 font-semibold text-ink-600 transition hover:text-ink-900 dark:text-ink-300 dark:hover:text-white">
                            Войти
                        </a>
                        <a href="{{ route('register') }}"
                           class="rounded-xl bg-brand-600 px-4 py-2.5 font-semibold text-white transition hover:bg-brand-500">
                            Начать
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <section class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-20 lg:grid-cols-2">
                <div>
                    <p class="label-caps">SMS OTP Gateway</p>
                    <h1 class="mt-3 text-4xl font-bold leading-tight tracking-tight text-ink-900 dark:text-ink-50 sm:text-5xl">
                        Одноразовые коды —<br>с ваших собственных телефонов
                    </h1>
                    <p class="mt-6 max-w-xl text-lg text-ink-500 dark:text-ink-400">
                        Вы ставите Android-приложение на телефон с обычной SIM-картой, привязываете
                        его к панели по QR-коду — и ваш бэкенд отправляет коды одним HTTP-запросом.
                        Без договоров с операторами и платы за каждое сообщение.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="{{ route('register') }}"
                           class="rounded-xl bg-brand-600 px-6 py-3 font-semibold text-white shadow-lift transition hover:bg-brand-500">
                            Создать аккаунт
                        </a>
                        <a href="{{ route('login') }}"
                           class="rounded-xl border border-ink-300 px-6 py-3 font-semibold text-ink-700 transition hover:bg-white dark:border-ink-700 dark:text-ink-200 dark:hover:bg-ink-900">
                            Войти
                        </a>
                    </div>

                    <p class="mt-4 text-sm text-ink-400 dark:text-ink-500">
                        Код живёт 5 минут · 5 попыток ввода · лимиты на номер и на ключ
                    </p>
                </div>

                <div class="overflow-hidden rounded-2xl ring-1 ring-ink-900/10 dark:ring-white/10">
                    <div class="flex items-center gap-1.5 bg-ink-900 px-4 py-3">
                        <span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-2 font-mono text-xs text-ink-400">отправить код</span>
                    </div>
                    <pre class="overflow-x-auto bg-ink-950 p-5 font-mono text-[13px] leading-relaxed text-ink-100"><code>{{ $snippet }}</code></pre>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-6 pb-8">
                <h2 class="text-center text-2xl font-bold text-ink-900 dark:text-ink-50">Как это работает</h2>

                <div class="mt-10 grid gap-6 sm:grid-cols-3">
                    @foreach ([
                        ['1', 'Подключите телефон', 'Приложение сканирует QR-код из панели и встаёт в пул устройств. Оно же шлёт heartbeat, по которому видно, что телефон жив.'],
                        ['2', 'Выпустите API-ключ', 'Ключ показывается один раз, в базе остаётся только sha256-хеш. Лимиты считаются по ключу и по номеру.'],
                        ['3', 'Вызовите один эндпоинт', 'POST /otp/send возвращает otp_id, POST /otp/verify проверяет введённые цифры. Остальное шлюз делает сам.'],
                    ] as [$n, $title, $text])
                        <div class="card p-6">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-sm font-bold text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                                {{ $n }}
                            </span>
                            <h3 class="mt-4 font-semibold text-ink-900 dark:text-ink-50">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-6 py-16">
                <div class="grid gap-6 sm:grid-cols-3">
                    @foreach ([
                        ['Несколько телефонов', 'Коды расходятся по устройствам round-robin. Один телефон отвалился — заказ уходит на следующий.'],
                        ['Честные статусы', 'Телефон отчитывается о фактическом результате отправки и досылает статус, если пропадала связь.'],
                        ['Логи и маскировка', 'История каждого кода со статусом и сроком жизни. Номера в списке замаскированы.'],
                    ] as [$title, $text])
                        <div class="rounded-2xl border border-ink-200 p-6 dark:border-ink-800">
                            <h3 class="font-semibold text-ink-900 dark:text-ink-50">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>

        <footer class="border-t border-ink-200/70 py-6 text-center text-sm text-ink-400 dark:border-ink-800 dark:text-ink-500">
            {{ config('app.name') }} · {{ now()->year }}
        </footer>
    </div>
</body>
</html>
