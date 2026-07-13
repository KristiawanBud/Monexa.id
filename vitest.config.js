import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/*.spec.js'],
    },
})
