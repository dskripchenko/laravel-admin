// Fingerprints the sources of the prebuilt admin bundle (public/).
//
// The bundle is committed so hosts need no Node, and the lockfile is not, so
// CI cannot rebuild it byte for byte. What CI can check is that the bundle was
// rebuilt after its sources last changed: `npm run build:app` writes this
// fingerprint next to the bundle, and `npm run check:app` fails when the
// sources no longer match it.
import { createHash } from 'node:crypto'
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const target = join(root, 'public/source-hash.txt')

function walk(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return name === '__fixtures__' ? [] : walk(path)
    return /\.(test|spec|stories)\.ts$/.test(name) ? [] : [path]
  })
}

const pkg = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8'))
const hash = createHash('sha256')
for (const file of [...walk(join(root, 'resources/ts')), join(root, 'vite.app.config.ts')].sort()) {
  hash.update(relative(root, file).replaceAll('\\', '/'))
  hash.update(readFileSync(file).toString('utf8').replace(/\r\n/g, '\n'))
}
hash.update(JSON.stringify({ dependencies: pkg.dependencies, peer: pkg.peerDependencies, dev: pkg.devDependencies }))
const digest = hash.digest('hex')

if (process.argv.includes('--write')) {
  writeFileSync(target, digest + '\n')
  console.log(`public/source-hash.txt: ${digest}`)
} else {
  let stored = ''
  try {
    stored = readFileSync(target, 'utf8').trim()
  } catch {
    // no fingerprint yet
  }
  if (stored !== digest) {
    console.error('The prebuilt bundle in public/ is out of date with resources/ts. Run `npm run build:app` and commit public/.')
    process.exit(1)
  }
  console.log('The prebuilt bundle matches its sources.')
}
