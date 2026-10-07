import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    // Pre-empaqueta al arrancar las dependencias que solo cargan páginas
    // diferidas (dashboards, catálogos). Sin esto, Vite las descubre tarde,
    // re-optimiza y el navegador recibe "504 Outdated Optimize Dep".
    optimizeDeps: {
        include: [
            'apexcharts',
            'vue3-apexcharts',
            'sweetalert2',
            'reka-ui',
            'lucide-vue-next',
            '@tabler/icons-vue',
            'axios',
            'clsx',
            'tailwind-merge',
        ],
    },
    build: {
        chunkSizeWarningLimit: 700,
        rollupOptions: {
            output: {
                manualChunks: {
                    // Separa ApexCharts en su propio chunk (lazy load)
                    'vendor-apex': ['apexcharts', 'vue3-apexcharts'],
                    // Vue y Inertia en un chunk compartido
                    'vendor-vue': ['vue', '@inertiajs/vue3'],
                    // Lucide icons
                    'vendor-lucide': ['lucide-vue-next'],
                    // SweetAlert2
                    'vendor-swal': ['sweetalert2'],
                },
            },
        },
    },
});
