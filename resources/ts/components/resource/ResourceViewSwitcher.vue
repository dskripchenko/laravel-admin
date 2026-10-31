<script setup lang="ts">
/**
 * ResourceViewSwitcher — the list | kanban | calendar toggle of a resource's
 * index page. It shows only the views the resource offers (the manifest's
 * `views`), the base one (list or tree) first.
 */
import { CalendarDays, List, ListTree, SquareKanban } from 'lucide-vue-next'
import { UidButton, UidIcon } from '@dskripchenko/ui'
import { useI18nStore } from '../../stores/i18n'

defineProps<{
  views: string[]
  modelValue: string
}>()

const emit = defineEmits<{ 'update:modelValue': [view: string] }>()

const i18n = useI18nStore()
const tr = (s: string): string => i18n.tr(s)

const ICONS = { list: List, tree: ListTree, kanban: SquareKanban, calendar: CalendarDays } as const

function label(view: string): string {
  switch (view) {
    case 'tree': return tr('Дерево')
    case 'kanban': return tr('Канбан')
    case 'calendar': return tr('Календарь')
    default: return tr('Список')
  }
}

function icon(view: string): (typeof ICONS)[keyof typeof ICONS] {
  return ICONS[view as keyof typeof ICONS] ?? List
}
</script>

<template>
  <div class="admin-view-switcher" role="group" :aria-label="tr('Вид')" data-testid="resource-view-switcher">
    <UidButton
      v-for="view in views"
      :key="view"
      size="md"
      :variant="view === modelValue ? 'secondary' : 'ghost'"
      :aria-pressed="view === modelValue"
      :title="label(view)"
      :data-view="view"
      @click="emit('update:modelValue', view)"
    >
      <template #prepend><UidIcon :icon="icon(view)" :size="14" /></template>
      <span class="admin-view-switcher__label">{{ label(view) }}</span>
    </UidButton>
  </div>
</template>

<style>
.admin-view-switcher {
  display: inline-flex;
  gap: var(--uid-space-2xs, 2px);
  padding: var(--uid-space-2xs, 2px);
  border: 1px solid var(--uid-color-border);
  border-radius: var(--uid-radius-md, 8px);
}
@media (max-width: 640px) {
  .admin-view-switcher__label {
    display: none;
  }
}
</style>
