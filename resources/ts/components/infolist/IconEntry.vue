<script setup lang="ts">
/**
 * IconEntry — boolean flag rendered as a coloured icon + optional label.
 *
 * Looks up the lucide-vue-next icon by name (kebab-case → PascalCase
 * conversion to match the library's exports), so PHP-side
 *   IconEntry::make('flag')
 *       ->trueIcon('check-circle-2')
 *       ->falseIcon('x-circle')
 *       ->trueLabel('Активно')
 *       ->falseLabel('Отключено')
 * surfaces as an actual ✓ / ✗ instead of a blank placeholder.
 */
import { computed } from 'vue'
import * as LucideIcons from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { tryUseRecord } from './recordContext'

interface Props {
  name?: string
  label?: string
  value?: boolean | null
  /** lucide-vue-next icon name, e.g. 'check-circle-2'. */
  trueIcon?: string
  /** lucide-vue-next icon name, e.g. 'x-circle'. */
  falseIcon?: string
  trueLabel?: string
  falseLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  label: '',
  value: undefined,
  trueIcon: 'check-circle-2',
  falseIcon: 'circle',
  trueLabel: '',
  falseLabel: '',
})

const record = tryUseRecord()
const flag = computed<boolean>(() => {
  let v: unknown = props.value
  if (v === undefined && record && props.name) {
    v = record[props.name]
  }
  return Boolean(v)
})

function toPascal(name: string): string {
  return name
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join('')
}

const iconComponent = computed(() => {
  const key = toPascal(flag.value ? props.trueIcon : props.falseIcon)
  return (LucideIcons as Record<string, unknown>)[key]
})

const displayLabel = computed(() => (flag.value ? props.trueLabel : props.falseLabel))
</script>

<template>
  <span :class="['admin-infolist-icon', flag ? 'admin-infolist-icon--on' : 'admin-infolist-icon--off']">
    <UidIcon
      v-if="iconComponent"
      :icon="iconComponent as any"
      :size="14"
      class="admin-infolist-icon__glyph"
    />
    <span v-if="displayLabel">{{ displayLabel }}</span>
  </span>
</template>

<style>
.admin-infolist-icon {
  display: inline-flex;
  align-items: center;
  gap: var(--uid-space-xs);
  font-size: var(--uid-font-size-sm);
}
.admin-infolist-icon--on { color: var(--uid-success); }
.admin-infolist-icon--off { color: var(--uid-text-tertiary); }
.admin-infolist-icon__glyph {
  flex: none;
}
</style>
