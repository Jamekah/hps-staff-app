import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * "Modernist" design system — ported from the Claude Design project.
 * Ink-dominant palette on a warm off-white ground, brand red accent,
 * Archivo type, and square corners throughout (radius 0).
 */

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Archivo', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                // Grounds
                paper: '#f3f2f2',
                surface: '#eae9e9',

                // Ink — text colour and the neutral ramp built from it
                ink: {
                    DEFAULT: '#201e1d',
                    100: '#f8f4f4',
                    200: '#eae7e7',
                    300: '#d7d3d3',
                    400: '#bab6b6',
                    500: '#9b9797',
                    600: '#7d7979',
                    700: '#605d5d',
                    800: '#444141',
                    900: '#2d2b2b',
                },

                // Brand red — the single accent
                accent: {
                    DEFAULT: '#ec3013',
                    100: '#fff2ef',
                    200: '#ffe0d9',
                    300: '#ffc4b8',
                    400: '#ff9783',
                    500: '#ff563c',
                    600: '#dd2b0f',
                    700: '#ae1800',
                    800: '#7c1405',
                    900: '#4d170e',
                },

                // Studio colours — deliberately blue/green so they never collide
                // with the calendar's ink/red event vocabulary.
                studio1: {
                    DEFAULT: '#2f5e89',
                    tint: '#edf3f9',
                    dark: '#23466a',
                    mid: '#3c5c78',
                    muted: '#5e6e7d',
                },
                studio2: {
                    DEFAULT: '#216b52',
                    tint: '#eaf6f0',
                    dark: '#14503b',
                    mid: '#33604f',
                    muted: '#5b7a6e',
                },
            },

            // Square corners are core to the look. `full` is kept for the few
            // genuinely circular elements (unread dots, avatars).
            borderRadius: {
                none: '0',
                sm: '0',
                DEFAULT: '0',
                md: '0',
                lg: '0',
                xl: '0',
                '2xl': '0',
                '3xl': '0',
                full: '9999px',
            },

            letterSpacing: {
                label: '0.1em',
                tight: '-0.02em',
            },

            boxShadow: {
                sm: '0 1px 2px rgb(45 43 43 / 0.14)',
                DEFAULT: '0 3px 10px rgb(45 43 43 / 0.16)',
                md: '0 3px 10px rgb(45 43 43 / 0.16)',
                lg: '0 12px 32px rgb(45 43 43 / 0.22)',
            },
        },
    },

    plugins: [forms],
};
