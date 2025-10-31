/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './src/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                // Package default colors - can be overridden by parent app
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};
