<script setup lang="ts">
/**
 * The status dots of the top bar.
 *
 * The panel draws them; what they say comes from the backend, from whichever
 * plugins registered a StatusIndicator. That division is why this component
 * knows nothing about health checks or queues: a package must not have to ship
 * a Vue build of its own to put a dot in the header.
 *
 * Silent when there is nothing to say — an installation with no indicators
 * registered gets no empty slot in its header, and neither does one whose
 * endpoint failed. A header that reports its own inability to report is noise.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import type { Component } from 'vue'
import { UidIcon, UidTooltip } from '@dskripchenko/ui'
import { CircleAlert, CircleCheck, CircleHelp, TriangleAlert } from 'lucide-vue-next'
import { getAdminClient } from '../../../stores/registry'
import { trSafe as tr } from '../../../stores/i18n'

type StatusLevel = 'ok' | 'warning' | 'error' | 'unknown'

interface Indicator {
  key: string
  status: StatusLevel
  label: string
  detail?: string | null
  url?: string | null
}

/** How often to re-ask, in ms. A minute: this is a header, not a monitor. */
const POLL_MS = 60_000

const indicators = ref<Indicator[]>([])
let timer: ReturnType<typeof setInterval> | null = null

const icons: Record<StatusLevel, Component> = {
  ok: CircleCheck,
  warning: TriangleAlert,
  error: CircleAlert,
  unknown: CircleHelp,
}

/**
 * A green dot per indicator is a row of decorations nobody reads. Everything
 * healthy collapses into nothing at all — the header speaks only when
 * something is off.
 */
const visible = computed<Indicator[]>(() => indicators.value.filter((i) => i.status !== 'ok'))

async function load(): Promise<void> {
  try {
    const client = getAdminClient()
    const response = await client.get<{ indicators: Indicator[] }>('/system/status')
    indicators.value = Array.isArray(response?.indicators) ? response.indicators : []
  } catch {
    // A status endpoint that is unreachable says nothing rather than shouting:
    // it fails on every deploy restart, and a header that cries wolf during a
    // deploy is a header people learn to ignore.
    indicators.value = []
  }
}

onMounted(() => {
  void load()
  timer = setInterval(() => void load(), POLL_MS)
})

onBeforeUnmount(() => {
  if (timer !== null) clearInterval(timer)
  timer = null
})

function tooltip(indicator: Indicator): string {
  return indicator.detail ? `${indicator.label} — ${indicator.detail}` : indicator.label
}

/**
 * An in-panel address goes through the router, the same rule a screen's
 * message link follows: a full page load would throw away the panel's state
 * over what is, after all, a hint in the header.
 */
function isInternal(indicator: Indicator): boolean {
  return typeof indicator.url === 'string' && indicator.url.startsWith('/')
}
</script>

<template>
  <div v-if="visible.length > 0" class="admin-topbar__status" :aria-label="tr('Состояние системы')">
    <template v-for="indicator in visible" :key="indicator.key">
      <router-link
        v-if="isInternal(indicator)"
        :to="indicator.url!"
        class="admin-topbar__status-item"
        :class="`admin-topbar__status-item--${indicator.status}`"
        :data-status="indicator.status"
        :data-testid="`status-${indicator.key}`"
      >
        <UidTooltip :content="tooltip(indicator)">
          <span class="admin-topbar__status-inner">
            <UidIcon :icon="icons[indicator.status]" :size="16" />
            <span class="admin-topbar__status-label">{{ indicator.label }}</span>
          </span>
        </UidTooltip>
      </router-link>
      <component
        :is="indicator.url ? 'a' : 'span'"
        v-else
        :href="indicator.url ?? undefined"
        :target="indicator.url ? '_blank' : undefined"
        :rel="indicator.url ? 'noopener' : undefined"
        class="admin-topbar__status-item"
        :class="`admin-topbar__status-item--${indicator.status}`"
        :data-status="indicator.status"
        :data-testid="`status-${indicator.key}`"
      >
        <UidTooltip :content="tooltip(indicator)">
          <span class="admin-topbar__status-inner">
            <UidIcon :icon="icons[indicator.status]" :size="16" />
            <span class="admin-topbar__status-label">{{ indicator.label }}</span>
          </span>
        </UidTooltip>
      </component>
    </template>
  </div>
</template>

<style scoped>
.admin-topbar__status {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-1, 4px);
}

.admin-topbar__status-item {
  display: inline-flex;
  align-items: center;
  color: inherit;
  text-decoration: none;
}

.admin-topbar__status-inner {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-1, 4px);
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  line-height: 18px;
}

.admin-topbar__status-item--warning .admin-topbar__status-inner {
  color: var(--uid-color-warning-fg, #92400e);
  background: var(--uid-color-warning-bg, #fef3c7);
}

.admin-topbar__status-item--error .admin-topbar__status-inner {
  color: var(--uid-color-danger-fg, #991b1b);
  background: var(--uid-color-danger-bg, #fee2e2);
}

.admin-topbar__status-item--unknown .admin-topbar__status-inner {
  color: var(--uid-color-fg-muted, #6b7280);
  background: var(--uid-color-bg-subtle, #f3f4f6);
}

/* The word goes first on a narrow screen: the icon and its colour already
   carry the alarm, and the header has other things to fit. */
@media (max-width: 720px) {
  .admin-topbar__status-label {
    display: none;
  }
}
</style>
