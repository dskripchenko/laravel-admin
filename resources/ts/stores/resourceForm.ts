/**
 * useResourceFormStore — the state of a resource's create, edit and view
 * screens.
 *
 * It holds:
 *   - the mode ('create' | 'edit' | 'view')
 *   - the record as the API returned it, and the state — the working copy in
 *     the form state, alongside a reference to the initial values for
 *     unsaved-changes detection
 *   - the field-keyed errors, set from a ValidationError
 *   - loading, saving and deleting, which disable the submit and show spinners
 *   - dirty (computed): whether there are unsaved changes
 *
 * The endpoints, per the laravel-admin contract:
 *   GET    /{slug}/read         — fetches one record, with the id in the query
 *   POST   /{slug}/create
 *   POST   /{slug}/update
 *   POST   /{slug}/delete
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import { ApiError, ValidationError } from '../api/errors'
import { useManifestStore } from './manifest'

export type FormMode = 'create' | 'edit' | 'view'

interface ReadResponse {
  // The backend's ResourceController::read returns a payload of {record}.
  record: Record<string, unknown>
}

interface SaveResponse {
  // The backend's create and update return {record, redirect_url, message}.
  record?: Record<string, unknown>
  id?: string | number
  redirect_url?: string
  message?: string
}

export const useResourceFormStore = defineStore('admin-resource-form', () => {
  const slug = ref<string | null>(null)
  const mode = ref<FormMode>('create')
  const recordId = ref<string | number | null>(null)

  /** The form's current state, mutated through setField. */
  const state = ref<Record<string, unknown>>({})
  /** A snapshot of the initial values, taken after the load, for dirty detection. */
  const initial = ref<Record<string, unknown>>({})

  /** The field-keyed errors; cleared on a successful save. */
  const errors = ref<Record<string, string[]>>({})

  const loading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const error = ref<Error | null>(null)

  const isCreate = computed(() => mode.value === 'create')
  const isEdit = computed(() => mode.value === 'edit')
  const isView = computed(() => mode.value === 'view')
  const hasError = computed(() => error.value !== null)

  /** Dirty means the state differs from the initial values in at least one key. */
  const isDirty = computed(() => {
    const a = state.value
    const b = initial.value
    const keys = new Set([...Object.keys(a), ...Object.keys(b)])
    for (const k of keys) {
      if (!Object.is(a[k], b[k])) {
        // Scalars are compared directly; objects and arrays through their JSON.
        if (typeof a[k] === 'object' || typeof b[k] === 'object') {
          if (JSON.stringify(a[k]) !== JSON.stringify(b[k])) return true
        } else {
          return true
        }
      }
    }
    return false
  })

  /** An in-place mutation of the reactive object: it keeps the identity provide/inject relies on. */
  function replaceObject(target: Record<string, unknown>, next: Record<string, unknown>): void {
    for (const k of Object.keys(target)) delete target[k]
    Object.assign(target, next)
  }

  function reset(): void {
    replaceObject(state.value, {})
    replaceObject(initial.value, {})
    errors.value = {}
    loading.value = false
    saving.value = false
    deleting.value = false
    error.value = null
    recordId.value = null
  }

  /** Prepares the store for create mode on a resource. */
  function prepareCreate(resourceSlug: string, defaults: Record<string, unknown> = {}): void {
    slug.value = resourceSlug
    mode.value = 'create'
    recordId.value = null
    replaceObject(state.value, defaults)
    replaceObject(initial.value, defaults)
    errors.value = {}
    error.value = null
  }

  /** Loads a record for the edit or the view mode. */
  async function load(
    resourceSlug: string,
    id: string | number,
    targetMode: 'edit' | 'view' = 'edit',
  ): Promise<void> {
    slug.value = resourceSlug
    mode.value = targetMode
    recordId.value = id
    loading.value = true
    error.value = null
    errors.value = {}

    try {
      const client = getAdminClient()
      const res = await client.get<ReadResponse>(`/${resourceSlug}/read`, {
        params: { id },
      })
      replaceObject(state.value, res.record)
      replaceObject(initial.value, res.record)
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  /** Sets a field's value — usually through the FormState composable. */
  function setField(name: string, value: unknown): void {
    state.value[name] = value
    if (errors.value[name]) {
      // Clear that field's error as it changes, as one expects.
      const next = { ...errors.value }
      delete next[name]
      errors.value = next
    }
  }

  function setErrors(next: Record<string, string[]>): void {
    errors.value = { ...next }
  }

  /**
   * Seeds the fields' defaults (Field::default() from the manifest) on a
   * create form. It writes both into the state and into the initial values, so
   * that a default does not count as an unsaved change. Values that are
   * already set — a query pre-fill — are left alone.
   */
  function seedDefaults(values: Record<string, unknown>): void {
    for (const [name, value] of Object.entries(values)) {
      if (state.value[name] === undefined) {
        state.value[name] = value
        initial.value[name] = value
      }
    }
  }

  function clearErrors(): void {
    errors.value = {}
  }

  /**
   * Saves: POST /create in create mode, POST /update with the id in edit mode.
   * Returns the new id, for the redirect after a create.
   */
  async function save(): Promise<string | number> {
    if (!slug.value) throw new Error('useResourceFormStore.save() before slug set')
    if (saving.value) throw new Error('Already saving')

    saving.value = true
    error.value = null
    errors.value = {}

    try {
      const client = getAdminClient()
      const url =
        mode.value === 'create'
          ? `/${slug.value}/create`
          : `/${slug.value}/update`

      const payload =
        mode.value === 'create'
          ? state.value
          : { id: recordId.value, ...state.value }

      const res = await client.post<SaveResponse>(url, payload)
      // The backend returns `record: {id, ...}`; the older `{id}` shape is still accepted.
      const newId = (res.record?.id ?? res.id) as string | number | undefined
      if (newId === undefined) {
        throw new Error('save: backend response does not contain record.id')
      }
      recordId.value = newId
      // After a successful save, initial becomes the state so that dirty is false.
      replaceObject(initial.value, { ...state.value })
      mode.value = 'edit'
      // The fields' DB-driven options are serialized into the manifest, so
      // after a mutation we drop its cache — otherwise the selects (a group's
      // parent and the like) stay stale until a full page reload.
      void useManifestStore().refresh().catch(() => undefined)
      return newId
    } catch (err) {
      if (err instanceof ValidationError) {
        errors.value = { ...err.fields }
      } else if (err instanceof ApiError) {
        error.value = err
      } else if (err instanceof Error) {
        error.value = err
      }
      throw err
    } finally {
      saving.value = false
    }
  }

  /** Deletes the current record; edit mode only. */
  async function destroy(): Promise<void> {
    if (!slug.value || recordId.value === null) {
      throw new Error('Nothing to delete')
    }
    if (deleting.value) throw new Error('Already deleting')

    deleting.value = true
    error.value = null
    try {
      const client = getAdminClient()
      await client.post(`/${slug.value}/delete`, { id: recordId.value })
      void useManifestStore().refresh().catch(() => undefined)
    } catch (err) {
      if (err instanceof Error) error.value = err
      throw err
    } finally {
      deleting.value = false
    }
  }

  return {
    // state
    slug,
    mode,
    recordId,
    state,
    initial,
    errors,
    loading,
    saving,
    deleting,
    error,
    // getters
    isCreate,
    isEdit,
    isView,
    isDirty,
    hasError,
    // actions
    reset,
    prepareCreate,
    load,
    setField,
    setErrors,
    seedDefaults,
    clearErrors,
    save,
    destroy,
  }
})
