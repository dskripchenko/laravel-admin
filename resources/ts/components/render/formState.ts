/**
 * The form-state composable: it exposes the state and the errors through
 * provide/inject.
 *
 * The container — a resource form, a settings page — calls
 * `provideFormState()`, and the field components in the tree call
 * `useFormState()` to read a value and change a field.
 *
 * The state is a reactive proxy that setField mutates by key; the errors are a
 * separate reactive map of field name → string[] messages.
 */

import { inject, provide, reactive, type InjectionKey } from 'vue'

export interface FormStateContext {
  state: Record<string, unknown>
  errors: Record<string, string[]>
  /** The form's context: FieldRenderer hides the fields with visibility[mode]=false. */
  mode?: 'create' | 'update' | 'view'
  setField: (name: string, value: unknown) => void
  getField: (name: string) => unknown
  setError: (name: string, messages: string[] | null) => void
  setErrors: (next: Record<string, string[]>) => void
  clearErrors: () => void
}

const FormStateKey: InjectionKey<FormStateContext> = Symbol('admin.form-state')

/**
 * Creates the form context and provides it to the descendants.
 *
 * The `initial` object given is wrapped into `reactive()`, so the mutations are
 * visible from outside too: the caller owns the state and may read it after a
 * submit.
 *
 * @param initial The state's initial values.
 * @param initialErrors The initial errors, for a re-render after a
 *                      ValidationError.
 */
export function provideFormState(
  initial: Record<string, unknown> = {},
  initialErrors: Record<string, string[]> = {},
  mode?: 'create' | 'update' | 'view',
): FormStateContext {
  const state = reactive(initial)
  const errors = reactive<Record<string, string[]>>({ ...initialErrors })

  const ctx: FormStateContext = {
    state,
    errors,
    mode,
    setField(name, value) {
      ;(state as Record<string, unknown>)[name] = value
      // Clear that field's errors as it changes, as one expects.
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
 * Returns the form context. It throws when called outside a
 * `provideFormState()`.
 */
export function useFormState(): FormStateContext {
  const ctx = inject(FormStateKey)
  if (!ctx) {
    throw new Error('useFormState() called outside of provideFormState() scope')
  }
  return ctx
}

/** The optional form: null when there is none. */
export function tryUseFormState(): FormStateContext | null {
  return inject(FormStateKey, null)
}
