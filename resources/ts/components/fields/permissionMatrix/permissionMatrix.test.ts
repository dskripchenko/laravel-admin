import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { UidCheckbox } from '@dskripchenko/ui'
import { provideFormState, type FormStateContext } from '../../render/formState'
import PermissionMatrixField from '../PermissionMatrixField.vue'
import { filterGroups, stateOf, toggleKeys, type MatrixGroup } from './matrix'

const groups: MatrixGroup[] = [
  {
    label: 'Клиенты › Очереди',
    items: [
      { key: 'clients.claims', label: 'Обращения и претензии', extra: 'clients.claims.reveal' },
      { key: 'clients.callbacks', label: 'Обратные звонки' },
    ],
  },
  {
    label: 'Система',
    items: [
      { key: 'platform.roles', label: 'Роли' },
      { key: 'platform.audit', label: 'Журнал аудита', hint: 'Все изменения' },
    ],
  },
]

describe('matrix arithmetic', () => {
  it('tells none, some and all', () => {
    expect(stateOf(['a', 'b'], new Set())).toBe('none')
    expect(stateOf(['a', 'b'], new Set(['a']))).toBe('some')
    expect(stateOf(['a', 'b'], new Set(['a', 'b']))).toBe('all')
  })

  it('finds by label, key and hint, keeps a group found by its own label', () => {
    expect(filterGroups(groups, 'звонк', false, new Set()).map((g) => g.items.map((i) => i.key)))
      .toEqual([['clients.callbacks']])
    expect(filterGroups(groups, 'platform.au', false, new Set())[0].items.map((i) => i.key)).toEqual(['platform.audit'])
    expect(filterGroups(groups, 'изменения', false, new Set())[0].items).toHaveLength(1)
    expect(filterGroups(groups, 'систем', false, new Set())[0].items).toHaveLength(2)
    expect(filterGroups(groups, '', true, new Set(['platform.roles']))).toEqual([{ label: 'Система', items: [groups[1].items[0]] }])
  })

  it('turns keys on and off keeping the rest', () => {
    expect(toggleKeys(['x', 'a'], ['a', 'b'], true)).toEqual(['x', 'a', 'b'])
    expect(toggleKeys(['x', 'a', 'b'], ['a', 'b'], false)).toEqual(['x'])
  })
})

function mountField(initial: unknown, props: Record<string, unknown> = {}) {
  let ctx: FormStateContext | null = null
  const w = mount(defineComponent({
    setup() {
      ctx = provideFormState({ perms: initial })
      return () => h(PermissionMatrixField, { name: 'perms', groups, extraLabel: 'ПДн без маски', ...props })
    },
  }))
  return { w, ctx: ctx! }
}

describe('PermissionMatrixField', () => {
  it('counts the selected and shows a group half-checked', () => {
    const { w } = mountField(['clients.claims'])
    expect(w.get('[data-testid="permission-matrix-total"]').text()).toBe('Выбрано 1 из 4')
    const group = w.get('[data-testid="permission-group-клиенты-очереди"]')
    expect(group.get('[data-testid="permission-group-count"]').text()).toBe('1 из 2')
    const check = group.get('[data-testid="permission-group-check"]').element as HTMLInputElement
    expect(check.indeterminate).toBe(true)
  })

  it('checks a whole group and selects all', async () => {
    const { w, ctx } = mountField([])
    await w.get('[data-testid="permission-group-система"]').get('[data-testid="permission-group-check"]').setValue(true)
    expect(ctx.getField('perms')).toEqual(['platform.roles', 'platform.audit'])
    await w.get('[data-testid="permission-matrix-all"]').setValue(true)
    expect(ctx.getField('perms')).toEqual(['platform.roles', 'platform.audit', 'clients.claims', 'clients.callbacks'])
    await w.get('[data-testid="permission-matrix-all"]').setValue(false)
    expect(ctx.getField('perms')).toEqual([])
  })

  it('reads an Orchid map and grants the extra key with its main one', async () => {
    const { w, ctx } = mountField({ 'clients.callbacks': 1, 'platform.roles': 0 })
    expect(w.get('[data-testid="permission-matrix-total"]').text()).toBe('Выбрано 1 из 4')
    await w.get('[data-testid="permission-clients-claims"]').get('[data-testid="permission-extra"]').setValue(true)
    expect(ctx.getField('perms')).toEqual(['clients.callbacks', 'clients.claims', 'clients.claims.reveal'])
    await w.get('[data-testid="permission-clients-claims"]').get('[data-testid="permission-check"]').setValue(false)
    expect(ctx.getField('perms')).toEqual(['clients.callbacks'])
  })

  it('filters the rows by the search', async () => {
    const { w } = mountField([])
    await w.get('[data-testid="permission-matrix-search"]').setValue('аудит')
    await nextTick()
    const rows = w.findAll('.admin-permission-matrix__item')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('platform.audit')
    await w.get('[data-testid="permission-matrix-search"]').setValue('нет такого')
    expect(w.find('[data-testid="permission-matrix-empty"]').exists()).toBe(true)
  })

  it('locks every checkbox when read-only', async () => {
    const { w, ctx } = mountField(['clients.claims'], { readonly: true })
    await w.get('[data-testid="permission-matrix-all"]').setValue(true)
    expect(ctx.getField('perms')).toEqual(['clients.claims'])
    expect(w.findAllComponents(UidCheckbox).every((c) => c.props('disabled'))).toBe(true)
    expect(w.find('[data-testid="permission-matrix-search"]').exists()).toBe(true)
  })
})
