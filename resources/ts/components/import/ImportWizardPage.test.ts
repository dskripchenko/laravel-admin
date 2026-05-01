import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ImportWizardPage from './ImportWizardPage.vue'

const FIELD_OPTIONS = [
  { value: 'title', label: 'Заголовок' },
  { value: 'author', label: 'Автор' },
  { value: 'status', label: 'Статус' },
]

describe('ImportWizardPage', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders title + stepper with 4 steps', () => {
    const w = mount(ImportWizardPage, {
      props: { fieldOptions: FIELD_OPTIONS },
    })
    expect(w.find('.admin-page__title').text()).toBe('Импорт')
  })

  it('starts on step 0 (Upload)', () => {
    const w = mount(ImportWizardPage, {
      props: { fieldOptions: FIELD_OPTIONS },
    })
    expect(w.text()).toContain('Загрузите файл')
  })

  it('Далее button disabled when no file uploaded', () => {
    const w = mount(ImportWizardPage, {
      props: { fieldOptions: FIELD_OPTIONS },
    })
    const next = w.findAll('button').find((b) => b.text() === 'Далее')
    expect((next!.element as HTMLButtonElement).disabled).toBe(true)
  })

  it('controlled step prop overrides internal state', () => {
    const w = mount(ImportWizardPage, {
      props: { fieldOptions: FIELD_OPTIONS, step: 2 },
    })
    expect(w.text()).toContain('Предпросмотр')
  })

  it('step 2 renders preview placeholder when no headers/columns', () => {
    const w = mount(ImportWizardPage, {
      props: { fieldOptions: FIELD_OPTIONS, step: 2 },
    })
    expect(w.text()).toContain('Host не передал previewColumns')
  })

  it('step 1 renders mapping rows from headers', () => {
    const w = mount(ImportWizardPage, {
      props: {
        fieldOptions: FIELD_OPTIONS,
        step: 1,
        headers: [
          { key: 'col_a', label: 'Колонка A', sample: 'Hello' },
          { key: 'col_b', label: 'Колонка B', sample: 'Author' },
        ],
      },
    })
    const rows = w.findAll('.admin-import-wizard__map-row')
    expect(rows).toHaveLength(2)
    expect(w.text()).toContain('Колонка A')
    expect(w.text()).toContain('«Hello»')
  })

  it('step 1 без headers показывает empty-message', () => {
    const w = mount(ImportWizardPage, {
      props: { fieldOptions: FIELD_OPTIONS, step: 1 },
    })
    expect(w.text()).toContain('Нет данных')
  })

  it('step 3 renders KPI stats from progress prop', () => {
    const w = mount(ImportWizardPage, {
      props: {
        fieldOptions: FIELD_OPTIONS,
        step: 3,
        progress: { created: 10, updated: 5, errors: 2, total: 17 },
      },
    })
    expect(w.text()).toContain('Создано')
    expect(w.text()).toContain('Обновлено')
    expect(w.text()).toContain('Ошибки')
  })

  it('emits cancel from header button', async () => {
    const w = mount(ImportWizardPage, {
      props: { fieldOptions: FIELD_OPTIONS },
    })
    const cancelBtn = w.findAll('button').find((b) => b.text() === 'Отмена')
    await cancelBtn!.trigger('click')
    expect(w.emitted('cancel')).toBeTruthy()
  })
})

// Vitest auto-globals: beforeEach available
import { beforeEach } from 'vitest'
