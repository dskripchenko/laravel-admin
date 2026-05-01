/**
 * Composable для form-state: предоставляет state + ошибки через provide/inject.
 *
 * Контейнер (Resource form / Settings page) вызывает `provideFormState()`,
 * Field-компоненты в дереве — `useFormState()` для чтения значения и
 * мутации поля.
 *
 * State — reactive proxy; setField мутирует ключ. Errors — отдельный
 * reactive Map (field-name → string[] messages).
 */

import { inject, provide, reactive, type InjectionKey } from 'vue'

export interface FormStateContext {
  state: Record<string, unknown>
  errors: Record<string, string[]>
  setField: (name: string, value: unknown) => void
  getField: (name: string) => unknown
  setError: (name: string, messages: string[] | null) => void
  setErrors: (next: Record<string, string[]>) => void
  clearErrors: () => void
}

const FormStateKey: InjectionKey<FormStateContext> = Symbol('admin.form-state')

/**
 * Создаёт form-context и provid'ит его потомкам.
 *
 * Переданный `initial`-object оборачивается в `reactive()` — мутации stora
 * видны и снаружи (caller владеет state'ом и может его читать после submit'а).
 *
 * @param initial Начальные значения state'а.
 * @param initialErrors Начальные ошибки (для повторного render'а после
 *                      ValidationError).
 */
export function provideFormState(
  initial: Record<string, unknown> = {},
  initialErrors: Record<string, string[]> = {},
): FormStateContext {
  const state = reactive(initial)
  const errors = reactive<Record<string, string[]>>({ ...initialErrors })

  const ctx: FormStateContext = {
    state,
    errors,
    setField(name, value) {
      ;(state as Record<string, unknown>)[name] = value
      // Очистить ошибки этого поля при изменении — стандартный UX.
      if (errors[name]) {
        delete errors[name]
      }
    },
    getField(name) {
      return (state as Record<string, unknown>)[name]
    },
    setError(name, messages) {
      if (messages === null || messages.length === 0) {
        delete errors[name]
      } else {
        errors[name] = messages
      }
    },
    setErrors(next) {
      for (const key of Object.keys(errors)) delete errors[key]
      Object.assign(errors, next)
    },
    clearErrors() {
      for (const key of Object.keys(errors)) delete errors[key]
    },
  }

  provide(FormStateKey, ctx)
  return ctx
}

/**
 * Получить form-context. Throws, если не вызван внутри `provideFormState()`.
 */
export function useFormState(): FormStateContext {
  const ctx = inject(FormStateKey)
  if (!ctx) {
    throw new Error('useFormState() called outside of provideFormState() scope')
  }
  return ctx
}

/** Опционально получить (null если нет). */
export function tryUseFormState(): FormStateContext | null {
  return inject(FormStateKey, null)
}
