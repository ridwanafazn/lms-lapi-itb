import defaultTheme from 'tailwindcss/defaultTheme';
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

            colors: {
                'accent-contrast': '#E20E0E',
                'accent-pastel': '#F18787',
                'accent-light': '#F8C3C3',
                'text-blue': '#3F4494',
                'text-pastel': '#2D64AC',
                'text-gray': '#545454',
                'primary': '#137FEC',
                'secondary1': '#DBEAFE',
                'secondary2': '#E7F4FD',
                'secondary3': '#F4F9FE',
                'gray1': '#858585',
                'gray2': '#9FA2CA',
                'gray3': '#CFD1E5',
                'gray-stroke': '#E4EFF2',
                'gray-bg': '#F6F7F8',


            },

            fontFamily: {
                heading: ['"Atkinson Hyperlegible"', 'sans-serif'],
                body: ['Inter', 'sans-serif'],
                caption: ['"Work Sans"', 'sans-serif'],
            },

            // 1. DAFTARKAN KEYFRAMES (Gerakannya)
            keyframes: {
                marquee: {
                    '0%': { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-100%)' },
                },
                marqueeReverse: {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(0)' },
                },
            },

            // 2. DAFTARKAN ANIMATION CLASS (Durasinya)
            animation: {
                // 40s adalah durasi satu putaran (semakin besar semakin pelan)
                'marquee': 'marquee 40s linear infinite',
                'marquee-reverse': 'marqueeReverse 40s linear infinite',
            },
        },
    },

    plugins: [forms],
};
