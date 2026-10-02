<script setup lang="ts">
import { computed } from 'vue'
import { UidCard } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'

/**
 * The backend's IframeWidget::data() gives {src, height, sandbox}. Validating
 * the src against the allowed hosts is the host's business; the sandbox
 * attribute is passed through as it is. The default — the backend's too —
 * lets scripts, forms and pop-ups run under an opaque origin: adding
 * allow-same-origin to allow-scripts would let the frame lift its own sandbox,
 * which browsers warn about.
 */
interface Props {
  title?: string
  src?: string
  height?: number | null
  sandbox?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  src: '',
  height: null,
  sandbox: 'allow-scripts allow-forms allow-popups',
})

const frameStyle = computed(() => ({
  height: props.height ? `${props.height}px` : '320px',
}))
</script>

<template>
  <UidCard padding="md" class="admin-widget admin-widget--iframe">
    <header v-if="title" class="admin-widget__hd">
      <h3 class="admin-widget__title">{{ title }}</h3>
    </header>
    <iframe
      v-if="src"
      :src="src"
      :sandbox="sandbox"
      :style="frameStyle"
      class="admin-widget__iframe"
      loading="lazy"
      referrerpolicy="no-referrer"
    />
    <p v-else class="admin-widget__empty">{{ tr('Не задан src') }}</p>
  </UidCard>
</template>

<style scoped>
.admin-widget__iframe {
  width: 100%;
  /* A framed viewport: the embedded page scrolls inside it, and without the
     edge its content reads as cut off at the bottom. */
  border: 1px solid var(--uid-border-subtle, var(--uid-color-border, #e4e4e7));
  border-radius: var(--uid-radius-md, 8px);
  display: block;
  background: var(--uid-color-bg-subtle, transparent);
}
</style>
