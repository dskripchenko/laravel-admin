/**
 * useNavigationStore — глобальный pending-counter для top-loading-bar.
 *
 * При router.beforeEach (старт навигации) — вызываем `start()`.
 * При router.afterEach + resource-page завершила data-fetch — вызываем `end()`.
 *
 * Counter (а не bool) защищает от race condition'ов: если несколько
 * параллельных fetch'ей, bar остаётся видимым пока хоть один pending.
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useNavigationStore = defineStore('admin-navigation', () => {
  const pending = ref<number>(0)
  const isLoading = computed<boolean>(() => pending.value > 0)

  function start(): void {
    pending.value += 1
  }

  function end(): void {
    if (pending.value > 0) pending.value -= 1
  }

  /** Сбросить counter (на случай зависшего pending в dev). */
  function reset(): void {
    pending.value = 0
  }

  return { pending, isLoading, start, end, reset }
})
