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

/**
 * Provides a form context scoped to one object-valued field of `parent` — the
 * backend's Field\Group, whose state is `{city: ..., street: ...}` under its
 * own name.
 *
 * Unlike NestedFieldsGroup this keeps no copy: every read and write goes to
 * `parent.state[name]`, and the errors are the parent's `name.child` keys —
 * the shape Laravel's validator reports them in — seen without the prefix.
 * Scopes nest: a group inside a group prefixes twice.
 */
export function provideScopedFormState(parent: FormStateContext, name: string): FormStateContext {
  const prefix = `${name}.`
  const obj = (): Record<string, unknown> => {
    const v = parent.getField(name)
    return v !== null && typeof v === 'object' && !Array.isArray(v) ? (v as Record<string, unknown>) : {}
  }
  const ownKeys = (): string[] =>
    Object.keys(parent.errors)
      .filter((k) => k.startsWith(prefix))
      .map((k) => k.slice(prefix.length))

  // A live view over the parent's errors: reads go through the parent's
  // reactive map, so a component reading `errors.city` re-renders when
  // `errors['address.city']` changes.
  const errors = new Proxy({} as Record<string, string[]>, {
    get: (_t, key) => (typeof key === 'string' ? parent.errors[prefix + key] : undefined),
    has: (_t, key) => typeof key === 'string' && prefix + key in parent.errors,
    ownKeys: () => ownKeys(),
    getOwnPropertyDescriptor: (_t, key) =>
      typeof key === 'string' && prefix + key in parent.errors
        ? { enumerable: true, configurable: true, value: parent.errors[prefix + key] }
        : undefined,
  })

  const ctx: FormStateContext = {
    get state() {
      return obj()
    },
    errors,
    mode: parent.mode,
    setField(key, value) {
      parent.setField(name, { ...obj(), [key]: value })
      parent.setError(prefix + key, null)
    },
    getField(key) {
      return obj()[key]
    },
    setError(key, messages) {
      parent.setError(prefix + key, messages)
    },
    setErrors(next) {
      for (const key of ownKeys()) parent.setError(prefix + key, null)
      for (const [key, messages] of Object.entries(next)) parent.setError(prefix + key, messages)
    },
    clearErrors() {
      for (const key of ownKeys()) parent.setError(prefix + key, null)
    },
  }

  provide(FormStateKey, ctx)
  return ctx
}
