import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // CSS global + per halaman NutriTumbuh
                'resources/css/globals.css',
                'resources/css/styleguide.css',
                'resources/css/admin-referensi.css',
                'resources/css/daftar.css',
                'resources/css/data-dirianak.css',
                'resources/css/data-kader.css',
                'resources/css/data-ortu.css',
                'resources/css/data-pertumbuhan.css',
                'resources/css/db-kader.css',
                'resources/css/db-ortu.css',
                'resources/css/history-ortu.css',
                'resources/css/input-ortu.css',
                'resources/css/kebijakan.css',
                'resources/css/login.css',
                'resources/css/perhatian-kader.css',
                'resources/css/resep-detail.css',
                'resources/css/review-kader.css',
                'resources/css/review-ortu.css',
                'resources/css/riwayat-kader.css',
                'resources/css/ukur-kader.css',
                // JS per halaman (hanya yang ada file-nya)
                'resources/js/api.js',
                'resources/js/admin-referensi.js',
                'resources/js/db-kader.js',
                'resources/js/db-ortu.js',
                'resources/js/history-ortu.js',
                'resources/js/input-ortu.js',
                'resources/js/perhatian-kader.js',
                'resources/js/resep-detail.js',
                'resources/js/riwayat-kader.js',
                'resources/js/ukur-kader.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
