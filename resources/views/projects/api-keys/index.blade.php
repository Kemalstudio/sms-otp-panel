@php
    $baseUrl = rtrim(config('app.url'), '/');
    $newKey = session('new_api_key');

    if ($newKey) {
        $curlSnippet = <<<CODE
curl -X POST {$baseUrl}/api/v1/otp/send \
  -H 'X-Api-Key: {$newKey}' \
  -H 'Content-Type: application/json' \
  -d '{"phone": "+99365123456"}'
CODE;
    }
@endphp

<x-app-layout>
    <x-slot name="title">{{ $project->name }} — API-ключи</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="label-caps">Проект</p>
                <h1 class="truncate text-2xl font-bold text-ink-900 dark:text-ink-50">{{ $project->name }}</h1>
            </div>

            <form method="POST" action="{{ route('projects.api-keys.store', $project) }}">
                @csrf
                <x-primary-button>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                    Создать ключ
                </x-primary-button>
            </form>
        </div>

        <div class="mt-5">
            <x-project-nav :project="$project" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-flash>{{ session('status') }}</x-flash>
        @endif

        @if ($apiKeys->where('status', 'active')->isEmpty())
            <x-flash tone="warning">
                Активных ключей нет — вашему бэкенду нечем авторизоваться, и
                <code class="font-mono">/api/v1/otp/send</code> будет отвечать
                <code class="font-mono">401</code>. Создайте ключ.
            </x-flash>
        @endif

        <section class="card-flush">
            <div class="card-header">
                <div>
                    <h3 class="section-title">API-ключи</h3>
                    <p class="section-hint">
                        В базе лежит только sha256-хеш. Полное значение показывается один раз — при создании.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                    <thead class="table-head">
                        <tr>
                            <th class="px-6 py-3">Ключ</th>
                            <th class="px-6 py-3">Создан</th>
                            <th class="px-6 py-3">Последнее использование</th>
                            <th class="px-6 py-3">Статус</th>
                            <th class="px-6 py-3 text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @forelse ($apiKeys as $apiKey)
                            <tr @class(['table-row', 'opacity-60' => $apiKey->isRevoked()])>
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-ink-900 dark:text-ink-50">
                                    {{ $apiKey->key_prefix }}<span class="text-ink-400">…</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 tabular-nums" title="{{ $apiKey->created_at->format('d.m.Y H:i') }}">
                                    {{ $apiKey->created_at->format('d.m.Y') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($apiKey->last_used_at)
                                        <span title="{{ $apiKey->last_used_at->format('d.m.Y H:i:s') }}">
                                            {{ $apiKey->last_used_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-ink-400 dark:text-ink-500">не использовался</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <x-status-badge :status="$apiKey->status" subject="key" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    @unless ($apiKey->isRevoked())
                                        <form method="POST"
                                              action="{{ route('projects.api-keys.destroy', [$project, $apiKey]) }}"
                                              onsubmit="return confirm('Отозвать ключ {{ $apiKey->key_prefix }}…? Все запросы с ним сразу начнут получать 401.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-sm font-semibold text-rose-600 transition hover:text-rose-500 dark:text-rose-400">
                                                Отозвать
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-ink-400 dark:text-ink-500">
                                            отозван {{ $apiKey->revoked_at->format('d.m.Y') }}
                                        </span>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state title="Ключей пока нет"
                                                   description="Ключ — это то, чем ваш бэкенд представляется шлюзу в заголовке X-Api-Key."
                                                   icon="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z">
                                        <form method="POST" action="{{ route('projects.api-keys.store', $project) }}">
                                            @csrf
                                            <x-primary-button>Создать первый ключ</x-primary-button>
                                        </form>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card p-6">
            <h3 class="section-title">Как это работает</h3>
            <ul class="mt-3 space-y-2 text-sm text-ink-500 dark:text-ink-400">
                <li class="flex gap-2">
                    <span class="text-brand-500">→</span>
                    Ключ передаётся в заголовке <code class="font-mono text-ink-700 dark:text-ink-200">X-Api-Key</code> и
                    привязан к этому проекту: чужие устройства и логи он не видит.
                </li>
                <li class="flex gap-2">
                    <span class="text-brand-500">→</span>
                    Лимиты считаются по ключу: 20 запросов в минуту, плюс не чаще одного кода в минуту на один номер.
                </li>
                <li class="flex gap-2">
                    <span class="text-brand-500">→</span>
                    Отзыв мгновенный и необратимый. Утёк ключ — отзывайте и выпускайте новый,
                    старый начнёт получать <code class="font-mono text-ink-700 dark:text-ink-200">401</code>.
                </li>
            </ul>
        </section>
    </div>

    {{-- Единственный момент, когда ключ существует в открытом виде. --}}
    <x-modal name="new-api-key" :show="(bool) $newKey" focusable maxWidth="2xl">
        @if ($newKey)
            <div class="p-6">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">
                    Ключ создан
                </h2>

                <x-flash tone="warning" class="mt-4">
                    Сохраните его сейчас — больше он нигде не появится. В базе остаётся только sha256-хеш.
                </x-flash>

                <x-copy-field :value="$newKey" label="API-ключ" class="mt-4" />

                <p class="mt-6 text-sm font-medium text-ink-900 dark:text-ink-100">Проверьте одной командой</p>
                <x-code-block class="mt-2" :snippets="[['label' => 'cURL', 'code' => $curlSnippet]]" />

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    {{--
                        Ключ едет в «Обзор» через sessionStorage вкладки, а не через
                        сессию на сервере: открытое значение должно показаться ровно
                        один раз и не воскресать на следующей странице.
                    --}}
                    <a href="{{ route('projects.show', $project) }}"
                       x-on:click="sessionStorage.setItem('otp_test_key', @js($newKey))"
                       class="inline-flex items-center gap-2 rounded-xl border border-ink-300 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700 shadow-sm transition hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200 dark:hover:bg-ink-800">
                        Проверить в панели
                    </a>
                    <x-primary-button x-on:click="$dispatch('close')">Я сохранил ключ</x-primary-button>
                </div>
            </div>
        @endif
    </x-modal>
</x-app-layout>
