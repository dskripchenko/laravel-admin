/**
 * Menu store: дерево пунктов сайдбара.
 *
 * Источник — /system/menu (backend строит из ResourceRegistry/ScreenRegistry/
 * Settings + plugin-вкладов). Фронт фильтрует по permission'ам залогиненного
 * пользователя через auth-store.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAdminClient } from './registry'
import { useAuthStore } from './auth'

export interface MenuItem {
  /** Уникальный идентификатор. */
  key: string
  /** Текст пункта. */
  label: string
  /** Имя icon'а (опционально — host-проект сам резолвит icon-set). */
  icon?: string | null
  /** URL (внутренний router-target) либо null для группы. */
  url?: string | null
  /** Имя router-route (если задано — приоритет над url). */
  routeName?: string | null
  /** Бейдж справа (число unread, ярлык вроде "new"). */
  badge?: string | number | null
  /** Группа (header в sidebar). */
  group?: string | null
  /** Сортировочный вес. */
  order?: number
  /** Permission-keys; если заданы — пункт виден только при hasAnyPermission. */
  permissions?: string[]
  /** Вложенные пункты. */
  children?: MenuItem[]
}

export interface MenuGroup {
  /** Заголовок группы (null = пункты без группы). */
  group: string | null
  items: MenuItem[]
}

interface MenuResponse {
  items: MenuItem[]
}

export const useMenuStore = defineStore('admin-menu', () => {
  const items = ref<MenuItem[]>([])
  const loading = ref(false)
  const error = ref<Error | null>(null)
  const isLoaded = ref(false)

  /**
   * Видимые пункты — отфильтрованные по permission'ам через auth-store.
   * Поддерживает wildcards (`*`, `admin.users.*`) через auth.hasAnyPermission.
   * Items без permissions считаются открытыми всем.
   */
  const visibleItems = computed<MenuItem[]>(() => {
    const auth = useAuthStore()
    const filter = (it: MenuItem): MenuItem | null => {
      if (Array.isArray(it.permissions) && it.permissions.length > 0) {
        if (!auth.hasAnyPermission(it.permissions)) return null
      }
      const filteredChildren = (it.children ?? [])
        .map(filter)
        .filter((c): c is MenuItem => c !== null)
      return { ...it, children: filteredChildren }
    }
    return items.value.map(filter).filter((i): i is MenuItem => i !== null)
  })

  /**
   * Группированный список — для рендера в sidebar по секциям.
   * Сортировка: order по возрастанию, затем label по алфавиту.
   */
  const groupedItems = computed<MenuGroup[]>(() => {
    const groups = new Map<string | null, MenuItem[]>()
    for (const item of visibleItems.value) {
      const groupKey = item.group ?? null
      if (!groups.has(groupKey)) groups.set(groupKey, [])
      groups.get(groupKey)!.push(item)
    }
    const result: MenuGroup[] = []
    for (const [group, list] of groups) {
      list.sort((a, b) => {
        const orderDiff = (a.order ?? 0) - (b.order ?? 0)
        if (orderDiff !== 0) return orderDiff
        return a.label.localeCompare(b.label)
      })
      result.push({ group, items: list })
    }
    return result
  })

  /** Загрузить меню с backend'а. Кэшируется до reset()/force=true. */
  async function load(force = false): Promise<MenuItem[]> {
    if (isLoaded.value && !force) return items.value
    loading.value = true
    error.value = null
    try {
      const client = getAdminClient()
      const response = await client.get<MenuResponse>('/system/menu')
      items.value = Array.isArray(response.items) ? response.items : []
      isLoaded.value = true
      return items.value
    } catch (err) {
      error.value = err instanceof Error ? err : new Error(String(err))
      throw err
    } finally {
      loading.value = false
    }
  }

  /** Установить items напрямую (например, host-проект построил из manifest'а). */
  function setItems(next: MenuItem[]): void {
    items.value = next
    isLoaded.value = true
    error.value = null
  }

  function reset(): void {
    items.value = []
    isLoaded.value = false
    error.value = null
    loading.value = false
  }

  return {
    items,
    loading,
    error,
    isLoaded,
    visibleItems,
    groupedItems,
    load,
    setItems,
    reset,
  }
})
