/**
 * useResourceFormStore — state для Resource Create/Edit/View screens.
 *
 * Управляет:
 *   - mode ('create' | 'edit' | 'view')
 *   - record (raw из API), state (working copy в form-state, но мы держим
 *     reference на initial для unsaved-changes detection)
 *   - errors (field-keyed) — устанавливаются при ValidationError
 *   - loading/saving/deleting — для disable submit / show spinner
 *   - dirty (computed) — есть ли несохранённые изменения
 *
 * Endpoints (laravel-admin contract):
 *   GET    /resources/{slug}/read        — fetch one (id в filter[id])
 *   POST   /resources/{slug}/create
 *   POST   /resources/{slug}/update
 *   POST   /resources/{slug}/destroy
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import { ApiError, ValidationError } from '../api/errors'

export type FormMode = 'create' | 'edit' | 'view'

interface ReadResponse {
  data: Record<string, unknown>
}

interface SaveResponse {
  id: string | number
  data?: Record<string, unknown>
  redirect_url?: string
}

export const useResourceFormStore = defineStore('admin-resource-form', () => {
  const slug = ref<string | null>(null)
  const mode = ref<FormMode>('create')
  const recordId = ref<string | number | null>(null)

  /** Текущее состояние формы — мутируется через setField. */
  const state = ref<Record<string, unknown>>({})
  /** Snapshot изначальных значений (после load) — для dirty-detection. */
  const initial = ref<Record<string, unknown>>({})

  /** Field-keyed errors. Очищаются при successful save. */
  const errors = ref<Record<string, string[]>>({})

  const loading = ref(false)
  const saving = ref(false)
  const deleting = ref(false)
  const error = ref<Error | null>(null)

  const isCreate = computed(() => mode.value === 'create')
  const isEdit = computed(() => mode.value === 'edit')
  const isView = computed(() => mode.value === 'view')
  const hasError = computed(() => error.value !== null)

  /** Dirty: state отличается от initial хотя бы по одному ключу. */
  const isDirty = computed(() => {
    const a = state.value
    const b = initial.value
    const keys = new Set([...Object.keys(a), ...Object.keys(b)])
    for (const k of keys) {
      if (!Object.is(a[k], b[k])) {
        // Простое сравнение скаляров; объекты/массивы сравниваем JSON-сериализацией.
        if (typeof a[k] === 'object' || typeof b[k] === 'object') {
          if (JSON.stringify(a[k]) !== JSON.stringify(b[k])) return true
        } else {
          return true
        }
      }
    }
    return false
  })

  function reset(): void {
    state.value = {}
    initial.value = {}
    errors.value = {}
    loading.value = false
    saving.value = false
    deleting.value = false
    error.value = null
    recordId.value = null
  }

  /** Подготовить store для create-mode на ресурсе. */
  function prepareCreate(resourceSlug: string, defaults: Record<string, unknown> = {}): void {
    slug.value = resourceSlug
    mode.value = 'create'
    recordId.value = null
    state.value = { ...defaults }
    initial.value = { ...defaults }
    errors.value = {}
    error.value = null
  }

  /** Загрузить запись для edit либо view-режима. */
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
      const res = await client.get<ReadResponse>(`/resources/${resourceSlug}/read`, {
        params: { 'filter[id]': id },
      })
      state.value = { ...res.data }
      initial.value = { ...res.data }
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  /** Установить значение поля (через FormState composable обычно). */
  function setField(name: string, value: unknown): void {
    state.value[name] = value
    if (errors.value[name]) {
      // Очищаем ошибку конкретного поля при изменении (стандартный UX).
      const next = { ...errors.value }
      delete next[name]
      errors.value = next
    }
  }

  function setErrors(next: Record<string, string[]>): void {
    errors.value = { ...next }
  }

  function clearErrors(): void {
    errors.value = {}
  }

  /**
   * Сохранить. На create — POST /create; на edit — POST /update с id.
   * Возвращает new id (для post-create редиректа).
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
          ? `/resources/${slug.value}/create`
          : `/resources/${slug.value}/update`

      const payload =
        mode.value === 'create'
          ? state.value
          : { id: recordId.value, ...state.value }

      const res = await client.post<SaveResponse>(url, payload)
      const newId = res.id
      recordId.value = newId
      // После успешного save обновим initial = state, чтобы dirty=false.
      initial.value = { ...state.value }
      mode.value = 'edit'
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

  /** Удалить текущую запись (только в edit-mode). */
  async function destroy(): Promise<void> {
    if (!slug.value || recordId.value === null) {
      throw new Error('Nothing to delete')
    }
    if (deleting.value) throw new Error('Already deleting')

    deleting.value = true
    error.value = null
    try {
      const client = getAdminClient()
      await client.post(`/resources/${slug.value}/destroy`, { id: recordId.value })
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
    clearErrors,
    save,
    destroy,
  }
})
