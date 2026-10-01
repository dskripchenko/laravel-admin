/**
 * The pieces of the Wizard layout that are not about rendering: finding the
 * fields of a step, checking them against the rules before moving on, and
 * keeping the progress in localStorage.
 *
 * The client-side check is a convenience, not the authority: it knows the
 * common Laravel rules (required, email, numeric, integer, min, max, in) and
 * lets everything else through to the server, which validates again on submit.
 */

import { tRaw } from '../../stores/i18n'

export interface WizardField {
  name: string
  type: string
  label: string
  rules: string[]
}

interface AnyNode {
  kind?: string
  type?: string
  name?: unknown
  label?: unknown
  required?: unknown
  rules?: unknown
  items?: unknown
  children?: unknown
  sections?: unknown
}

function asNodes(value: unknown): AnyNode[] {
  return Array.isArray(value) ? value.filter((v): v is AnyNode => typeof v === 'object' && v !== null) : []
}

/** Normalizes a rules value — a list, a pipe string or nothing — to a flat list. */
export function normalizeRules(value: unknown): string[] {
  const list = Array.isArray(value) ? value : typeof value === 'string' ? [value] : []
  return list
    .filter((r): r is string => typeof r === 'string')
    .flatMap((r) => r.split('|'))
    .map((r) => r.trim())
    .filter((r) => r !== '')
}

/**
 * The fields of a subtree, in order. A field's own nested fields (a repeater's
 * rows) are its business, so the walk stops at a field.
 */
export function collectFields(nodes: unknown): WizardField[] {
  const out: WizardField[] = []

  const walk = (list: AnyNode[]): void => {
    for (const node of list) {
      if (node.kind === 'field' || (node.kind === undefined && typeof node.name === 'string' && node.items === undefined)) {
        if (typeof node.name !== 'string' || node.name === '') continue
        const rules = normalizeRules(node.rules)
        if (node.required === true && !rules.includes('required')) rules.unshift('required')
        out.push({
          name: node.name,
          type: typeof node.type === 'string' ? node.type : '',
          label: typeof node.label === 'string' && node.label !== '' ? node.label : node.name,
          rules,
        })
        continue
      }
      walk(asNodes(node.items ?? node.children))
      for (const section of asNodes(node.sections)) {
        walk(asNodes(section.children ?? section.items))
      }
    }
  }

  walk(asNodes(nodes))
  return out
}

function isEmpty(value: unknown): boolean {
  if (value === null || value === undefined) return true
  if (typeof value === 'string') return value.trim() === ''
  if (Array.isArray(value)) return value.length === 0
  return false
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function sizeOf(value: unknown, numeric: boolean): number | null {
  if (numeric || typeof value === 'number') {
    const n = Number(value)
    return Number.isFinite(n) ? n : null
  }
  if (typeof value === 'string' || Array.isArray(value)) return value.length
  return null
}

/** The messages for one value against its rules; an empty list means valid. */
export function validateValue(value: unknown, rules: string[], label: string): string[] {
  const required = rules.includes('required')
  if (isEmpty(value)) {
    return required ? [tRaw('Поле «:field» обязательно для заполнения.', { field: label })] : []
  }

  const numeric = rules.includes('numeric') || rules.includes('integer')
  const errors: string[] = []

  for (const rule of rules) {
    const [name, arg = ''] = rule.split(':', 2) as [string, string?]
    switch (name) {
      case 'email':
        if (typeof value !== 'string' || !EMAIL_RE.test(value)) {
          errors.push(tRaw('Поле «:field» должно быть адресом электронной почты.', { field: label }))
        }
        break
      case 'numeric':
        if (!Number.isFinite(Number(value))) {
          errors.push(tRaw('Поле «:field» должно быть числом.', { field: label }))
        }
        break
      case 'integer':
        if (!Number.isInteger(Number(value))) {
          errors.push(tRaw('Поле «:field» должно быть целым числом.', { field: label }))
        }
        break
      case 'min': {
        const size = sizeOf(value, numeric)
        if (size !== null && size < Number(arg)) {
          errors.push(tRaw('Поле «:field»: не меньше :min.', { field: label, min: arg }))
        }
        break
      }
      case 'max': {
        const size = sizeOf(value, numeric)
        if (size !== null && size > Number(arg)) {
          errors.push(tRaw('Поле «:field»: не больше :max.', { field: label, max: arg }))
        }
        break
      }
      case 'in':
        if (!arg.split(',').includes(String(value))) {
          errors.push(tRaw('Поле «:field» содержит недопустимое значение.', { field: label }))
        }
        break
      default:
        // Anything else is the server's to check.
        break
    }
  }

  return errors
}

/**
 * Checks a step: its fields' own rules plus the step's `rules` map
 * (`Step::rules(['email' => ['required', 'email']])`). Returns the errors keyed
 * by field name — empty when the step may be left.
 */
export function validateStep(
  fields: WizardField[],
  stepRules: unknown,
  getValue: (name: string) => unknown,
): Record<string, string[]> {
  const extra: Record<string, string[]> =
    stepRules && typeof stepRules === 'object' && !Array.isArray(stepRules)
      ? Object.fromEntries(
          Object.entries(stepRules as Record<string, unknown>).map(([k, v]) => [k, normalizeRules(v)]),
        )
      : {}

  const labels = new Map(fields.map((f) => [f.name, f.label]))
  const byName = new Map<string, string[]>()
  for (const f of fields) byName.set(f.name, [...f.rules])
  for (const [name, rules] of Object.entries(extra)) {
    byName.set(name, [...new Set([...(byName.get(name) ?? []), ...rules])])
  }

  const errors: Record<string, string[]> = {}
  for (const [name, rules] of byName) {
    const messages = validateValue(getValue(name), rules, labels.get(name) ?? name)
    if (messages.length > 0) errors[name] = messages
  }
  return errors
}

/* -----------------------------------------------------------------
 * Progress in localStorage
 * ----------------------------------------------------------------- */

export interface WizardProgress {
  step: number
  values: Record<string, unknown>
}

/** Secrets are never written to the browser's storage. */
const NOT_PERSISTED_TYPES: ReadonlySet<string> = new Set(['password', 'file', 'image', 'image_cropper'])

export function persistableFields(fields: WizardField[]): WizardField[] {
  return fields.filter((f) => !NOT_PERSISTED_TYPES.has(f.type))
}

export function storageKey(persistKey: string): string {
  return `admin.wizard.${persistKey}`
}

export function loadProgress(persistKey: string): WizardProgress | null {
  try {
    const raw = window.localStorage.getItem(storageKey(persistKey))
    if (!raw) return null
    const parsed = JSON.parse(raw) as Partial<WizardProgress>
    if (typeof parsed !== 'object' || parsed === null) return null
    return {
      step: typeof parsed.step === 'number' ? parsed.step : 0,
      values: parsed.values && typeof parsed.values === 'object' ? parsed.values : {},
    }
  } catch {
    return null
  }
}

export function saveProgress(persistKey: string, progress: WizardProgress): void {
  try {
    window.localStorage.setItem(storageKey(persistKey), JSON.stringify(progress))
  } catch {
    // Storage full or blocked: the wizard works on without it.
  }
}

export function clearProgress(persistKey: string): void {
  try {
    window.localStorage.removeItem(storageKey(persistKey))
  } catch {
    // Nothing to clear.
  }
}

