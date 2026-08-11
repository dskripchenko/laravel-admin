import { describe, it, expect } from 'vitest'
import { computed, ref } from 'vue'
import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { useShellVisibility } from './useShellVisibility'

function route(name: string, meta: Record<string, unknown> = {}) {
  return { name, meta } as unknown as RouteLocationNormalizedLoaded
}

describe('useShellVisibility', () => {
  it('treats a catch-all match as a 404 only after boot resolved', () => {
    const ready = ref(false)
    const { settledNotFound, useShell } = useShellVisibility(
      route('admin.notFound'),
      computed(() => ready.value),
    )

    // Booting: the dynamic routes are not registered yet, so this tells us
    // nothing about the address. The shell stays and the gate inside it draws
    // the skeleton — the same thing any other deep link shows.
    expect(settledNotFound.value).toBe(false)
    expect(useShell.value).toBe(true)

    // The manifest arrived and the address is still unmatched: a real 404,
    // drawn bare, without the shell.
    ready.value = true
    expect(settledNotFound.value).toBe(true)
    expect(useShell.value).toBe(false)
  })

  it('keeps ordinary routes inside the shell whatever the boot state', () => {
    for (const ready of [false, true]) {
      const { useShell } = useShellVisibility(
        route('admin.screen.beta-feedback'),
        computed(() => ready),
      )

      expect(useShell.value).toBe(true)
    }
  })

  it('never wraps auth and fullscreen routes', () => {
    const ready = computed(() => true)

    expect(useShellVisibility(route('admin.login', { kind: 'auth' }), ready).useShell.value)
      .toBe(false)
    expect(useShellVisibility(route('admin.preview', { fullscreen: true }), ready).useShell.value)
      .toBe(false)
  })
})
