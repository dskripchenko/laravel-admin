/**
 * The contract between core's templates and the @dskripchenko/ui components
 * they use.
 *
 * Vue silently drops a listener, a slot or a prop that a component does not
 * declare: the listener falls through to the root element as a DOM listener
 * that never fires, the slot is never rendered, and the code still compiles.
 * That is how EmbeddedResourceTable kept listening for `select-row` and filling
 * an `#actions` slot long after UidTable had replaced both.
 *
 * This test reads every core .vue template, finds each element that is a kit
 * component imported from '@dskripchenko/ui', and checks what it binds against
 * what the installed kit declares:
 *  - events: the component's runtime `emits`, or a native DOM event (which
 *    falls through to the root element on purpose);
 *  - slots: the `slots` the kit's generated .vue.d.ts declares;
 *  - props: the component's runtime `props`, or a plain HTML attribute
 *    (class, aria-*, data-*, title…) meant to fall through.
 *
 * A finding that is deliberate goes into ALLOWED below, with the reason.
 */
import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { parse } from 'vue/compiler-sfc'
import { NodeTypes, type ElementNode, type TemplateChildNode, type RootNode } from '@vue/compiler-core'
import * as kit from '@dskripchenko/ui'

const here = dirname(fileURLToPath(import.meta.url))
const coreRoot = resolve(here, '../..')
const kitDist = resolve(coreRoot, 'node_modules/@dskripchenko/ui/dist')

/** `file → [kind:Component:name]` entries that are deliberate. Keep it short. */
const ALLOWED: Record<string, string[]> = {
  // Known gaps in the kit, not in core: UidInput puts unknown attributes on its
  // wrapper <div>, not on the <input>, so `list` (the datalist of allowed keys)
  // and `maxlength` do nothing yet. The fix belongs in UidInput; drop these
  // entries once it forwards native input attributes.
  'resources/ts/components/fields/KeyValueField.vue': ['prop:UidInput:list'],
  'resources/ts/components/profile/TwoFactorSetup.vue': ['prop:UidInput:maxlength'],
}

/**
 * Kit components whose slot type takes any name. Their .d.ts cannot tell a
 * real slot from a stale one, so the fixed slots are listed here from the kit
 * source; every other slot is named after data (UidTable: a column key) and
 * must be bound by that data, `#[col.key]`, not by a literal name.
 */
const OPEN_SLOTS: Record<string, string[]> = {
  UidTable: ['empty'],
}

// ---------------------------------------------------------------------------
// What the kit declares
// ---------------------------------------------------------------------------

interface KitApi {
  props: Set<string>
  emits: Set<string>
  /** null: the kit declares no slot type for it (unknown); '*' in the set: any name. */
  slots: Set<string> | null
}

const camelize = (s: string): string => s.replace(/-(\w)/g, (_, c: string) => c.toUpperCase())
const hyphenate = (s: string): string => s.replace(/\B([A-Z])/g, '-$1').toLowerCase()

function walkFiles(dir: string, suffix: string, out: string[] = []): string[] {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name)
    if (statSync(p).isDirectory()) walkFiles(p, suffix, out)
    else if (name.endsWith(suffix)) out.push(p)
  }
  return out
}

const dtsByComponent = new Map<string, string>()
for (const file of walkFiles(kitDist, '.vue.d.ts')) {
  const name = file.slice(file.lastIndexOf('/') + 1, -'.vue.d.ts'.length)
  if (!dtsByComponent.has(name)) dtsByComponent.set(name, file)
}

/** The top-level member names of the first `slots: {…}` object in a .vue.d.ts. */
function slotsFromDts(source: string): Set<string> {
  const names = new Set<string>()
  const at = source.search(/\bslots: (Readonly<)?\{/)
  if (at < 0) return names
  let i = source.indexOf('{', at)
  let depth = 0
  let line = ''
  for (; i < source.length; i++) {
    const ch = source[i]
    if (ch === '{' || ch === '(' || ch === '<' || ch === '[') {
      if (depth === 1 && ch === '[') line += ch
      depth++
      if (depth === 1) continue
    } else if (ch === '}' || ch === ')' || ch === '>' || ch === ']') {
      if (ch === '>' && source[i - 1] === '=') continue // an arrow `=>`
      depth--
      if (depth === 0) break
    }
    if (depth === 1) {
      if (ch === '\n' || ch === ';') {
        const m = /^\s*(?:"([^"]+)"|'([^']+)'|([A-Za-z_$][\w$-]*)|(\[))\??\s*[(:]?/.exec(line)
        if (m) names.add(m[4] ? '*' : (m[1] ?? m[2] ?? m[3]))
        line = ''
      } else {
        line += ch
      }
    }
  }
  return names
}

function kitApi(name: string): KitApi | null {
  const comp = (kit as Record<string, unknown>)[name] as
    | { props?: string[] | Record<string, unknown>; emits?: string[] | Record<string, unknown> }
    | undefined
  if (!comp || typeof comp !== 'object' && typeof comp !== 'function') return null
  const keys = (v: string[] | Record<string, unknown> | undefined): string[] =>
    Array.isArray(v) ? v : v ? Object.keys(v) : []
  const dts = dtsByComponent.get(name)
  return {
    props: new Set(keys(comp.props).map(camelize)),
    emits: new Set(keys(comp.emits)),
    slots: dts ? slotsFromDts(readFileSync(dts, 'utf8')) : null,
  }
}

// ---------------------------------------------------------------------------
// What core binds
// ---------------------------------------------------------------------------

/** Attributes that are meant to fall through to the component's root element. */
const FALLTHROUGH_ATTRS = new Set([
  'class', 'style', 'id', 'role', 'title', 'tabindex', 'name', 'lang', 'dir', 'hidden',
  'draggable', 'for', 'href', 'target', 'rel', 'autocomplete', 'autofocus', 'inputmode',
  'key', 'ref', 'is',
])

function isFallthroughAttr(name: string): boolean {
  const n = name.toLowerCase()
  return FALLTHROUGH_ATTRS.has(n) || n.startsWith('aria-') || n.startsWith('data-')
}

function isNativeEvent(name: string): boolean {
  const n = name.toLowerCase()
  return `on${n}` in HTMLElement.prototype || `on${n}` in window || n.startsWith('drag') || n.startsWith('pointer')
}

interface Finding {
  file: string
  line: number
  text: string
  key: string
}

function importedKitNames(scripts: string): Set<string> {
  const names = new Set<string>()
  for (const m of scripts.matchAll(/import\s*\{([^}]*)\}\s*from\s*'@dskripchenko\/ui'/g)) {
    for (const part of m[1].split(',')) {
      const p = part.trim().replace(/^type\s+/, '')
      if (!p || part.trim().startsWith('type ')) continue
      const [orig, alias] = p.split(/\s+as\s+/)
      names.add(`${(alias ?? orig).trim()}=${orig.trim()}`)
    }
  }
  return names
}

function checkFile(file: string, src = readFileSync(file, 'utf8')): { findings: Finding[]; checked: number } {
  const { descriptor } = parse(src, { filename: file })
  const ast = descriptor.template?.ast as RootNode | undefined
  if (!ast) return { findings: [], checked: 0 }
  const scripts = (descriptor.script?.content ?? '') + (descriptor.scriptSetup?.content ?? '')
  const local = new Map<string, string>()
  for (const pair of importedKitNames(scripts)) {
    const [alias, orig] = pair.split('=')
    local.set(alias, orig)
    local.set(hyphenate(alias), orig)
  }
  const rel = relative(coreRoot, file)
  const findings: Finding[] = []
  let checked = 0

  const report = (el: ElementNode, kind: string, comp: string, name: string): void => {
    findings.push({
      file: rel,
      line: el.loc.start.line,
      text: `${kind} "${name}" is not declared by ${comp}`,
      key: `${kind}:${comp}:${name}`,
    })
  }

  const visit = (nodes: TemplateChildNode[]): void => {
    for (const node of nodes) {
      if (node.type !== NodeTypes.ELEMENT) {
        if (node.type === NodeTypes.IF) node.branches.forEach((b) => visit(b.children))
        if (node.type === NodeTypes.FOR) visit(node.children)
        continue
      }
      const el = node as ElementNode
      const comp = local.get(el.tag)
      const api = comp ? kitApi(comp) : null
      if (comp && api) {
        checked++
        for (const p of el.props) {
          if (p.type === NodeTypes.ATTRIBUTE) {
            if (!api.props.has(camelize(p.name)) && !isFallthroughAttr(p.name)) report(el, 'prop', comp, p.name)
            continue
          }
          const arg = p.arg && p.arg.type === NodeTypes.SIMPLE_EXPRESSION && p.arg.isStatic ? p.arg.content : null
          if (p.name === 'bind' && arg) {
            if (!api.props.has(camelize(arg)) && !isFallthroughAttr(arg)) report(el, 'prop', comp, arg)
          } else if (p.name === 'on' && arg) {
            const declared = api.emits.has(arg) || api.emits.has(camelize(arg)) || api.emits.has(hyphenate(arg))
            if (!declared && !isNativeEvent(arg)) report(el, 'event', comp, arg)
          } else if (p.name === 'model') {
            const model = camelize(arg ?? 'modelValue')
            if (!api.props.has(model)) report(el, 'prop', comp, model)
            if (!api.emits.has(`update:${model}`) && !api.emits.has(`update:${hyphenate(model)}`)) {
              report(el, 'event', comp, `update:${model}`)
            }
          } else if (p.name === 'slot') {
            checkSlot(el, comp, api, arg ?? 'default', p.arg !== undefined && arg === null)
          }
        }
        let defaultContent = false
        for (const child of el.children) {
          const slotDir = child.type === NodeTypes.ELEMENT && child.tag === 'template'
            ? child.props.find((p) => p.type === NodeTypes.DIRECTIVE && p.name === 'slot')
            : undefined
          if (slotDir && slotDir.type === NodeTypes.DIRECTIVE) {
            const a = slotDir.arg
            const dynamic = a !== undefined && !(a.type === NodeTypes.SIMPLE_EXPRESSION && a.isStatic)
            const name = a && a.type === NodeTypes.SIMPLE_EXPRESSION ? a.content : 'default'
            checkSlot(child as ElementNode, comp, api, name, dynamic)
          } else if (child.type !== NodeTypes.COMMENT && !(child.type === NodeTypes.TEXT && child.content.trim() === '')) {
            defaultContent = true
          }
        }
        if (defaultContent && !el.props.some((p) => p.type === NodeTypes.DIRECTIVE && p.name === 'slot')) {
          checkSlot(el, comp, api, 'default', false)
        }
      }
      visit(el.children)
    }
  }

  function checkSlot(el: ElementNode, comp: string, api: KitApi, name: string, dynamic: boolean): void {
    if (api.slots === null) return
    if (api.slots.has('*')) {
      const fixed = OPEN_SLOTS[comp]
      if (fixed && !dynamic && !fixed.includes(name)) report(el, 'slot', comp, name)
      return
    }
    if (dynamic) {
      // A computed slot name can only be right when the kit takes any name.
      report(el, 'slot', comp, '[dynamic]')
      return
    }
    if (!api.slots.has(name)) report(el, 'slot', comp, name)
  }

  visit(ast.children)
  return { findings, checked }
}

const vueFiles = walkFiles(resolve(coreRoot, 'resources/ts'), '.vue').filter((f) => !f.includes('/stories/'))

describe('core templates vs the @dskripchenko/ui component API', () => {
  it('reads the kit declarations it relies on', () => {
    const table = kitApi('UidTable')!
    expect(table.emits).toContain('update:selection')
    expect(table.emits).toContain('row-click')
    expect(table.props).toContain('selection')
    expect(table.slots).toContain('*')
    const button = kitApi('UidButton')!
    expect([...button.slots!].sort()).toEqual(['append', 'default', 'prepend'])
    expect(kitApi('UidSkeleton')!.slots!.size).toBe(0)
  })

  it('binds only the events, slots and props the kit components declare', () => {
    const all: Finding[] = []
    let checked = 0
    for (const file of vueFiles) {
      const r = checkFile(file)
      checked += r.checked
      all.push(...r.findings)
    }
    expect(checked).toBeGreaterThan(200)
    const drift = all.filter((f) => !(ALLOWED[f.file] ?? []).includes(f.key))
    expect(drift.map((f) => `${f.file}:${f.line} ${f.text}`)).toEqual([])
  })

  it('fails on the drift it was written for', () => {
    // EmbeddedResourceTable before the fix: UidTable's pre-1.0 selection events
    // and an #actions slot that UidTable never renders.
    const stale = `<script setup lang="ts">
import { UidTable, UidAlert, UidProgress } from '@dskripchenko/ui'
</script>
<template>
  <UidTable :columns="c" :data="d" selectable @select-row="a" @select-all="b" @update:selection="s" @click="x">
    <template v-for="col in c" :key="col.key" #[col.key]="p">{{ p }}</template>
    <template #actions="p">{{ p }}</template>
    <template #empty>none</template>
  </UidTable>
  <UidAlert><template #title>t</template>body</UidAlert>
  <UidProgress :model-value="1" data-x="1" class="k" />
</template>`
    const { findings } = checkFile(join(coreRoot, 'resources/ts/Stale.vue'), stale)
    expect(findings.map((f) => f.key).sort()).toEqual([
      'event:UidTable:select-all',
      'event:UidTable:select-row',
      'prop:UidProgress:model-value',
      'slot:UidAlert:title',
      'slot:UidTable:actions',
    ])
  })
})
