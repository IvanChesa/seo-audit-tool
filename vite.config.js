import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

// https://laravel.com/docs/vite
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.jsx'],
            refresh: true,
        }),
        react(),
    ],
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{js,jsx}'],
        setupFiles: ['./resources/js/test/setup.js'],
        restoreMocks: true,
        css: false,
        // jsdom start-up on a cold cache (first run after npm ci) can take
        // several seconds under load; this only gives slow machines headroom.
        testTimeout: 15000,
    },
});
