import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                slab: ['"Roboto Slab"', 'serif'],
            },
            colors: {
                brand: {
                    navy: {
                        DEFAULT: '#131c2e',
                        from: '#10172a',
                        to: '#1b2340',
                    },
                    green: {
                        DEFAULT: '#0c8f0a',
                        hover: '#0a7a08',
                    },
                },
            },
            boxShadow: {
                badge: '0 4px 12px rgba(0, 0, 0, 0.3)',
                /* Not named `brand-green`: that collides with the `brand.green` color, and the
                   generated shadow-color utility would override the 0.35 alpha with solid green. */
                'brand-cta': '0 6px 16px rgba(12, 143, 10, 0.35)',
            },
        },
    },

    plugins: [forms],
};
