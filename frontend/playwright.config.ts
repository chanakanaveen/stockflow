import { defineConfig, devices } from '@playwright/test'
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  timeout: 180000,
  expect: { timeout: 20000 },
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:5174',
    trace: 'off',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } },
    },
  ],
  webServer: [
    {
      command:
        'php -d xdebug.log= artisan serve --env=testing --host=127.0.0.1 --port=18100 --no-reload',
      cwd: '../backend',
      url: 'http://127.0.0.1:18100/up',
      reuseExistingServer: false,
      timeout: 120000,
    },
    {
      command: 'npm run dev',
      url: 'http://127.0.0.1:5174',
      reuseExistingServer: false,
      timeout: 120000,
      env: { VITE_PORT: '5174', API_PROXY_TARGET: 'http://127.0.0.1:18100' },
    },
  ],
})
