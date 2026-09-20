@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-xl border-ink-300 bg-white text-sm text-ink-900 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100 dark:placeholder:text-ink-600 dark:focus:border-brand-400 dark:focus:ring-brand-400']) }}>
