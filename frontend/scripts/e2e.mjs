import { randomBytes } from 'node:crypto'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
const backend = fileURLToPath(new URL('../../backend/', import.meta.url))
const frontend = fileURLToPath(new URL('../', import.meta.url))
const env = {
  ...process.env,
  APP_ENV: 'testing',
  APP_DEBUG: 'false',
  DB_CONNECTION: 'mysql',
  DB_DATABASE: 'stockflow_test',
  DB_USERNAME: 'stockflow_test',
  DB_URL: '',
  SESSION_DRIVER: 'database',
  SESSION_COOKIE: 'stockflow-e2e',
  CACHE_STORE: 'array',
  APP_URL: 'http://127.0.0.1:5174',
  XDEBUG_MODE: 'off',
  DEMO_ENABLED: 'false',
  E2E_PASSWORD: randomBytes(24).toString('base64url'),
}
const prepare = spawnSync(
  process.env.PHP_BIN || 'php',
  ['-d', 'xdebug.log=', 'tests/Support/prepare-browser-demo.php'],
  { cwd: backend, env, stdio: 'inherit', windowsHide: true },
)
if (prepare.status !== 0) process.exit(prepare.status ?? 1)
const result = spawnSync(
  process.execPath,
  ['node_modules/@playwright/test/cli.js', 'test', ...process.argv.slice(2)],
  { cwd: frontend, env, stdio: 'inherit', windowsHide: true },
)
process.exit(result.status ?? 1)
