import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AdminConfirmDialog from '../components/shell/AdminConfirmDialog.vue'
import { confirmDialog, confirmState, resolveConfirmDialog } from './useConfirm'

describe('useConfirm', () => {
  it('opens the dialog and resolves with the answer', async () => {
    const answer = confirmDialog({ message: 'Delete?', destructive: true })
    expect(confirmState.open).toBe(true)
    expect(confirmState.message).toBe('Delete?')
    expect(confirmState.destructive).toBe(true)
    resolveConfirmDialog(true)
    await expect(answer).resolves.toBe(true)
    expect(confirmState.open).toBe(false)
  })

  it('a second question answers the first with a no', async () => {
    const first = confirmDialog('One?')
    const second = confirmDialog('Two?')
    await expect(first).resolves.toBe(false)
    expect(confirmState.message).toBe('Two?')
    resolveConfirmDialog(false)
    await expect(second).resolves.toBe(false)
  })

  it('the dialog answers through its buttons', async () => {
    const wrapper = mount(AdminConfirmDialog, { attachTo: document.body })
    const answer = confirmDialog({ message: 'Revoke?' })
    await nextTick()
    expect(document.body.textContent).toContain('Revoke?')
    ;(document.querySelector('[data-testid="confirm-ok"]') as HTMLElement).click()
    await expect(answer).resolves.toBe(true)
    wrapper.unmount()
  })

  it('marks its overlay so it opens above a drawer mounted after it', async () => {
    // The kit gives modals and drawers one layer; the stylesheet raises the
    // overlay holding [data-admin-confirm] above them.
    const wrapper = mount(AdminConfirmDialog, { attachTo: document.body })
    const answer = confirmDialog({ message: 'Leave without saving?' })
    await nextTick()
    const marker = document.querySelector('[data-admin-confirm]')
    expect(marker).not.toBeNull()
    expect(marker!.closest('.uid-modal-overlay')).not.toBeNull()
    resolveConfirmDialog(false)
    await answer
    wrapper.unmount()
  })
})
