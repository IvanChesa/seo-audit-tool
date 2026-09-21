import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vite.dev/config/
export default defineConfig({
    plugins: [react()],
    server: {
        port: 5173,
        strictPort: true,
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./src/test/setup.js'],
        restoreMocks: true,
        css: false,
        // jsdom start-up on a cold cache (first run after npm ci) can take
        // several seconds under load; this only gives slow machines headroom.
        testTimeout: 15000,
    },
});
