<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'SMS OTP Gateway') }}</title>

        {{--
            Theme is resolved before the first paint. Doing it in Alpine instead
            would flash a white panel at anyone working in a dark room.
        --}}
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
        {{-- A single soft brand wash behind everything, so cards have something to sit on. --}}
        <div class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-80 bg-gradient-to-b from-brand-500/10 via-brand-500/[0.03] to-transparent dark:from-brand-500/15 dark:via-brand-500/5"></div>

        <div class="min-h-full">
            @include('layouts.navigation')

            @isset($header)
                <header class="border-b border-ink-200/70 bg-white/60 backdrop-blur dark:border-ink-800 dark:bg-ink-900/40">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="pb-16">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
