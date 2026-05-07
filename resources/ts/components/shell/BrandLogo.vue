<script setup lang="ts">
/**
 * LAdmin brand mark — "Terminal Block".
 * Vue-порт docs/design_handoff_laravel_admin/brand/Logo.tsx.
 *
 * Палитра — zinc-900 фон + teal-400 ">_" с мигающим курсором.
 * Все внутренние размеры скейлятся от prop `size`, так что mark
 * остаётся чётким при любом px (28 для sidebar, 40 на login и т.д.).
 *
 * При prefers-reduced-motion курсор не анимируется (см. CSS ниже).
 */
import { computed } from 'vue'

interface Props {
  /** Сторона квадрата в px. */
  size?: number
  /** Мигающий курсор. */
  animated?: boolean
  /** color = бренд-палитра, mono = single-tone через currentColor. */
  variant?: 'color' | 'mono'
  title?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 28,
  animated: true,
  variant: 'color',
  title: 'LAdmin',
})

const TEAL = '#2dd4bf'
const INK = '#18181b'

const radius = computed(() => Math.round(props.size * 0.25))
const fontSize = computed(() => Math.round(props.size * 0.42))
const padX = computed(() => Math.round(props.size * 0.18))
const cursorW = computed(() => Math.max(2, Math.round(props.size * 0.18)))
const cursorH = computed(() => Math.round(props.size * 0.3))
const cursorGap = computed(() => Math.max(1, Math.round(props.size * 0.04)))

const bg = computed(() => (props.variant === 'mono' ? 'currentColor' : INK))
const fg = computed(() =>
  props.variant === 'mono' ? 'var(--ladmin-logo-fg, #fff)' : TEAL,
)

const wrapStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  padding: `0 ${padX.value}px`,
  borderRadius: `${radius.value}px`,
  background: bg.value,
  color: fg.value,
  fontSize: `${fontSize.value}px`,
}))

const cursorStyle = computed(() => ({
  width: `${cursorW.value}px`,
  height: `${cursorH.value}px`,
  marginLeft: `${cursorGap.value}px`,
  background: fg.value,
}))
</script>

<template>
  <span
    role="img"
    :aria-label="title"
    class="ladmin-logo"
    :class="{ 'ladmin-logo--animated': animated }"
    :style="wrapStyle"
  >
    <span aria-hidden="true">&gt;_</span>
    <span aria-hidden="true" class="ladmin-logo__cursor" :style="cursorStyle" />
  </span>
</template>

<style>
.ladmin-logo {
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  font-family: ui-monospace, 'IBM Plex Mono', 'Fira Code', Menlo, monospace;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1;
  flex: none;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
}
.ladmin-logo--animated .ladmin-logo__cursor {
  animation: ladmin-cursor 1.1s steps(1, end) infinite;
}
@keyframes ladmin-cursor {
  50% { opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .ladmin-logo--animated .ladmin-logo__cursor { animation: none; }
}
</style>
