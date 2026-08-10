import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // Fonts are bundled rather than inlined so the browser can cache them
        // across page loads instead of re-downloading them inside the CSS.
        assetsInlineLimit: 0,
    },
});
