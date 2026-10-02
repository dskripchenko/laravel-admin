/**
 * Every UI-kit token the admin's styles read must exist in the kit. A name
 * the kit does not define falls back to the literal after the comma — a light
 * colour, typically — so the dark theme showed light patches (the
 * notifications page's active tab: white text on #f3f4f6) and spacing used
 * whatever the fallback said.
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, resolve } from 'node:path'

const root = resolve(__dirname, '..')
const kitCss = resolve(__dirname, '../../../node_modules/@dskripchenko/ui/dist/styles/all.css')

/** Read by the admin, defined by the host or set at runtime, or optional hooks. */
const ALLOWED = new Set([
  '--uid-font-family-display',
  '--uid-surface-sunken',
  '--uid-sidebar-item-color',
  // Stacking levels newer kits define; read with a fallback until then.
  '--uid-z-drawer',
  '--uid-z-popover',
])

function files(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return files(path)
    return /\.(vue|css)$/.test(name) ? [path] : []
  })
}

describe('UI-kit tokens used by the admin', () => {
  it('are all defined by the kit or the admin itself', () => {
    const sources = files(root).map((f) => readFileSync(f, 'utf8'))
    const defined = new Set<string>()
    for (const css of [readFileSync(kitCss, 'utf8'), ...sources]) {
      for (const m of css.matchAll(/(--uid-[a-z0-9-]+)\s*:/g)) defined.add(m[1]!)
    }
    const missing = new Set<string>()
    for (const css of sources) {
      for (const m of css.matchAll(/var\((--uid-[a-z0-9-]+)/g)) {
        if (!defined.has(m[1]!) && !ALLOWED.has(m[1]!)) missing.add(m[1]!)
      }
    }
    expect([...missing].sort()).toEqual([])
  })
})
