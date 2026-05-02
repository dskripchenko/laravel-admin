<script setup lang="ts">
/**
 * Top loading bar (как у YouTube/GitHub/Vercel).
 *
 * Реактивно отображает useNavigationStore.isLoading. Indeterminate-anim
 * (CSS keyframes) — slide bar слева направо в бесконечном цикле пока
 * pending > 0.
 *
 * Hide-delay 120ms: если pending быстро спадает к 0, bar успевает
 * мигнуть в полный размер вместо instant-cutoff.
 */
import { computed, ref, watch } from 'vue'
import { useNavigationStore } from '../stores/navigation'

const nav = useNavigationStore()

// Soft-hide: bar остаётся 120ms после end() для плавности.
const visible = ref<boolean>(false)
let hideTimer: ReturnType<typeof setTimeout> | null = null

watch(
  () => nav.isLoading,
  (loading) => {
    if (loading) {
      if (hideTimer !== null) {
        clearTimeout(hideTimer)
        hideTimer = null
      }
      visible.value = true
    } else {
      hideTimer = setTimeout(() => {
        visible.value = false
        hideTimer = null
      }, 120)
    }
  },
  { immediate: true },
)

const cls = computed(() => ({
  'admin-loading-bar': true,
  'admin-loading-bar--visible': visible.value,
}))
</script>

<template>
  <div :class="cls" role="progressbar" aria-busy="true" />
</template>

<style>
.admin-loading-bar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  z-index: var(--uid-z-toast, 9999);
  pointer-events: none;
  opacity: 0;
  transition: opacity 120ms ease-out;
  background: transparent;
}

.admin-loading-bar--visible {
  opacity: 1;
}

.admin-loading-bar--visible::before {
  content: '';
  position: absolute;
  inset: 0;
  background: var(--uid-accent, var(--uid-color-primary, #14b8a6));
  transform-origin: 0 50%;
  animation: admin-loading-bar-slide 1.4s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes admin-loading-bar-slide {
  0% {
    transform: translateX(-100%) scaleX(0.4);
  }
  50% {
    transform: translateX(0%) scaleX(0.7);
  }
  100% {
    transform: translateX(100%) scaleX(0.4);
  }
}

/* Reduced-motion: статичный bar 60% width. */
@media (prefers-reduced-motion: reduce) {
  .admin-loading-bar--visible::before {
    animation: none;
    width: 60%;
    transform: none;
  }
}
</style>
