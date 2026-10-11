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
                'ink-soft': '#4F5968',
                'ink-faint': '#5A6472',
                line: '#E7E0CC',
                'line-card': '#ECEEF2',
                orange: '#F2A93B',
                mandarin: '#35619E',
                'mandarin-soft': '#E3EDF9',
                english: '#3D6B52',
                'english-soft': '#E4F1E8',
                good: '#3D6B52',
            },
        },
    },

    plugins: [forms],
};
