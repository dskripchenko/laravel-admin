/**
 * The panel's own captions are Russian source strings passed through tr(),
 * translated per locale. An English word typed straight into a template
 * ("Filter", "Limit") shows in English in every language; this guard finds
 * one in any component's template.
 */
import { describe, expect, it } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

const ROOT = join(__dirname)

function vueFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return vueFiles(path)
    return name.endsWith('.vue') ? [path] : []
  })
}

function template(source: string): string {
  const start = source.indexOf('<template>')
  const end = source.lastIndexOf('</template>')
  return start === -1 || end === -1 ? '' : source.slice(start, end)
}

describe('component templates', () => {
  it('carry no hardcoded English captions', () => {
    const offenders: string[] = []
    for (const file of vueFiles(ROOT)) {
      const tpl = template(readFileSync(file, 'utf8'))
      // A text node of English words between two tags: >Filter<, >Resource slug<.
      for (const m of tpl.matchAll(/>\s*([A-Z][a-z]+(?: [a-z]+)*)\s*</g)) {
        offenders.push(`${relative(ROOT, file)}: ${m[1]}`)
      }
    }
    expect(offenders).toEqual([])
  })
})
