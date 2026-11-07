import fs from 'node:fs'
import path from 'node:path'
import { execFileSync } from 'node:child_process'

const files = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard'], { encoding: 'utf8' })
  .trim().split(/\r?\n/).filter((file) => file.endsWith('.md'))
let links = 0
const errors = []
for (const file of files) {
  const body = fs.readFileSync(file, 'utf8')
  if ((body.match(/^```/gm) ?? []).length % 2) errors.push(`${file}: unbalanced code fences`)
  for (const match of body.matchAll(/!?\[[^\]]*\]\(([^)]+)\)/g)) {
    const target = match[1].split('#')[0]
    if (!target || /^[a-z]+:/i.test(target)) continue
    links++
    if (!fs.existsSync(path.resolve(path.dirname(file), decodeURIComponent(target)))) {
      errors.push(`${file}: missing local target ${target}`)
    }
  }
}
if (errors.length) {
  console.error(errors.join('\n'))
  process.exit(1)
}
console.log(`PASS: ${files.length} Markdown files, ${links} local links, balanced code fences`)
