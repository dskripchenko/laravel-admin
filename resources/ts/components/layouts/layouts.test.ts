import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref, type Component } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import LayoutRenderer, { type LayoutNode } from '../render/LayoutRenderer.vue'
import { clearRegistry, registerLayout } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { provideFormState, type FormStateContext } from '../render/formState'
import { provideScreenContext, type ScreenContext } from '../render/screenContext'
import { provideRecord } from '../infolist/recordContext'
import { clearInfolistRegistry, registerBuiltinInfolistEntries } from '../infolist'
import { setAdminClient } from '../../stores/registry'
import { useAuthStore } from '../../stores/auth'
import { storageKey } from './wizard'

interface HostOptions {
  state?: Record<string, unknown>
  record?: Record<string, unknown> | null
  screen?: boolean
  runMethod?: (method: string) => Promise<boolean>
}

/** Mounts a node the way ScreenPage does: form state, record, screen context. */
function mountNode(node: LayoutNode, options: HostOptions = {}) {
  const handles: { form?: FormStateContext; screen?: ScreenContext } = {}
  const Host = defineComponent({
    setup() {
      handles.form = provideFormState(options.state ?? {})
      if (options.record !== null) provideRecord(options.record ?? handles.form.state)
      if (options.screen !== false) {
        handles.screen = provideScreenContext({
          running: ref(false),
          runMethod: options.runMethod ?? (async () => true),
          confirm: () => true,
        })
      }
      return () => h(LayoutRenderer, { node })
    },
  })
  const wrapper = mount(Host, { global: { stubs: { teleport: true } } })
  return { wrapper, ...handles }
}

const field = (name: string, extra: Record<string, unknown> = {}): LayoutNode => ({
  kind: 'field',
  type: 'input',
  name,
  label: name,
  ...extra,
})

beforeEach(() => {
  setActivePinia(createPinia())
  clearRegistry()
  registerBuiltinComponents()
})

describe('AccordionLayout', () => {
  const sections = [
    { title: 'One', defaultOpen: false, children: [field('a')] },
    { title: 'Two', defaultOpen: true, children: [field('b')] },
    { title: 'Three', defaultOpen: true, children: [field('c')] },
  ]

  const expanded = (wrapper: ReturnType<typeof mount>) =>
    wrapper.findAll('.uid-accordion-item__trigger').map((b) => b.attributes('aria-expanded'))

  it('opens only the first defaultOpen section in the single mode', () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'accordion', sections })
    expect(wrapper.text()).toContain('One')
    expect(expanded(wrapper)).toEqual(['false', 'true', 'false'])
    // Closed sections keep their fields mounted, so the state is not lost.
    expect(wrapper.findAll('input')).toHaveLength(3)
  })

  it('keeps every defaultOpen section open in the multi mode', () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'accordion', sections, multi: true })
    expect(expanded(wrapper)).toEqual(['false', 'true', 'true'])
  })

  it('opens a closed section on click and closes the other in the single mode', async () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'accordion', sections })
    await wrapper.findAll('.uid-accordion-item__trigger')[0]!.trigger('click')
    expect(expanded(wrapper)).toEqual(['true', 'false', 'false'])
  })

  it('lets several sections stay open in the multi mode', async () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'accordion', sections, multi: true })
    await wrapper.findAll('.uid-accordion-item__trigger')[0]!.trigger('click')
    expect(expanded(wrapper)).toEqual(['true', 'true', 'true'])
  })
})

describe('WizardLayout', () => {
  const steps: LayoutNode[] = [
    { type: 'step', title: 'Account', items: [field('email', { required: true, rules: ['required', 'email'] })] },
    { type: 'step', title: 'Profile', rules: { name: ['required'] }, items: [field('name')] },
    { type: 'step', title: 'Done', items: [] },
  ]

  afterEach(() => {
    window.localStorage.clear()
  })

  function buttonByClass(wrapper: ReturnType<typeof mount>, cls: string) {
    return wrapper.find(`.${cls}`)
  }

  it('renders a stepper and only the current step', () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'wizard', items: steps })
    expect(wrapper.find('.uid-stepper').exists()).toBe(true)
    expect(wrapper.findAll('.uid-stepper__step')).toHaveLength(3)
    expect(wrapper.html()).toContain('name="email"')
    expect(wrapper.html()).not.toContain('name="name"')
  })

  it('blocks moving forward while the step is invalid and shows the errors', async () => {
    const { wrapper, form } = mountNode({ kind: 'layout', type: 'wizard', items: steps })
    await buttonByClass(wrapper, 'admin-wizard-layout__next').trigger('click')
    await flushPromises()
    expect(form!.errors.email).toBeDefined()
    expect(wrapper.html()).toContain('name="email"')

    form!.setField('email', 'not-an-email')
    await buttonByClass(wrapper, 'admin-wizard-layout__next').trigger('click')
    await flushPromises()
    expect(form!.errors.email?.[0]).toContain('email')

    form!.setField('email', 'a@b.co')
    await buttonByClass(wrapper, 'admin-wizard-layout__next').trigger('click')
    await flushPromises()
    expect(form!.errors.email).toBeUndefined()
    expect(wrapper.html()).toContain('name="name"')
  })

  it("applies the step's own rules map", async () => {
    const { wrapper, form } = mountNode(
      { kind: 'layout', type: 'wizard', items: steps },
      { state: { email: 'a@b.co' } },
    )
    await buttonByClass(wrapper, 'admin-wizard-layout__next').trigger('click')
    await flushPromises()
    await buttonByClass(wrapper, 'admin-wizard-layout__next').trigger('click')
    await flushPromises()
    expect(form!.errors.name).toBeDefined()
  })

  it('calls the submit method on the last step', async () => {
    const runMethod = vi.fn(async () => true)
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'wizard', items: steps, submitMethod: 'finish', freeForm: true },
      { state: { email: 'a@b.co', name: 'Ann' }, runMethod },
    )
    // Free form: jump straight to the last step through the stepper.
    await wrapper.findAll('.uid-stepper__step')[2]!.trigger('click')
    await flushPromises()
    await buttonByClass(wrapper, 'admin-wizard-layout__submit').trigger('click')
    await flushPromises()
    expect(runMethod).toHaveBeenCalledWith('finish')
  })

  it('does not let a linear wizard jump forward through the stepper', async () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'wizard', items: steps })
    await wrapper.findAll('.uid-stepper__step')[2]!.trigger('click')
    await flushPromises()
    expect(wrapper.html()).toContain('name="email"')
  })

  it('sends a free-form wizard back to the first invalid step on submit', async () => {
    const runMethod = vi.fn(async () => true)
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'wizard', items: steps, submitMethod: 'finish', freeForm: true },
      { runMethod },
    )
    await wrapper.findAll('.uid-stepper__step')[2]!.trigger('click')
    await flushPromises()
    await buttonByClass(wrapper, 'admin-wizard-layout__submit').trigger('click')
    await flushPromises()
    expect(runMethod).not.toHaveBeenCalled()
    expect(wrapper.html()).toContain('name="email"')
  })

  it('persists the step and the values under persistKey and restores them', async () => {
    const node: LayoutNode = { kind: 'layout', type: 'wizard', items: steps, persistKey: 'onboarding' }
    const first = mountNode(node, { state: { email: 'a@b.co' } })
    await buttonByClass(first.wrapper, 'admin-wizard-layout__next').trigger('click')
    await flushPromises()
    first.form!.setField('name', 'Ann')
    await flushPromises()

    const saved = JSON.parse(window.localStorage.getItem(storageKey('onboarding'))!)
    expect(saved).toEqual({ step: 1, values: { email: 'a@b.co', name: 'Ann' } })
    first.wrapper.unmount()

    const second = mountNode(node)
    await flushPromises()
    expect(second.form!.getField('name')).toBe('Ann')
    expect(second.wrapper.html()).toContain('name="name"')
  })

  it('never stores a password', async () => {
    const node: LayoutNode = {
      kind: 'layout',
      type: 'wizard',
      persistKey: 'secret',
      items: [{ type: 'step', title: 'A', items: [field('pw', { type: 'password' }), field('login')] }],
    }
    const { form } = mountNode(node, { state: { pw: 'hunter2', login: 'ann' } })
    form!.setField('login', 'bob')
    await flushPromises()
    const saved = JSON.parse(window.localStorage.getItem(storageKey('secret'))!)
    expect(saved.values).toEqual({ login: 'bob' })
  })
})

describe('StepLayout', () => {
  it('renders a lone step as a titled section', () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'step', title: 'Alone', items: [field('x')] })
    expect(wrapper.find('.admin-section__title').text()).toBe('Alone')
    expect(wrapper.findAll('input')).toHaveLength(1)
  })
})

describe('ModalLayout and DrawerLayout', () => {
  for (const type of ['modal', 'drawer'] as const) {
    const root = type === 'modal' ? '.uid-modal' : '.uid-drawer'

    it(`${type}: closed until the screen opens it by id, then shows the children`, async () => {
      const { wrapper, screen } = mountNode({
        kind: 'layout',
        type,
        id: 'edit',
        title: 'Edit',
        items: [field('title')],
      })
      expect(wrapper.find(root).exists()).toBe(false)

      screen!.open('edit')
      await flushPromises()
      expect(wrapper.find(root).exists()).toBe(true)
      expect(wrapper.find(root).text()).toContain('Edit')
      expect(wrapper.findAll('input')).toHaveLength(1)
    })

    it(`${type}: an action with attributes.opens opens it through dispatch`, async () => {
      const { wrapper, screen } = mountNode({ kind: 'layout', type, id: 'side', items: [] })
      await screen!.dispatch({ attributes: { opens: 'side' } })
      await flushPromises()
      expect(wrapper.find(root).exists()).toBe(true)
    })

    it(`${type}: a footer method runs and closes it on success`, async () => {
      const runMethod = vi.fn(async () => true)
      const { wrapper, screen } = mountNode(
        {
          kind: 'layout',
          type,
          id: 'm',
          items: [],
          footer: [{ name: 'save', label: 'Save', primary: true, attributes: { method: 'save' } }],
        },
        { runMethod },
      )
      screen!.open('m')
      await flushPromises()
      const save = wrapper.findAll('button').find((b) => b.text() === 'Save')!
      await save.trigger('click')
      await flushPromises()
      expect(runMethod).toHaveBeenCalledWith('save')
      expect(screen!.isOpen('m')).toBe(false)
    })

    it(`${type}: a failed footer method keeps it open`, async () => {
      const { wrapper, screen } = mountNode(
        {
          kind: 'layout',
          type,
          id: 'm',
          items: [],
          footer: [{ name: 'save', label: 'Save', attributes: { method: 'save' } }],
        },
        { runMethod: async () => false },
      )
      screen!.open('m')
      await flushPromises()
      await wrapper.findAll('button').find((b) => b.text() === 'Save')!.trigger('click')
      await flushPromises()
      expect(screen!.isOpen('m')).toBe(true)
    })

    it(`${type}: a non-dismissable one has no cross and ignores Escape`, async () => {
      const { wrapper, screen } = mountNode({ kind: 'layout', type, id: 'm', dismissable: false, items: [] })
      screen!.open('m')
      await flushPromises()
      expect(wrapper.find(`${root}__close`).exists()).toBe(false)
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
      await flushPromises()
      expect(screen!.isOpen('m')).toBe(true)
    })

    it(`${type}: a dismissable one closes on its cross`, async () => {
      const { wrapper, screen } = mountNode({ kind: 'layout', type, id: 'm', items: [] })
      screen!.open('m')
      await flushPromises()
      await wrapper.find(`${root}__close`).trigger('click')
      expect(screen!.isOpen('m')).toBe(false)
    })
  }

  it('modal: a "close" footer action closes it without a method', async () => {
    const { wrapper, screen } = mountNode({
      kind: 'layout',
      type: 'modal',
      id: 'm',
      items: [],
      footer: [{ name: 'cancel', label: 'Cancel', attributes: {} }],
    })
    screen!.open('m')
    await flushPromises()
    await wrapper.findAll('button').find((b) => b.text() === 'Cancel')!.trigger('click')
    expect(screen!.isOpen('m')).toBe(false)
  })

  it('modal: maps the size, with full as the widest the kit has', async () => {
    const { wrapper, screen } = mountNode({ kind: 'layout', type: 'modal', id: 'm', size: 'full', items: [] })
    screen!.open('m')
    await flushPromises()
    expect(wrapper.find('.uid-modal').classes()).toContain('uid-modal--xl')
  })

  it('drawer: takes the side from position and the width from a size token', async () => {
    const { wrapper, screen } = mountNode({
      kind: 'layout',
      type: 'drawer',
      id: 'd',
      position: 'left',
      size: 'lg',
      items: [],
    })
    screen!.open('d')
    await flushPromises()
    const panel = wrapper.find('.uid-drawer')
    expect(panel.classes()).toContain('uid-drawer--left')
    expect(panel.attributes('style')).toContain('640px')
  })

  it('outside a screen, an overlay marked open renders on its own', () => {
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'modal', open: true, title: 'Hi', items: [] },
      { screen: false },
    )
    expect(wrapper.find('.uid-modal').exists()).toBe(true)
  })
})

describe('ViewLayout', () => {
  it('renders the registered component with the backend props', () => {
    const Card: Component = defineComponent({
      props: { count: { type: Number, default: 0 }, label: { type: String, default: '' } },
      setup: (p) => () => h('p', { class: 'card' }, `${p.label}: ${p.count}`),
    })
    registerLayout('my-card', Card)
    const { wrapper } = mountNode({ kind: 'layout', type: 'view', component: 'my-card', count: 42, label: 'Items' })
    expect(wrapper.find('.card').text()).toBe('Items: 42')
  })

  it('warns about an unregistered component instead of rendering anything', () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'view', component: 'nope', html: '<b>x</b>' })
    expect(wrapper.find('.admin-view-layout__missing').exists()).toBe(true)
    expect(wrapper.text()).toContain("registerLayout('nope'")
    expect(wrapper.find('b').exists()).toBe(false)
  })

  it('refuses to render itself', () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'view', component: 'view' })
    expect(wrapper.find('.admin-view-layout__missing').exists()).toBe(true)
  })
})

describe('WrapperLayout', () => {
  it('wraps the children into the given tag and class', () => {
    const { wrapper } = mountNode({
      kind: 'layout',
      type: 'wrapper',
      tag: 'section',
      className: 'two-col-grid',
      items: [field('a'), field('b')],
    })
    const el = wrapper.find('section.admin-wrapper-layout')
    expect(el.exists()).toBe(true)
    expect(el.classes()).toContain('two-col-grid')
    expect(el.findAll('input')).toHaveLength(2)
  })

  it('falls back to a div for a tag outside the allowlist', () => {
    const { wrapper } = mountNode({ kind: 'layout', type: 'wrapper', tag: 'script', items: [] })
    expect(wrapper.find('script').exists()).toBe(false)
    expect(wrapper.find('div.admin-wrapper-layout').exists()).toBe(true)
  })
})

describe('InfolistLayout', () => {
  beforeEach(() => {
    clearInfolistRegistry()
    registerBuiltinInfolistEntries()
  })

  const entries = [
    { kind: 'entry', type: 'text', name: 'title', label: 'Title' },
    { kind: 'entry', type: 'text', name: 'status', label: 'Status' },
  ] as unknown as LayoutNode[]

  it('renders the entries from the screen record', () => {
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'infolist', items: entries },
      { record: { title: 'Hello', status: 'draft' } },
    )
    expect(wrapper.find('.admin-infolist-layout--rows').exists()).toBe(true)
    expect(wrapper.text()).toContain('Hello')
    expect(wrapper.text()).toContain('draft')
  })

  it('falls back to the form state when there is no record', () => {
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'infolist', items: entries },
      { record: null, state: { title: 'From form' } },
    )
    expect(wrapper.text()).toContain('From form')
  })

  it('lays the entries out as a grid of the given columns', () => {
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'infolist', items: entries, layout: 'grid', columns: 3 },
      { record: {} },
    )
    expect(wrapper.find('.admin-infolist-layout--grid').exists()).toBe(true)
  })
})

describe('AuditTrailLayout', () => {
  const get = vi.fn()

  beforeEach(() => {
    get.mockReset()
    get.mockResolvedValue({
      data: [
        { id: 3, event: 'updated', created_at: new Date().toISOString(), actor: null, summary: 'x', diff: null },
        { id: 2, event: 'updated', created_at: new Date().toISOString(), actor: null, summary: 'y', diff: null },
        { id: 1, event: 'created', created_at: new Date().toISOString(), actor: null, summary: 'z', diff: null },
      ],
    })
    setAdminClient({ get, post: vi.fn() } as never)
  })

  it('loads the timeline of the record id from the state, up to the limit', async () => {
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'audit_trail', subjectType: 'App\\Models\\User', idStateKey: 'user_id', limit: 2 },
      { record: { user_id: 7 } },
    )
    await flushPromises()
    await flushPromises()
    expect(get).toHaveBeenCalledWith(
      `/audit/timeline?subject_type=${encodeURIComponent('App\\Models\\User')}&subject_id=7`,
    )
    expect(wrapper.findAll('.admin-audit-timeline__item')).toHaveLength(2)
  })

  it('shows nothing while the record has no id', () => {
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'audit_trail', subjectType: 'App\\Models\\User' },
      { record: {} },
    )
    expect(wrapper.find('.admin-audit-timeline').exists()).toBe(false)
    expect(get).not.toHaveBeenCalled()
  })

  it('is hidden without the permission', () => {
    useAuthStore().permissions = ['other.*']
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'audit_trail', subjectType: 'User', permission: 'admin.audit' },
      { record: { id: 1 } },
    )
    expect(wrapper.find('.admin-audit-timeline').exists()).toBe(false)
  })

  it('is shown with the permission', () => {
    useAuthStore().permissions = ['admin.*']
    const { wrapper } = mountNode(
      { kind: 'layout', type: 'audit_trail', subjectType: 'User', permission: 'admin.audit' },
      { record: { id: 1 } },
    )
    expect(wrapper.find('.admin-audit-timeline').exists()).toBe(true)
  })
})
