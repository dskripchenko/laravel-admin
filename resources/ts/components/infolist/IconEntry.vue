<script setup lang="ts">
/**
 * IconEntry — boolean-флаг как иконка (true → check, false → x).
 * Опционально labels: '{ true: "Опубликовано", false: "Черновик" }'.
 */
import { computed } from 'vue'
import { tryUseRecord } from './recordContext'

interface Props {
  name?: string
  label?: string
  value?: boolean | null
  /** Имя icon'и для true (lucide-key, например 'check-circle-2'). */
  trueIcon?: string
  /** Имя icon'и для false. */
  falseIcon?: string
  /** Текстовые подписи. */
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
const displayLabel = computed(() => (flag.value ? props.trueLabel : props.falseLabel))
const iconName = computed(() => (flag.value ? props.trueIcon : props.falseIcon))
</script>

<template>
  <span :class="['admin-infolist-icon', flag ? 'admin-infolist-icon--on' : 'admin-infolist-icon--off']">
    <span class="admin-infolist-icon__glyph" :data-icon="iconName" />
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
  display: inline-block;
  width: 14px;
  height: 14px;
}
</style>
