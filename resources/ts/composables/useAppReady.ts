import { computed, onMounted, onUnmounted, ref, type ComputedRef } from 'vue'
import { useManifestStore } from '../stores/manifest'
import { useMenuStore } from '../stores/menu'

/**
 * One readiness gate for the whole shell: the menu and the page appear
 * together.
 *
 * Measured on the live stand, the load came in two beats: at 236 ms a topbar
 * with an empty sidebar and a page that does not exist in this installation
 * (the HomePage placeholder saying "register a DashboardScreen"), and only at
 * 510 ms the manifest — the menu fills in and the real page replaces the
 * placeholder. For a quarter of a second one looked at the wrong screen, and
 * then everything jumped into place.
 *
 * The manifest and the menu are fetched in parallel, so waiting for both costs
 * less than showing a half-assembled interface. The gate opens when both are
 * ready — or on a safety timeout, since a hung menu request has no right to
 * hold the page forever.
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
