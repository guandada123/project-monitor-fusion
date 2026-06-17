import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    // ApexCharts — used on most pages, split as dedicated chunk
                    'vendor-apex': ['apexcharts'],
                },
                // Group smaller shared dependencies into a common vendor chunk
                // when they appear in >2 chunks
                experimentalMinChunkSize: 8192,
            },
        },
        chunkSizeWarningLimit: 800,
    },
});
