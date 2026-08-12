/**
 * useNavigationStore — the global pending counter behind the top loading bar.
 *
 * `start()` is called from router.beforeEach, as a navigation begins; `end()`
 * from router.afterEach and once a resource page has finished fetching.
 *
 * It is a counter rather than a boolean to survive the races: with several
 * fetches in flight the bar stays visible while at least one is pending.
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

  /** Resets the counter, in case a pending one gets stuck during development. */
  function reset(): void {
    pending.value = 0
  }

  return { pending, isLoading, start, end, reset }
})
