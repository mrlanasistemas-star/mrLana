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
                // Función (no objeto): con la forma de objeto, Rollup metía en
                // "vendor-apex" módulos compartidos con la entrada y app.js lo
                // importaba de forma estática, así que ApexCharts (~590 kB) se
                // descargaba en TODAS las páginas. ApexCharts ya no se fuerza a
                // un chunk manual: queda en el chunk diferido del Dashboard.
                manualChunks(id) {
                    if (!id.includes('/node_modules/')) {
                        return undefined;
                    }
                    if (/\/node_modules\/(vue|@vue|@inertiajs)\//.test(id)) {
                        return 'vendor-vue';
                    }
                    if (id.includes('/node_modules/lucide-vue-next/')) {
                        return 'vendor-lucide';
                    }
                    if (id.includes('/node_modules/sweetalert2/')) {
                        return 'vendor-swal';
                    }
                    return undefined;
                },
            },
        },
    },
});
