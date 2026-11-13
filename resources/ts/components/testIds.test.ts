/**
 * The test ids e2e tests rely on (docs/en/testing.md, "data-testid for end-to-end tests"): the rule
 * that builds them, the list of the key ones — each must stay in its
 * component under this very name, old and new — and the ones put on
 * elements the kit draws (the toasts, the table's rows and cells, a field).
 */
import { afterEach, describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { UidToastProvider, useToast } from '@dskripchenko/ui'
import { testIdOf, testIdPart } from '../composables/testId'
import { adminToast } from '../stores/toast'
import { markTableRows } from './resource/tableTestIds'
import FieldRenderer from './render/FieldRenderer.vue'
import { clearRegistry } from './render/registry'
import { registerBuiltinComponents } from './render/builtin'
import { provideFormState } from './render/formState'

describe('testIdOf', () => {
  it('puts the parts into kebab-case and joins them', () => {
    expect(testIdOf('field', 'firstName')).toBe('field-first-name')
    expect(testIdOf('field', 'first_name')).toBe('field-first-name')
    expect(testIdOf('field', 'user.email')).toBe('field-user-email')
    expect(testIdOf('field', 'items[0][title]')).toBe('field-items-0-title')
    expect(testIdOf('resource-row', 15)).toBe('resource-row-15')
    expect(testIdOf('block-tree-node', 'new-1a2b')).toBe('block-tree-node-new-1a2b')
    expect(testIdOf('block-tree-palette-type', 'App\\Blocks\\TextBlock')).toBe('block-tree-palette-type-app-blocks-text-block')
    expect(testIdOf('tab', 0)).toBe('tab-0')
    expect(testIdOf('entry', undefined)).toBe('entry')
    expect(testIdPart('HTMLParser')).toBe('html-parser')
    expect(testIdPart('Заказы')).toBe('заказы')
  })
})

/** [component, test id as written in its template] — a static one or the prefix of testIdOf(). */
const KEY_TEST_IDS: Array<[string, string]> = [
  // The shell: login, two-factor, menu, user menu.
  ['auth/LoginForm.vue', 'login-form'],
  ['auth/LoginForm.vue', 'login-email'],
  ['auth/LoginForm.vue', 'login-password'],
  ['auth/LoginForm.vue', 'login-remember'],
  ['auth/LoginForm.vue', 'login-submit'],
  ['auth/LoginForm.vue', 'login-error'],
  ['auth/TwoFactorForm.vue', 'two-factor-form'],
  ['auth/TwoFactorForm.vue', 'two-factor-code'],
  ['auth/TwoFactorForm.vue', 'two-factor-recovery-code'],
  ['auth/TwoFactorForm.vue', 'two-factor-submit'],
  ['shell/AdminSidebarNode.vue', '`menu-${item.key}`'],
  ['shell/widgets/UserMenu.vue', 'user-menu'],
  ['shell/widgets/UserMenu.vue', 'user-menu-profile'],
  ['shell/widgets/UserMenu.vue', 'logout'],
  ['shell/AdminTopBar.vue', 'topbar-search'],
  ['shell/AdminConfirmDialog.vue', 'confirm-ok'],
  ['shell/AdminConfirmDialog.vue', 'confirm-cancel'],
  // The list of a resource.
  ['resource/ResourceIndexPage.vue', "testIdOf('resource-index'"],
  ['resource/ResourceIndexPage.vue', 'resource-table'],
  ['resource/ResourceIndexPage.vue', 'resource-create'],
  ['resource/ResourceIndexPage.vue', 'resource-more'],
  ['resource/ResourceIndexPage.vue', 'resource-refresh'],
  ['resource/ResourceIndexPage.vue', "testIdOf('resource-export'"],
  ['resource/ResourceIndexPage.vue', 'resource-import'],
  ['resource/ResourceIndexPage.vue', 'resource-pagination'],
  ['resource/ResourceIndexPage.vue', 'resource-empty'],
  ['resource/ResourceIndexPage.vue', 'resource-no-results'],
  ['resource/ResourceIndexPage.vue', 'row-view'],
  ['resource/ResourceIndexPage.vue', 'row-edit'],
  ['resource/ResourceIndexPage.vue', 'row-delete'],
  ['resource/ResourceIndexPage.vue', 'row-replicate'],
  ['resource/ResourceIndexPage.vue', 'row-restore'],
  ['resource/ResourceIndexPage.vue', 'row-force-delete'],
  ['resource/ResourceIndexPage.vue', 'row-actions-menu'],
  ['resource/ResourceIndexPage.vue', 'bulk-bar'],
  ['resource/ResourceIndexPage.vue', 'bulk-delete'],
  ['resource/ResourceIndexPage.vue', 'bulk-export'],
  ['resource/ResourceIndexPage.vue', 'bulk-clear'],
  ['resource/AdminFilterToolbar.vue', 'resource-search'],
  ['resource/AdminFilterToolbar.vue', "testIdOf('filter'"],
  ['resource/AdminFilterToolbar.vue', "testIdOf('filter-popover'"],
  ['resource/AdminFilterToolbar.vue', "testIdOf('filter-apply'"],
  ['resource/AdminFilterToolbar.vue', "testIdOf('filter-clear'"],
  ['resource/AdminFilterToolbar.vue', "testIdOf('filter-reset'"],
  ['resource/AdminFilterToolbar.vue', 'filter-add'],
  ['resource/AdminFilterToolbar.vue', 'toolbar-reset'],
  // The form, the card and the drawer of a record.
  ['resource/ResourceFormPage.vue', "testIdOf('resource-form'"],
  ['resource/ResourceFormPage.vue', 'form-save'],
  ['resource/ResourceFormPage.vue', 'form-delete'],
  ['resource/ResourceFormPage.vue', 'form-cancel'],
  ['resource/ResourceFormPage.vue', 'form-savebar-save'],
  ['render/FieldRenderer.vue', "testIdOf('field'"],
  ['resource/ResourceViewPage.vue', "testIdOf('resource-view'"],
  ['resource/ResourceViewPage.vue', 'view-edit'],
  ['resource/ResourceViewPage.vue', 'view-delete'],
  ['infolist/InfolistRenderer.vue', "testIdOf('entry'"],
  ['resource/ResourceRecordDrawer.vue', 'record-drawer'],
  ['resource/ResourceRecordDrawer.vue', 'record-drawer-save'],
  // Screens, actions, overlays, tabs.
  ['ScreenPage.vue', "testIdOf('screen'"],
  ['ScreenPage.vue', 'screen-commands'],
  ['ScreenPage.vue', 'screen-message'],
  ['ScreenPage.vue', 'screen-error'],
  ['actions/AdminActionButton.vue', '`action-${action.name}`'],
  ['actions/AdminActionMenuItems.vue', '`action-${action.name}`'],
  ['actions/AdminActionDialogs.vue', 'action-confirm-ok'],
  ['actions/AdminActionDialogs.vue', 'action-confirm-cancel'],
  ['actions/AdminActionDialogs.vue', 'action-modal-submit'],
  ['layouts/ModalLayout.vue', "testIdOf('modal'"],
  ['layouts/ModalLayout.vue', '`action-${action.name}`'],
  ['layouts/DrawerLayout.vue', '`action-${action.name}`'],
  ['layouts/DrawerLayout.vue', "testIdOf('drawer'"],
  ['layouts/TabsLayout.vue', "testIdOf('tab'"],
  ['layouts/TabsLayout.vue', "testIdOf('tab-panel'"],
  // The block tree.
  ['blockTree/BlockTreeLayout.vue', "testIdOf('block-tree'"],
  ['blockTree/BlockTreeLayout.vue', 'block-tree-add'],
  ['blockTree/BlockTreeLayout.vue', 'block-tree-undo'],
  ['blockTree/BlockTreeLayout.vue', 'block-tree-status'],
  ['blockTree/BlockTreeLayout.vue', 'block-tree-discard'],
  ['blockTree/BlockTreeLayout.vue', 'block-tree-publish'],
  ['blockTree/BlockTreeLayout.vue', 'block-tree-save-over'],
  ['blockTree/BlockTreeLayout.vue', 'block-tree-properties'],
  ['blockTree/BlockTreeNode.vue', "testIdOf('block-tree-node'"],
  ['blockTree/BlockTreeNode.vue', 'block-tree-node-add-inside'],
  ['blockTree/BlockTreeNode.vue', 'block-tree-node-duplicate'],
  ['blockTree/BlockTreeNode.vue', 'block-tree-node-remove'],
  ['blockTree/BlockPalette.vue', 'block-tree-palette'],
  ['blockTree/BlockPalette.vue', 'block-tree-palette-search'],
  ['blockTree/BlockPalette.vue', "testIdOf('block-tree-palette-type'"],
  ['blockTree/BlockPalette.vue', "testIdOf('block-tree-palette-preset'"],
  ['blockTree/BlockProperties.vue', "testIdOf('block-tree-revert'"],
]

describe('the key test ids', () => {
  it.each(KEY_TEST_IDS)('%s carries %s', (file, id) => {
    const source = readFileSync(join(__dirname, file), 'utf8')
    const written = id.startsWith('testIdOf(') ? source.includes(id) : source.includes(`data-testid="${id}"`) || source.includes(`:data-testid="${id}"`)
    expect(written, `${file}: ${id}`).toBe(true)
  })
})

describe('the test ids on what the kit draws', () => {
  afterEach(() => {
    useToast().clear()
    document.body.innerHTML = ''
  })

  it('names a toast by its level', async () => {
    const wrapper = mount(UidToastProvider, { attachTo: document.body })
    adminToast.success('Saved')
    adminToast.error('Failed')
    await wrapper.vm.$nextTick()
    expect(document.querySelector('[data-testid="toast-success"]')?.textContent).toContain('Saved')
    expect(document.querySelector('[data-testid="toast-error"]')?.textContent).toContain('Failed')
    wrapper.unmount()
  })

  it('names the rows of a table by the record key and the cells by the column', () => {
    const box = document.createElement('div')
    box.innerHTML = `<table><tbody>
      <tr><td class="uid-table__td--group">Group</td></tr>
      <tr class="uid-table__row"><td class="uid-table__td--select"></td><td>A</td><td></td></tr>
      <tr class="uid-table__row"><td class="uid-table__td--select"></td><td>B</td><td></td></tr>
    </tbody></table>`
    markTableRows(box, {
      rows: [{ id: 15 }, { id: 'a-7' }],
      keyOf: (row) => row.id as string | number,
      columns: ['firstName', '__row_actions__'],
      names: { __row_actions__: 'row-actions' },
    })
    const rows = box.querySelectorAll('tr.uid-table__row')
    expect(rows[0].getAttribute('data-testid')).toBe('resource-row-15')
    expect(rows[1].getAttribute('data-testid')).toBe('resource-row-a-7')
    expect(rows[1].getAttribute('data-row-key')).toBe('a-7')
    expect([...rows[0].children].map((td) => td.getAttribute('data-testid'))).toEqual([null, 'cell-first-name', 'row-actions'])
  })

  it('names a field by its name', () => {
    clearRegistry()
    registerBuiltinComponents()
    const Host = defineComponent({
      setup() {
        provideFormState({ first_name: 'Ann' })
        return () => h(FieldRenderer, { node: { type: 'text', name: 'first_name', label: 'Name' } })
      },
    })
    const wrapper = mount(Host)
    const field = wrapper.find('[data-testid="field-first-name"]')
    expect(field.exists()).toBe(true)
    expect((field.find('input').element as HTMLInputElement).value).toBe('Ann')
  })
})
