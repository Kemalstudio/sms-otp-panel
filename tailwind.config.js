import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * One saturated brand colour and three unmistakable status colours, matching
 * the Android client — an operator switching between the phone and the panel
 * should not have to relearn what green means.
 */

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['ui-monospace', 'JetBrains Mono', 'SFMono-Regular', ...defaultTheme.fontFamily.mono],
            },

            colors: {
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    950: '#1e1b4b',
                },
                ink: {
                    50: '#f6f7fb',
                    100: '#eef0f6',
                    200: '#e6e9f0',
                    300: '#d5d9e4',
                    400: '#9aa3b8',
                    500: '#6b7488',
                    600: '#4b5366',
                    700: '#2c3446',
                    800: '#1c2230',
                    900: '#141926',
                    950: '#0b0f1a',
                },
            },

            boxShadow: {
                card: '0 1px 2px 0 rgb(16 24 40 / 0.04), 0 1px 3px 0 rgb(16 24 40 / 0.06)',
                lift: '0 12px 32px -12px rgb(49 46 129 / 0.35)',
            },

            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(6px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },

            animation: {
                'fade-up': 'fade-up 0.25s ease-out both',
            },
        },
    },

    plugins: [forms],
};
