<script setup lang="ts">
/**
 * The overlay of action buttons on top of a widget in the edit mode:
 *   ☰ — the drag handle for reordering
 *   ⚙ — the widget's settings
 *   × — remove
 */
import { GripVertical, Settings, X } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { trSafe as tr } from '../../stores/i18n'

defineEmits<{
  configure: []
  remove: []
}>()
</script>

<template>
  <div class="admin-widget-actions" role="toolbar" :aria-label="tr('Действия с виджетом')">
    <button
      type="button"
      class="admin-widget-actions__btn admin-widget-actions__drag"
      :aria-label="tr('Перетащить')"
      :title="tr('Перетащить')"
      data-drag-handle="true"
    >
      <UidIcon :icon="GripVertical" :size="14" />
    </button>
    <button
      type="button"
      class="admin-widget-actions__btn"
      :aria-label="tr('Настройки')"
      :title="tr('Настройки')"
      @click="$emit('configure')"
    >
      <UidIcon :icon="Settings" :size="14" />
    </button>
    <button
      type="button"
      class="admin-widget-actions__btn admin-widget-actions__btn--danger"
      :aria-label="tr('Удалить')"
      :title="tr('Удалить')"
      @click="$emit('remove')"
    >
      <UidIcon :icon="X" :size="14" />
    </button>
  </div>
</template>

<style>
.admin-widget-actions {
  position: absolute;
  top: 8px;
  right: 8px;
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 2px;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  box-shadow: var(--uid-shadow-sm);
  z-index: 5;
}
.admin-widget-actions__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  color: var(--uid-text-secondary);
  cursor: pointer;
}
.admin-widget-actions__btn:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}
.admin-widget-actions__btn--danger:hover {
  background: color-mix(in srgb, var(--uid-color-danger, #dc2626) 14%, transparent);
  color: var(--uid-color-danger, #dc2626);
}
.admin-widget-actions__drag {
  cursor: grab;
}
.admin-widget-actions__drag:active {
  cursor: grabbing;
}
</style>
