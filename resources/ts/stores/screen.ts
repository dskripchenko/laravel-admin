/**
 * useScreenStore — state для произвольных Screen (custom forms / pages вне CRUD).
 *
 * Управляет:
 *   - slug + state (working copy формы — провайдится через provideFormState)
 *   - layout / commandBar / name / description (snapshot из backend `state` action)
 *   - errors (field-keyed) — устанавливаются при ValidationError из runMethod
 *   - loading / running — UX-флаги
 *
 * Endpoints (laravel-admin contract):
 *   GET    /{slug}/state              — compile() snapshot
 *   POST   /{slug}/runMethod          — диспатч command-метода
 *
 * Не кэширует ответы между slug'ами — переключение на другой screen
 * сбрасывает state. Сохранение dirty между screen'ами не нужно (custom
 * forms обычно atomic submit, не resume-able black box).
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import { ApiError, ValidationError } from '../api/errors'

export interface ScreenLayoutNode {
  kind: 'layout' | 'field'
  type?: string
  name?: string
  children?: ScreenLayoutNode[]
  [key: string]: unknown
}

export interface ScreenAction {
  kind: 'action'
  name: string
  label: string
  type: string
  icon?: string | null
  primary?: boolean
  destructive?: boolean
  permission?: string | null
  confirm?: { message: string; title?: string } | null
  position?: string[]
  attributes?: Record<string, unknown>
}

export interface ScreenStateSnapshot {
  state: Record<string, unknown>
  name: string
  description: string | null
  layout: ScreenLayoutNode[]
  command_bar: ScreenAction[]
  permissions: string[]
  etag: string
}

export interface ScreenMethodResult {
  state?: Record<string, unknown>
  message?: string
  alerts?: Array<{ type: string; message: string; duration_ms?: number }>
  redirect_url?: string | null
  refresh?: boolean
  download_url?: string | null
  extra?: Record<string, unknown>
}

export const useScreenStore = defineStore('admin-screen', () => {
  const slug = ref<string | null>(null)
  const name = ref<string>('')
  const description = ref<string | null>(null)
  const layout = ref<ScreenLayoutNode[]>([])
  const commandBar = ref<ScreenAction[]>([])
  const permissions = ref<string[]>([])
  const etag = ref<string | null>(null)

  /** Working copy формы — провайдится через provideFormState. */
  const state = ref<Record<string, unknown>>({})
  /** Initial state из последнего state-fetch'а (для reset). */
  const initial = ref<Record<string, unknown>>({})
  /** Field-keyed errors из ValidationException. */
  const errors = ref<Record<string, string[]>>({})

  const loading = ref(false)
  const running = ref(false)
  const error = ref<Error | null>(null)
  const lastMessage = ref<string | null>(null)

  const hasError = computed(() => error.value !== null)

  /** In-place мутация — сохраняет identity для provide/inject reactive proxy. */
  function replaceObject(target: Record<string, unknown>, next: Record<string, unknown>): void {
    for (const k of Object.keys(target)) delete target[k]
    Object.assign(target, next)
  }

  /**
   * Нормализует layout-tree для frontend LayoutRenderer'а.
   *
   * Backend Layout::toArray() кладёт детей в `children`, frontend layout-компоненты
   * (Rows/Columns/Section/Tabs) ждут `items`. Делаем алиас рекурсивно — без
   * мутации backend-формата.
   */
  function normalizeLayoutTree(nodes: ScreenLayoutNode[]): ScreenLayoutNode[] {
    return nodes.map((node) => normalizeLayoutNode(node))
  }

  function normalizeLayoutNode(node: ScreenLayoutNode): ScreenLayoutNode {
    const next: ScreenLayoutNode = { ...node }
    if (Array.isArray(node.children) && node.items === undefined) {
      next.items = (node.children as ScreenLayoutNode[]).map((child) =>
        typeof child === 'object' && child !== null ? normalizeLayoutNode(child) : child,
      )
    } else if (Array.isArray(node.items)) {
      next.items = (node.items as ScreenLayoutNode[]).map((child) =>
        typeof child === 'object' && child !== null ? normalizeLayoutNode(child) : child,
      )
    }
    // Backend кладёт type-specific поля в `props`. Распакуем их на верхний
    // уровень — frontend layouts читают props напрямую как props компонента.
    if (node.props && typeof node.props === 'object') {
      Object.assign(next, node.props)
    }
    return next
  }

  function reset(): void {
    slug.value = null
    name.value = ''
    description.value = null
    layout.value = []
    commandBar.value = []
    permissions.value = []
    etag.value = null
    replaceObject(state.value, {})
    replaceObject(initial.value, {})
    errors.value = {}
    loading.value = false
    running.value = false
    error.value = null
    lastMessage.value = null
  }

  /** Загрузить screen-snapshot. */
  async function load(screenSlug: string, params?: Record<string, unknown>): Promise<void> {
    slug.value = screenSlug
    loading.value = true
    error.value = null
    errors.value = {}

    try {
      const client = getAdminClient()
      const res = await client.get<ScreenStateSnapshot>(`/${screenSlug}/state`, {
        params,
      })
      name.value = res.name
      description.value = res.description
      layout.value = normalizeLayoutTree(res.layout)
      commandBar.value = res.command_bar
      permissions.value = res.permissions
      etag.value = res.etag
      replaceObject(state.value, res.state)
      replaceObject(initial.value, res.state)
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Диспатчит command-метод Screen'а. Передаёт текущий state как `payload`.
   * При success — обновляет state из ответа, ставит lastMessage.
   * При ValidationError — заполняет errors.
   * При прочей ошибке — ставит error и пробрасывает.
   */
  async function runMethod(
    method: string,
    overridePayload?: Record<string, unknown>,
  ): Promise<ScreenMethodResult> {
    if (!slug.value) {
      throw new Error('useScreenStore.runMethod() before slug set')
    }
    if (running.value) {
      throw new Error('Already running a method')
    }

    running.value = true
    error.value = null
    errors.value = {}
    lastMessage.value = null

    try {
      const client = getAdminClient()
      const payload = overridePayload ?? state.value
      const res = await client.post<ScreenMethodResult>(`/${slug.value}/runMethod`, {
        method,
        payload,
      })

      if (res.state && Object.keys(res.state).length > 0) {
        replaceObject(state.value, res.state)
        replaceObject(initial.value, { ...res.state })
      }
      if (res.message) {
        lastMessage.value = res.message
      }
      if (res.refresh) {
        // Сервер попросил перезагрузить snapshot — делаем lazy reload.
        await load(slug.value).catch(() => undefined)
      }
      return res
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
      running.value = false
    }
  }

  function setField(name: string, value: unknown): void {
    state.value[name] = value
    if (errors.value[name]) {
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

  return {
    // state
    slug,
    name,
    description,
    layout,
    commandBar,
    permissions,
    etag,
    state,
    initial,
    errors,
    loading,
    running,
    error,
    lastMessage,
    // getters
    hasError,
    // actions
    reset,
    load,
    runMethod,
    setField,
    setErrors,
    clearErrors,
  }
})
