import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { confirmState, confirmDialog, deleteWording, forceDeleteWording, resolveConfirmDialog } from './useConfirm'
import { useActionRunner, type AdminAction } from './useActionRunner'

describe('confirmation wording', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('names a delete question and its button after the delete', () => {
    void confirmDialog({ ...deleteWording(), message: 'Удалить строку?' })
    expect(confirmState.title).toBe('Удаление')
    expect(confirmState.confirmLabel).toBe('Удалить')
    expect(confirmState.destructive).toBe(true)
    resolveConfirmDialog(false)
  })

  it('names a permanent delete as such', () => {
    expect(forceDeleteWording()).toMatchObject({ title: 'Удаление навсегда', confirmLabel: 'Удалить навсегда' })
  })

  it('keeps the generic wording for a question that sets none', () => {
    void confirmDialog('Продолжить?')
    expect(confirmState.title).toBe('Подтверждение')
    expect(confirmState.confirmLabel).toBe('Подтвердить')
    resolveConfirmDialog(false)
  })

  it('titles and answers an action question with the action name', async () => {
    const runner = useActionRunner({ run: async () => ({}) } as never)
    const action = { name: 'cancel', label: 'Отменить заказ', type: 'button', confirm: 'Точно отменить?', destructive: true } as unknown as AdminAction
    const done = runner.run(action)
    await Promise.resolve()
    expect(runner.confirmState.title).toBe('Отменить заказ')
    expect(runner.confirmState.confirmLabel).toBe('Отменить заказ')
    expect(runner.confirmState.message).toBe('Точно отменить?')
    runner.resolveConfirm(false)
    expect(await done).toBe(false)
  })

  it('keeps an action’s own title and button', async () => {
    const runner = useActionRunner({ run: async () => ({}) } as never)
    const action = { name: 'x', label: 'X', type: 'button', confirm: { message: 'M', title: 'T', confirmLabel: 'Да' } } as unknown as AdminAction
    const done = runner.run(action)
    await Promise.resolve()
    expect(runner.confirmState.title).toBe('T')
    expect(runner.confirmState.confirmLabel).toBe('Да')
    runner.resolveConfirm(false)
    await done
  })
})
