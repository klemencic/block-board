/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// V Dockerju je API dosegljiv kot http://api:8000, lokalno na localhost:8000.
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8000'

const proxied = ['/api', '/login', '/logout', '/sanctum']

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    watch: { usePolling: true },
    proxy: Object.fromEntries(proxied.map((path) => [path, { target: apiTarget }])),
  },
  test: {
    environment: 'jsdom',
  },
})
