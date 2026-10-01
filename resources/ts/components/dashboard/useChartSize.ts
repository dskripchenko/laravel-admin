import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/**
 * Tracks an element's content box, so a chart is laid out in real pixels —
 * text and stroke widths stay true at any widget size, unlike a stretched
 * viewBox. Without ResizeObserver (jsdom, very old browsers) the fallback size
 * stands.
 */
export function useChartSize(
  el: Ref<HTMLElement | null>,
  fallback: { width: number; height: number },
): { width: Ref<number>; height: Ref<number> } {
  const width = ref(fallback.width)
  const height = ref(fallback.height)
  let observer: ResizeObserver | null = null

  function apply(w: number, h: number): void {
    // A collapsed box (display:none, not yet laid out) keeps the last size.
    if (w > 0) width.value = Math.round(w)
    if (h > 0) height.value = Math.round(h)
  }

  onMounted(() => {
    if (!el.value) return
    const rect = el.value.getBoundingClientRect()
    apply(rect.width, rect.height)
    if (typeof ResizeObserver === 'undefined') return
    observer = new ResizeObserver((entries) => {
      const box = entries[0]?.contentRect
      if (box) apply(box.width, box.height)
    })
    observer.observe(el.value)
  })

  onBeforeUnmount(() => {
    observer?.disconnect()
    observer = null
  })

  return { width, height }
}
