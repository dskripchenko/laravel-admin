import { computed, onMounted, onUnmounted, ref, type ComputedRef } from 'vue'
import { useManifestStore } from '../stores/manifest'
import { useMenuStore } from '../stores/menu'

/**
 * Один гейт готовности каркаса: меню и страница появляются вместе.
 *
 * Замер на живом стенде показал двухтактную загрузку: на 236 мс отрисован
 * топбар с пустым сайдбаром и страница, которой в этой установке нет
 * (HomePage-заглушка «зарегистрируйте DashboardScreen»), и только на 510 мс
 * приходит manifest — меню наполняется, страница подменяется настоящей.
 * Четверть секунды пользователь смотрел на чужой экран, а потом всё
 * скачком переставлялось.
 *
 * Манифест и меню запрашиваются параллельно, поэтому ждать оба дешевле, чем
 * показывать полусобранный интерфейс. Гейт открывается по готовности обоих —
 * либо по страховочному таймауту: зависший запрос меню не имеет права
 * держать страницу вечно.
 */
export const BOOT_GATE_TIMEOUT_MS = 1500

export function useAppReady(): ComputedRef<boolean> {
  const manifest = useManifestStore()
  const menu = useMenuStore()
  const expired = ref(false)
  let timer: ReturnType<typeof setTimeout> | null = null

  onMounted(() => {
    timer = setTimeout(() => {
      expired.value = true
    }, BOOT_GATE_TIMEOUT_MS)
  })
  onUnmounted(() => {
    if (timer !== null) clearTimeout(timer)
  })

  return computed<boolean>(() => expired.value || (manifest.bootResolved && !menu.loading))
}
