import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js',
                'resources/css/components/form-selects.css', 'resources/js/components/form-selects.js'],
            refresh: true,
        }),
    ],
});
