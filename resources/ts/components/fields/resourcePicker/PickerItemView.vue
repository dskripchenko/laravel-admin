<script setup lang="ts">
/**
 * One picked record: its preview (or a placeholder), title and subtitle. The
 * field, the dialog and the view page draw records with it, so a record looks
 * the same everywhere.
 *
 * `tile` stacks the preview over the text, for the dialog's grid; `row` puts
 * them side by side.
 */
import { ref, watch } from 'vue'
import { FileText } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import type { PickerItem } from './pickerApi'

interface Props {
  item: PickerItem
  variant?: 'tile' | 'row'
}

const props = withDefaults(defineProps<Props>(), { variant: 'row' })

// A broken image URL falls back to the placeholder instead of the browser's
// broken-image glyph.
const failed = ref(false)
watch(() => props.item.preview, () => {
  failed.value = false
})
</script>

<template>
  <div class="admin-picker-item" :class="`admin-picker-item--${variant}`">
    <div class="admin-picker-item__thumb">
      <img
        v-if="item.preview && !failed"
        :src="item.preview"
        :alt="item.title"
        loading="lazy"
        @error="failed = true"
      />
      <UidIcon v-else :icon="FileText" :size="variant === 'tile' ? 28 : 18" />
    </div>
    <div class="admin-picker-item__text">
      <span class="admin-picker-item__title" :title="item.title">{{ item.title }}</span>
      <span v-if="item.subtitle" class="admin-picker-item__subtitle" :title="item.subtitle">{{ item.subtitle }}</span>
    </div>
  </div>
</template>

<style>
.admin-picker-item {
  display: flex;
  gap: var(--uid-space-sm, 8px);
  min-width: 0;
}
.admin-picker-item--row {
  align-items: center;
}
.admin-picker-item--tile {
  flex-direction: column;
}
.admin-picker-item__thumb {
  flex: none;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: var(--uid-radius-sm, 4px);
  background: var(--uid-color-bg-subtle, #f3f4f6);
  color: var(--uid-color-text-tertiary, #9ca3af);
}
.admin-picker-item--row .admin-picker-item__thumb {
  width: 40px;
  height: 40px;
}
.admin-picker-item--tile .admin-picker-item__thumb {
  width: 100%;
  aspect-ratio: 4 / 3;
}
.admin-picker-item__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.admin-picker-item__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.admin-picker-item__title,
.admin-picker-item__subtitle {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.admin-picker-item__title {
  font-size: var(--uid-font-size-sm, 13px);
  color: var(--uid-color-text-primary, inherit);
}
.admin-picker-item__subtitle {
  font-size: var(--uid-font-size-xs, 12px);
  color: var(--uid-color-text-secondary, #62686f);
}
</style>
