import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            screens: {
                sidebar: '861px',
            },
            fontFamily: {
                display: ['Fredoka', 'sans-serif'],
                sans: ['Nunito', 'sans-serif'],
                hand: ['Caveat', 'cursive'],
            },
            colors: {
                navy: '#1B3A5C',
                'navy-deep': '#132C46',
                'navy-soft': '#2C4F73',
                cream: '#F6EFE0',
                card: '#FFFFFF',
                'card-alt': '#FBF8EF',
                ink: '#2B3648',
                'ink-soft': '#6B7686',
                'ink-faint': '#98A2B0',
                line: '#E7E0CC',
                'line-card': '#ECEEF2',
                orange: '#F2A93B',
                mandarin: '#3D6FB4',
                'mandarin-soft': '#E3EDF9',
                english: '#4F8768',
                'english-soft': '#E4F1E8',
                good: '#4F8768',
            },
        },
    },

    plugins: [forms],
};
