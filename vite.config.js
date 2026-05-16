import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.scss',
                'resources/views/auth/login.css',
                'resources/js/admin/app.js',
            ],
            refresh: true,
        }),
        vue(),
    ],
})