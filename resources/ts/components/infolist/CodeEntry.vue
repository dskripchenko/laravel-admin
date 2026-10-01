<script setup lang="ts">
/**
 * CodeEntry — the view of a Code field: UidCode, read-only, with the field's
 * language, line numbers and a copy button.
 */
import { computed } from 'vue'
import { UidCode } from '@dskripchenko/ui'
import { useEntryValue, isEmpty } from './entryValue'

interface Props {
  name?: string
  value?: unknown
  language?: string | null
  lineNumbers?: boolean
  height?: number | string | null
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  language: null,
  lineNumbers: true,
  height: null,
  placeholder: '—',
})

const raw = useEntryValue(props)
const code = computed<string>(() => {
  const v = raw.value
  if (isEmpty(v)) return ''
  return typeof v === 'string' ? v : JSON.stringify(v, null, 2)
})
const maxHeight = computed<string>(() => {
  if (props.height === null || props.height === '') return '320px'
  return typeof props.height === 'number' ? `${props.height}px` : props.height
})
</script>

<template>
  <span v-if="code === ''" class="admin-infolist-text">{{ placeholder }}</span>
  <UidCode
    v-else
    class="admin-infolist-code"
    :code="code"
    :language="language ?? undefined"
    :line-numbers="lineNumbers"
    :max-height="maxHeight"
    copy
  />
</template>

<style>
.admin-infolist-code {
  width: 100%;
}
</style>
