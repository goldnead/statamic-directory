import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import statamic from '@statamic/cms/vite-plugin';

export default defineConfig({
    plugins: [
        // Externalises `vue` to the CP runtime; without it the bundle ships a second Vue.
        statamic(),
        tailwindcss(),
        laravel({
            input: ['resources/js/cp.js', 'resources/css/cp.css'],
            // Must byte-match $vite in ServiceProvider.
            publicDirectory: 'resources/dist',
        }),
    ],
});
