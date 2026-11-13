import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import TabsLayout from './TabsLayout.vue'
import { clearRegistry } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { provideFormState } from '../render/formState'
import { countMatches, fieldMatches } from '../render/fieldSearch'

const field = (name: string, label: string, help: string | null = null) => ({ kind: 'field', type: 'input', name, label, help })
const block = (title: string, items: unknown[]) => ({ kind: 'layout', type: 'block', title, items })

const tabs = [
  { key: 'general', label: 'Общие', items: [block('Сайт', [field('site.name', 'Название сайта')])] },
  {
    key: 'captcha',
    label: 'Капча',
    items: [
      block('Капча', [
        field('captcha.enabled', 'Включить капчу на формах'),
        field('captcha.provider', 'Провайдер'),
        field('captcha.threshold', 'Порог срабатывания', 'Выше — строже'),
      ]),
    ],
  },
  { key: 'forms', label: 'Формы обращений', items: [block('Отправка', [field('forms.captcha_after', 'Показывать капчу после N отправок'), field('forms.email', 'Почта')])] },
]

describe('field search', () => {
  it('matches a label, a hint or a key and counts at any depth', () => {
    expect(fieldMatches(field('a.b', 'Порог', 'Выше — строже'), 'строже')).toBe(true)
    expect(fieldMatches(field('captcha.server_key', 'Ключ сервера'), 'server_key')).toBe(true)
    expect(countMatches(tabs, 'капч')).toBe(2)
    expect(countMatches(tabs, 'captcha')).toBe(4)
  })
})

describe('TabsLayout searchable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    clearRegistry()
    registerBuiltinComponents()
  })

  function mountTabs() {
    return mount(defineComponent({
      setup() {
        provideFormState({})
        return () => h(TabsLayout, { items: tabs as never, searchable: true, orientation: 'vertical' })
      },
    }))
  }

  it('counts the finds per tab, dims the others, moves to a tab that finds and hides the rest', async () => {
    const w = mountTabs()
    await w.get('[data-testid="tabs-search"]').setValue('капч')
    await nextTick()
    expect(w.get('[data-testid="tabs-search-count"]').text()).toBe('2 совпадения')
    expect(w.get('[data-testid="tab-matches-captcha"]').text()).toBe('1')
    expect(w.get('[data-testid="tab-matches-forms"]').text()).toBe('1')
    expect(w.get('[data-testid="tab-general"]').classes()).toContain('admin-tab--dim')
    // «Общие» found nothing: the captcha tab opened.
    const panel = w.get('[data-testid="tab-panel-captcha"]')
    expect(panel.findAll('.admin-field-slot--match')).toHaveLength(1)
    expect(panel.find('[data-testid="field-captcha-provider"]').exists()).toBe(false)
    expect(panel.find('[data-testid="field-captcha-enabled"]').exists()).toBe(true)
  })

  it('hides a block left without fields and shows everything once cleared', async () => {
    const w = mountTabs()
    await w.get('[data-testid="tab-forms"]').trigger('click')
    await w.get('[data-testid="tabs-search"]').setValue('почта')
    await nextTick()
    expect(w.get('[data-testid="tab-panel-forms"]').findAll('.admin-section')).toHaveLength(1)
    await w.get('[data-testid="tabs-search"]').setValue('')
    await nextTick()
    expect(w.find('[data-testid="tab-matches-forms"]').exists()).toBe(false)
    expect(w.get('[data-testid="tab-panel-forms"]').findAll('.admin-field-slot')).toHaveLength(2)
  })
})
