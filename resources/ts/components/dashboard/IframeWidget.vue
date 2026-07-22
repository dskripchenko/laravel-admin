<script setup lang="ts">
import { computed } from 'vue'
import { UidCard } from '@dskripchenko/ui'

/**
 * Backend IframeWidget::data() — {src, height, sandbox}. Host отвечает за
 * allowedHosts-валидацию src на своей стороне; sandbox-атрибут пробрасываем
 * как есть (default backend'а: allow-scripts allow-same-origin).
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
  sandbox: 'allow-scripts allow-same-origin',
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
    <p v-else class="admin-widget__empty">Не задан src</p>
  </UidCard>
</template>

<style scoped>
.admin-widget__iframe {
  width: 100%;
  border: 0;
  border-radius: var(--uid-radius-md, 8px);
  background: var(--uid-color-bg-subtle, transparent);
}
</style>
