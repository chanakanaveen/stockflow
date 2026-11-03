import fs from 'node:fs'
import { execFileSync } from 'node:child_process'
const doc = JSON.parse(fs.readFileSync('docs/openapi.json', 'utf8'))
const failures = []
const ids = new Set()
function walk(node) {
  if (!node || typeof node !== 'object') return
  if (node.$ref?.startsWith('#/')) {
    let value = doc
    for (const key of node.$ref.slice(2).split('/')) value = value?.[key]
    if (!value) failures.push('Unresolved reference: ' + node.$ref)
  }
  for (const value of Object.values(node)) walk(value)
}
walk(doc)
const normalize = path => path.replace(/\{[^}]+\}/g, '{}')
const documented = new Set()
for (const [path, item] of Object.entries(doc.paths)) for (const [method, op] of Object.entries(item)) {
  if (!['get','post','patch','put','delete'].includes(method)) continue
  if (!op.operationId || ids.has(op.operationId)) failures.push('Missing/duplicate operation ID: ' + path)
  ids.add(op.operationId)
  if (!op.responses) failures.push('Missing responses: ' + path)
  documented.add(method.toUpperCase() + ' ' + normalize(path === '/sanctum/csrf-cookie' ? path : '/api/v1' + path))
}
const output = execFileSync(process.env.PHP_BIN || 'php', ['-d','xdebug.log=','artisan','route:list','--json'], {cwd:'backend', encoding:'utf8', env:{...process.env,XDEBUG_MODE:'off'}})
const routes = JSON.parse(output)
const actual = new Set(routes.filter(r=>r.uri.startsWith('api/v1/') || r.uri === 'sanctum/csrf-cookie').flatMap(r=>r.method.split('|').filter(m=>m!=='HEAD').map(m=>m+' '+normalize('/'+r.uri))))
for (const route of actual) if (!documented.has(route)) failures.push('Undocumented route: '+route)
for (const route of documented) if (!actual.has(route)) failures.push('Unimplemented route: '+route)
if(failures.length) { console.error(failures.join('\n')); process.exit(1) }
console.log('OpenAPI: '+actual.size+' registered operations match; references and operation IDs valid.')
