/**
 * The description of a dashboard widget type, for the add and configure
 * dialogs (WidgetConfigDialog): how it is labelled, whether a user may add it,
 * its default size, and the form that edits its `config` — the object saved
 * in the user's layout and handed to the widget component as its data.
 *
 *     describeWidget('weather', {
 *       label: 'Weather',
 *       icon: CloudSun,
 *       defaultSize: 3,
 *       fields: [
 *         { kind: 'text', key: 'city', label: 'City', required: true },
 *         { kind: 'select', key: 'units', label: 'Units', default: 'metric',
 *           options: [{ value: 'metric', label: '°C' }, { value: 'imperial', label: '°F' }] },
 *       ],
 *     })
 *
 * The form is either a list of field descriptors — drawn by WidgetConfigForm —
 * or a component of the host's (`form`), bound with v-model to the form's
 * values. `read()` turns a saved config into the form's values (by default the
 * fields' keys are taken as they are), and `build()` turns the values back
 * into the config (by default each shown field by its kind: a number field
 * as a number, a switch as a boolean, the rest as text). The title is the
 * dialog's own and is always saved as `config.title`.
 */

import type { Component } from 'vue'
import type { ManifestResourceMeta } from '../../stores/manifest'
import { trSafe } from '../../stores/i18n'

/** A text, or a function returning it — to translate at the moment it is shown. */
export type WidgetText = string | (() => string)

/** The form's values, by field key. */
export type WidgetConfigValues = Record<string, unknown>

export interface WidgetConfigOption {
  value: string
  label: WidgetText
}

/** What a form may need from the panel: the resources and the user's permissions. */
export interface WidgetConfigContext {
  /** The manifest's resources the user may view. */
  resources: ManifestResourceMeta[]
  hasPermission: (key: string) => boolean
}

interface FieldBase {
  /** The key in the form's values and, by default, in the saved config. */
  key: string
  label: WidgetText
  help?: WidgetText
  placeholder?: WidgetText
  /** The dialog's submit waits for a value. */
  required?: boolean
  /** Shown — and built — only while this returns true for the current values. */
  visible?: (values: WidgetConfigValues) => boolean
}

export type WidgetConfigField =
  | (FieldBase & { kind: 'text'; default?: WidgetText })
  | (FieldBase & { kind: 'textarea' | 'code'; default?: WidgetText; rows?: number })
  | (FieldBase & { kind: 'number'; default?: number; min?: number; max?: number })
  | (FieldBase & {
    kind: 'select'
    default?: string
    /** Static options, or computed from the context — the resources, say. */
    options: WidgetConfigOption[] | ((context: WidgetConfigContext) => WidgetConfigOption[])
  })
  | (FieldBase & { kind: 'switch'; default?: boolean })

/** What build() is told besides the values. */
export interface WidgetBuildContext {
  /** The widget type being configured. */
  type: string
  /** The title entered in the dialog. */
  title: string
}

export interface WidgetTypeDescription {
  /** The name in the type picker; the type itself without one. */
  label?: WidgetText
  /** A Vue icon component (a lucide icon, say) shown in the type picker. */
  icon?: Component
  /** False keeps the type out of the add dialog; it still renders. True by default. */
  addable?: boolean
  /** The width, in columns of twelve, a newly added widget starts with; 6 by default. */
  defaultSize?: number
  /** The height, in rows, when neither the widget nor the user set one. */
  defaultRowSpan?: number
  /** The config form as field descriptors. */
  fields?: WidgetConfigField[]
  /**
   * A form component of its own in place of `fields`: it gets `modelValue`
   * (the values), `type` and `context`, and emits `update:modelValue`.
   */
  form?: Component
  /** A saved config → the form's values. */
  read?: (config: Record<string, unknown>) => WidgetConfigValues
  /** The form's values → the config to save, without the title. */
  build?: (values: WidgetConfigValues, context: WidgetBuildContext) => Record<string, unknown>
}

/** A WidgetText as shown: a function is called, a string is translated. */
export function widgetText(text: WidgetText | undefined): string {
  if (text === undefined) return ''
  return typeof text === 'function' ? text() : trSafe(text)
}

/** The fields shown for these values. */
export function visibleFields(description: WidgetTypeDescription | null, values: WidgetConfigValues): WidgetConfigField[] {
  return (description?.fields ?? []).filter((f) => !f.visible || f.visible(values))
}

/** The options of a select field in this context. */
export function fieldOptions(
  field: Extract<WidgetConfigField, { kind: 'select' }>,
  context: WidgetConfigContext,
): WidgetConfigOption[] {
  return typeof field.options === 'function' ? field.options(context) : field.options
}

function defaultValue(field: WidgetConfigField): unknown {
  switch (field.kind) {
    case 'switch':
      return field.default ?? false
    case 'number':
      // Number fields are edited as text and parsed on build.
      return field.default === undefined ? '' : String(field.default)
    case 'select':
      return field.default ?? ''
    default:
      return widgetText(field.default)
  }
}

function formValue(field: WidgetConfigField, stored: unknown): unknown {
  if (field.kind === 'switch') return Boolean(stored)
  if (stored === null || stored === undefined) return defaultValue(field)
  return typeof stored === 'string' ? stored : String(stored)
}

/**
 * The form's starting values: the fields' defaults, and over them what a
 * saved config holds — through read() when the description has one.
 */
export function initialValues(
  description: WidgetTypeDescription | null,
  config: Record<string, unknown> | null,
): WidgetConfigValues {
  const values: WidgetConfigValues = {}
  for (const field of description?.fields ?? []) values[field.key] = defaultValue(field)
  if (!config) return values
  if (description?.read) return { ...values, ...description.read(config) }
  for (const field of description?.fields ?? []) {
    if (field.key in config) values[field.key] = formValue(field, config[field.key])
  }
  return values
}

/** The default build: each shown field by its kind. */
function buildFromFields(description: WidgetTypeDescription | null, values: WidgetConfigValues): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  for (const field of visibleFields(description, values)) {
    const raw = values[field.key]
    switch (field.kind) {
      case 'number': {
        const n = Number(raw)
        out[field.key] = raw === '' || Number.isNaN(n) ? (field.default ?? 0) : n
        break
      }
      case 'switch':
        out[field.key] = Boolean(raw)
        break
      default:
        out[field.key] = raw === null || raw === undefined ? '' : String(raw)
    }
  }
  return out
}

/** The config to save: the title, then what build() (or the fields) make of the values. */
export function buildWidgetConfig(
  description: WidgetTypeDescription | null,
  values: WidgetConfigValues,
  context: WidgetBuildContext,
): Record<string, unknown> {
  const built = description?.build ? description.build(values, context) : buildFromFields(description, values)
  return { title: context.title, ...built }
}

/** The labels of the required fields shown without a value. */
export function missingRequired(description: WidgetTypeDescription | null, values: WidgetConfigValues): string[] {
  return visibleFields(description, values)
    .filter((f) => f.required && f.kind !== 'switch')
    .filter((f) => {
      const v = values[f.key]
      return v === null || v === undefined || String(v).trim() === ''
    })
    .map((f) => widgetText(f.label))
}

/** The resources of the context that declare a view ('kanban' or 'calendar'). */
export function resourcesWithView(
  context: WidgetConfigContext,
  view: 'kanban' | 'calendar',
): WidgetConfigOption[] {
  return context.resources
    .filter((r) => Boolean(r[view]) && (!Array.isArray(r.views) || r.views.includes(view)))
    // A function: the label is already in the panel's language.
    .map((r) => ({ value: r.slug, label: () => r.label }))
}

/** Whether the user may view a resource, by its meta's view permission. */
export function canViewResource(meta: ManifestResourceMeta, hasPermission: (key: string) => boolean): boolean {
  const permission = meta.permissions?.view
  return typeof permission !== 'string' || hasPermission(permission)
}
