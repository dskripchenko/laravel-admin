/**
 * Every type the backend can send has a component on this side.
 *
 * backend-types.json is written by `composer types:export` from the PHP
 * classes (tests/Support/BackendTypes.php) and kept honest by a PHP test. Here
 * each type is looked up in the SPA registries. A type the SPA does not draw
 * yet — or draws with a stand-in, like markdown in a plain textarea — must be
 * listed in GAPS. The list only shrinks: closing a gap without removing it
 * from GAPS fails too, so the list always tells the truth.
 */
import { describe, expect, it, beforeAll } from 'vitest'
import type { Component } from 'vue'
import backendTypes from '../../__fixtures__/backend-types.json'
import { registerBuiltinComponents } from './builtin'
import { getField, getLayout, hasField, hasLayout } from './registry'
import { registerBuiltinWidgets } from '../dashboard/builtin'
import { getWidget, hasWidget } from '../dashboard/registry'
import { registerBuiltinInfolistEntries } from '../infolist/builtin'
import { getInfolistEntry, hasInfolistEntry } from '../infolist/registry'
import { CHART_RENDERERS, type ChartRenderer } from '../dashboard/chartTypes'

/** `null` — no component at all; a component — the stand-in drawing it today. */
type Gap = Component | null

const GAPS: Record<'fields' | 'layouts' | 'widgets' | 'entries', Record<string, Gap>> = {
  fields: {},
  layouts: {},
  widgets: {},
  entries: {},
}

/** Chart types drawn by a renderer of another kind (a line as bars). */
const CHART_GAPS: Record<string, ChartRenderer | null> = {}

const lookups = {
  fields: { has: hasField, get: getField },
  layouts: { has: hasLayout, get: getLayout },
  widgets: { has: hasWidget, get: getWidget },
  entries: { has: hasInfolistEntry, get: getInfolistEntry },
} as const

beforeAll(() => {
  registerBuiltinComponents()
  registerBuiltinWidgets()
  registerBuiltinInfolistEntries()
})

describe('backend ↔ SPA parity', () => {
  for (const kind of Object.keys(lookups) as Array<keyof typeof lookups>) {
    const types = (backendTypes as Record<string, string[]>)[kind]

    it(`every backend ${kind} type has a component`, () => {
      const unhandled = types.filter((t) => !(t in GAPS[kind]) && !lookups[kind].has(t))
      expect(unhandled, `register a component or list it in GAPS.${kind}`).toEqual([])
    })

    it(`GAPS.${kind} lists only gaps that are still open`, () => {
      const closed = Object.entries(GAPS[kind])
        .filter(([t, standIn]) => {
          const { has, get } = lookups[kind]
          return standIn === null ? has(t) : !has(t) || get(t) !== standIn
        })
        .map(([t]) => t)
      const unknown = Object.keys(GAPS[kind]).filter((t) => !types.includes(t))
      expect(closed, `remove from GAPS.${kind}: the SPA draws them now`).toEqual([])
      expect(unknown, `remove from GAPS.${kind}: the backend no longer has them`).toEqual([])
    })
  }

  it('every backend chart type has a renderer', () => {
    const unhandled = backendTypes.charts.filter((t) => !(t in CHART_GAPS) && CHART_RENDERERS[t] === undefined)
    expect(unhandled).toEqual([])
  })

  it('CHART_GAPS lists only gaps that are still open', () => {
    const closed = Object.entries(CHART_GAPS)
      .filter(([t, standIn]) => (CHART_RENDERERS[t] ?? null) !== standIn)
      .map(([t]) => t)
    expect(closed, 'remove from CHART_GAPS').toEqual([])
  })
})
