/**
 * The editor fields — Field\Bpmn, Dmn, Kanban, Calendar and MarkdownEditor —
 * and their read-only views, against stand-ins for @dskripchenko/bpmn's
 * editors and viewers, @dskripchenko/markdown-editor and the kit's kanban and
 * calendar: what each passes in, how a change reaches the form state, the
 * read-only modes and the field error.
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, nextTick, type PropType } from 'vue'
import { markdownBlockRenderersKey, UidMarkdown } from '@dskripchenko/ui'
import { setMarkdownEditorLoader, setNotationLoaders, type BpmnVueModule, type MarkdownEditorModule } from '../documents/loaders'
import BpmnField from './BpmnField.vue'
import DmnField from './DmnField.vue'
import KanbanField from './KanbanField.vue'
import CalendarField from './CalendarField.vue'
import MarkdownEditorField from './MarkdownEditorField.vue'
import BpmnEntry from '../infolist/BpmnEntry.vue'
import DmnEntry from '../infolist/DmnEntry.vue'
import KanbanEntry from '../infolist/KanbanEntry.vue'
import CalendarEntry from '../infolist/CalendarEntry.vue'
import MarkdownEntry from '../infolist/MarkdownEntry.vue'
import FieldEntry from '../infolist/FieldEntry.vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import { provideRecord } from '../infolist/recordContext'
import { clearRegistry, getField } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { registerBuiltinInfolistEntries } from '../infolist/builtin'
import { getInfolistEntry } from '../infolist/registry'
import { builtinMarkdownRenderers } from '../markdown/blockRenderers'

const BPMN = '<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL" id="D1"><process id="P1"/></definitions>'
const BPMN_NEXT = BPMN.replace('P1', 'P2')
const DMN = '<definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/" id="D1"><decision id="Decision_1"/></definitions>'
const BOARD = '## To do\n- Write the brief\n\n## Done\n- Kick-off'
const MOVED = '## To do\n\n## Done\n- Kick-off\n- Write the brief'
const EVENTS = '2026-12-01 10:00-11:00 Release review'

/* ---------------- stand-ins ---------------- */

/** The last props each stand-in was rendered with, by its name. */
let seen: Record<string, Record<string, unknown>> = {}

function editorStub(name: string) {
  return defineComponent({
    name,
    props: {
      modelValue: { type: String, default: null },
      readonly: Boolean,
      properties: Boolean,
      diagram: { type: [String, Number], default: undefined },
      decision: { type: String, default: undefined },
      locale: { type: String, default: undefined },
    },
    emits: ['update:modelValue', 'change', 'error'],
    setup(props) {
      return () => {
        seen[name] = { ...props }
        return h('div', { class: `stub-${name}` })
      }
    },
  })
}

function viewerStub(name: string) {
  return defineComponent({
    name,
    props: {
      xml: { type: String, default: null },
      diagram: { type: [String, Number], default: undefined },
      decision: { type: String, default: undefined },
      highlight: { type: Array, default: () => [] },
      locale: { type: String, default: undefined },
    },
    setup(props) {
      return () => {
        seen[name] = { ...props }
        return h('div', { class: `stub-${name}` })
      }
    },
  })
}

const BpmnEditorStub = editorStub('BpmnEditor')
const DmnEditorStub = editorStub('DmnEditor')
const bpmnVue = {
  BpmnEditor: BpmnEditorStub,
  DmnEditor: DmnEditorStub,
  BpmnView: viewerStub('BpmnView'),
  DmnView: viewerStub('DmnView'),
} as unknown as BpmnVueModule

const MarkdownEditorStub = defineComponent({
  name: 'MarkdownEditor',
  props: ['modelValue', 'mode', 'modes', 'placeholder', 'readonly', 'minHeight', 'maxHeight', 'blockRenderers', 'locale'],
  emits: ['update:modelValue', 'update:mode', 'change'],
  setup(props) {
    return () => {
      seen.MarkdownEditor = { ...props }
      return h('div', { class: 'stub-MarkdownEditor' })
    }
  },
})
const markdownEditor = { MarkdownEditor: MarkdownEditorStub } as unknown as MarkdownEditorModule

/** The kit's kanban and calendar, keeping their props and emitting like the kit. */
function markupStub(name: string) {
  return defineComponent({
    name,
    props: ['source', 'editable', 'locked', 'lockable', 'view', 'date', 'views'],
    emits: ['change', 'lock'],
    setup(props) {
      return () => {
        seen[name] = { ...props }
        return h('div', { class: `stub-${name}` })
      }
    },
  })
}
const KanbanStub = markupStub('UidKanban')
const CalendarStub = markupStub('UidEventCalendar')

/* ---------------- the host ---------------- */

let wrapper: VueWrapper | null = null
let form: FormStateContext

function host(
  component: unknown,
  props: Record<string, unknown>,
  options: { state?: Record<string, unknown>; record?: Record<string, unknown>; provide?: Record<symbol, unknown> } = {},
): VueWrapper {
  const Host = defineComponent({
    props: { inner: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(p) {
      form = provideFormState(options.state ?? {})
      if (options.record) provideRecord(options.record)
      return () => h(component as never, p.inner as never)
    },
  })
  wrapper = mount(Host, {
    props: { inner: props },
    global: {
      provide: options.provide ?? {},
      stubs: { UidKanban: KanbanStub, UidEventCalendar: CalendarStub },
    },
  })
  return wrapper
}

beforeEach(() => {
  seen = {}
  setNotationLoaders({ bpmnVue: async () => bpmnVue })
  setMarkdownEditorLoader(async () => markdownEditor)
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  setNotationLoaders(null)
  setMarkdownEditorLoader(null)
})

/* ---------------- tests ---------------- */

describe('registration', () => {
  it('draws the editor field types as fields and as entries', () => {
    clearRegistry()
    registerBuiltinComponents()
    registerBuiltinInfolistEntries()
    expect(getField('bpmn')).toBe(BpmnField)
    expect(getField('dmn')).toBe(DmnField)
    expect(getField('kanban')).toBe(KanbanField)
    expect(getField('calendar')).toBe(CalendarField)
    expect(getField('markdown_editor')).toBe(MarkdownEditorField)
    expect(getInfolistEntry('bpmn')).toBe(BpmnEntry)
    expect(getInfolistEntry('dmn')).toBe(DmnEntry)
    expect(getInfolistEntry('kanban')).toBe(KanbanEntry)
    expect(getInfolistEntry('calendar')).toBe(CalendarEntry)
    expect(getInfolistEntry('markdown_editor')).toBe(MarkdownEntry)
  })
})

describe('BpmnField and DmnField', () => {
  it('edits the BPMN XML in the form state, with the options', async () => {
    host(BpmnField, { name: 'process', label: 'Process', properties: true, diagram: 'Collab', height: 640 }, { state: { process: BPMN } })
    await flushPromises()
    expect(seen.BpmnEditor).toMatchObject({ modelValue: BPMN, readonly: false, properties: true, diagram: 'Collab' })
    expect(wrapper!.text()).toContain('Process')
    expect((wrapper!.find('.admin-editor-field').element as HTMLElement).style.height).toBe('640px')

    wrapper!.findComponent(BpmnEditorStub).vm.$emit('update:modelValue', BPMN_NEXT)
    await nextTick()
    expect(form.getField('process')).toBe(BPMN_NEXT)
    expect(seen.BpmnEditor!.modelValue).toBe(BPMN_NEXT)
  })

  it('starts empty and edits a DMN file with DmnEditor', async () => {
    host(DmnField, { name: 'rules', decision: 'Decision_1' }, { state: { rules: null } })
    await flushPromises()
    expect(seen.BpmnEditor).toBeUndefined()
    expect(seen.DmnEditor).toMatchObject({ modelValue: '', decision: 'Decision_1', readonly: false })
    wrapper!.findComponent(DmnEditorStub).vm.$emit('update:modelValue', DMN)
    await nextTick()
    expect(form.getField('rules')).toBe(DMN)
  })

  it('is a viewer when readonly or disabled', async () => {
    host(BpmnField, { name: 'process', readonly: true }, { state: { process: BPMN } })
    await flushPromises()
    expect(seen.BpmnEditor!.readonly).toBe(true)
    wrapper!.unmount()

    host(DmnField, { name: 'rules', disabled: true }, { state: { rules: DMN } })
    await flushPromises()
    expect(seen.DmnEditor!.readonly).toBe(true)
  })

  it('shows the field error', async () => {
    host(BpmnField, { name: 'process' }, { state: {} })
    form.setErrors({ process: ['Wrong namespace of the BPMN model'] })
    await flushPromises()
    expect(wrapper!.text()).toContain('Wrong namespace of the BPMN model')
    expect(wrapper!.find('.admin-editor-field--invalid').exists()).toBe(true)
  })
})

describe('KanbanField and CalendarField', () => {
  it('writes the board\'s new markup into the form state', async () => {
    host(KanbanField, { name: 'board', label: 'Board' }, { state: { board: BOARD } })
    await flushPromises()
    expect(seen.UidKanban).toMatchObject({ source: BOARD, editable: true })
    // No lock in a field.
    expect(seen.UidKanban!.lockable).toBeFalsy()

    wrapper!.findComponent(KanbanStub).vm.$emit('change', MOVED)
    await nextTick()
    expect(form.getField('board')).toBe(MOVED)
    expect(seen.UidKanban!.source).toBe(MOVED)
  })

  it('writes the calendar\'s new markup into the form state, with the view options', async () => {
    host(CalendarField, { name: 'schedule', view: 'week', date: '2026-12-01', views: ['week', 'month'] }, { state: {} })
    await flushPromises()
    expect(seen.UidEventCalendar).toMatchObject({ source: '', view: 'week', date: '2026-12-01', views: ['week', 'month'], editable: true })

    wrapper!.findComponent(CalendarStub).vm.$emit('change', EVENTS)
    await nextTick()
    expect(form.getField('schedule')).toBe(EVENTS)
  })

  it('is read-only when readonly or disabled, and ignores a change then', async () => {
    host(KanbanField, { name: 'board', readonly: true }, { state: { board: BOARD } })
    await flushPromises()
    expect(seen.UidKanban!.editable).toBe(false)
    wrapper!.findComponent(KanbanStub).vm.$emit('change', MOVED)
    await nextTick()
    expect(form.getField('board')).toBe(BOARD)
    wrapper!.unmount()

    host(CalendarField, { name: 'schedule', disabled: true }, { state: { schedule: EVENTS } })
    await flushPromises()
    expect(seen.UidEventCalendar!.editable).toBe(false)
  })

  it('shows the field error', async () => {
    host(CalendarField, { name: 'schedule' }, { state: {} })
    form.setErrors({ schedule: ['The schedule is too long'] })
    await flushPromises()
    expect(wrapper!.text()).toContain('The schedule is too long')
  })
})

describe('MarkdownEditorField', () => {
  it('edits the Markdown in the form state, with the panel\'s block renderers and the options', async () => {
    host(MarkdownEditorField, {
      name: 'notes',
      modes: ['source', 'visual'],
      placeholder: 'Write…',
      minHeight: 320,
      maxHeight: '60vh',
    }, { state: { notes: '# Hi' } })
    await flushPromises()
    expect(seen.MarkdownEditor).toMatchObject({
      modelValue: '# Hi',
      mode: 'source',
      modes: ['source', 'visual'],
      placeholder: 'Write…',
      readonly: false,
      minHeight: '320px',
      maxHeight: '60vh',
      locale: 'ru',
    })
    expect(seen.MarkdownEditor!.blockRenderers).toBe(builtinMarkdownRenderers)

    wrapper!.findComponent(MarkdownEditorStub).vm.$emit('update:modelValue', '# Hello')
    await nextTick()
    expect(form.getField('notes')).toBe('# Hello')

    // The user switches the mode: the field keeps it.
    wrapper!.findComponent(MarkdownEditorStub).vm.$emit('update:mode', 'visual')
    await nextTick()
    expect(seen.MarkdownEditor!.mode).toBe('visual')
  })

  it('takes the block renderers a host has provided', async () => {
    const custom = { mermaid: builtinMarkdownRenderers.mermaid! }
    host(MarkdownEditorField, { name: 'notes' }, { state: {}, provide: { [markdownBlockRenderersKey as symbol]: custom } })
    await flushPromises()
    expect(seen.MarkdownEditor!.blockRenderers).toBe(custom)
    expect(seen.MarkdownEditor!.modelValue).toBe('')
  })

  it('is read-only when readonly or disabled', async () => {
    host(MarkdownEditorField, { name: 'notes', disabled: true }, { state: { notes: 'x' } })
    await flushPromises()
    expect(seen.MarkdownEditor!.readonly).toBe(true)
  })

  it('shows the field error', async () => {
    host(MarkdownEditorField, { name: 'notes' }, { state: {} })
    form.setErrors({ notes: ['The notes field must be a string.'] })
    await flushPromises()
    expect(wrapper!.text()).toContain('The notes field must be a string.')
  })
})

describe('the read-only entries', () => {
  it('draw a stored BPMN or DMN model with the viewers', async () => {
    host(BpmnEntry, { name: 'process', highlight: ['P1'], height: 300 }, { record: { process: BPMN } })
    await flushPromises()
    expect(seen.BpmnView).toMatchObject({ xml: BPMN, highlight: ['P1'] })
    expect(seen.BpmnEditor).toBeUndefined()
    wrapper!.unmount()

    host(DmnEntry, { name: 'rules', decision: 'Decision_1' }, { record: { rules: DMN } })
    await flushPromises()
    expect(seen.DmnView).toMatchObject({ xml: DMN, decision: 'Decision_1' })
  })

  it('draw a stored board and calendar without editing or a lock', async () => {
    host(KanbanEntry, { name: 'board' }, { record: { board: BOARD } })
    await flushPromises()
    expect(seen.UidKanban).toMatchObject({ source: BOARD, editable: false, lockable: false })
    wrapper!.unmount()

    host(CalendarEntry, { name: 'schedule', view: 'agenda' }, { record: { schedule: EVENTS } })
    await flushPromises()
    expect(seen.UidEventCalendar).toMatchObject({ source: EVENTS, view: 'agenda', editable: false })
  })

  it('show the placeholder for an empty value', async () => {
    for (const entry of [BpmnEntry, DmnEntry, KanbanEntry, CalendarEntry]) {
      host(entry, { name: 'x' }, { record: { x: null } })
      await flushPromises()
      expect(wrapper!.text()).toBe('—')
      wrapper!.unmount()
      wrapper = null
    }
    expect(Object.keys(seen)).toEqual([])
  })

  it('draw a field on the view page through FieldEntry, with the field\'s options', async () => {
    registerBuiltinInfolistEntries()
    host(FieldEntry, {
      name: 'schedule',
      field: { type: 'calendar', name: 'schedule', attributes: { view: 'week', maxLength: 100, readonly: true } },
    }, { record: { schedule: EVENTS } })
    await flushPromises()
    expect(seen.UidEventCalendar).toMatchObject({ source: EVENTS, view: 'week', editable: false })
    // A field's attributes are not the box's HTML attributes.
    expect(wrapper!.html()).not.toContain('maxlength')
    wrapper!.unmount()

    host(FieldEntry, { name: 'notes', field: { type: 'markdown_editor', name: 'notes', attributes: {} } }, { record: { notes: '# Title' } })
    await flushPromises()
    expect(wrapper!.findComponent(UidMarkdown).props('source')).toBe('# Title')
  })
})
