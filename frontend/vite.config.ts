import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '127.0.0.1',
    port: Number(process.env.VITE_PORT ?? 5173),
    strictPort: true,
    proxy: Object.fromEntries(
      ['/api', '/sanctum'].map((path) => [
        path,
        {
          target: process.env.API_PROXY_TARGET ?? 'http://127.0.0.1:18000',
          changeOrigin: false,
        },
      ]),
    ),
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    clearMocks: true,
    pool: 'threads',
    maxWorkers: 1,
    fileParallelism: false,
    exclude: ['e2e/**', 'node_modules/**'],
  },
})
