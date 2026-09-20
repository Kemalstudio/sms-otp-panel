<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SMS OTP Gateway') }}</title>

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
        <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-gradient-to-b from-brand-500/15 to-transparent"></div>

            <a href="{{ route('home') }}" class="relative flex items-center gap-2.5">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-white shadow-lift">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75h7.5A2.25 2.25 0 0 1 18 6v12a2.25 2.25 0 0 1-2.25 2.25h-7.5A2.25 2.25 0 0 1 6 18V6a2.25 2.25 0 0 1 2.25-2.25Zm2.25 13.5h3" />
                    </svg>
                </span>
                <span class="text-lg font-bold text-ink-900 dark:text-ink-50">SMS OTP Gateway</span>
            </a>

            <div class="card relative mt-8 w-full max-w-md p-8">
                {{ $slot }}
            </div>

            <p class="relative mt-6 text-xs text-ink-400 dark:text-ink-500">
                Коды отправляются с ваших телефонов — без операторских шлюзов.
            </p>
        </div>
    </body>
</html>
