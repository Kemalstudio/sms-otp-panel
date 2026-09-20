@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-ink-700 dark:text-ink-300']) }}>
    {{ $value ?? $slot }}
</label>
