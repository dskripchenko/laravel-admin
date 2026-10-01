<script setup lang="ts">
/**
 * CascaderEntry — the view of a Cascader field: the stored path as labels,
 * joined by the field's separator ("Russia / Moscow region / Moscow").
 */
import { computed } from 'vue'
import { useEntryValue } from './entryValue'
import { cascaderLabels, normalizeTree } from '../fields/support/tree'

interface Props {
  name?: string
  value?: unknown
  tree?: unknown
  levels?: Array<{ options?: unknown }> | null
  separator?: string
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  tree: () => [],
  levels: null,
  separator: ' / ',
  placeholder: '—',
})

const raw = useEntryValue(props)
const text = computed<string>(() => {
  const v = raw.value
  if (!Array.isArray(v) || v.length === 0) return props.placeholder
  let tree = normalizeTree(props.tree)
  if (tree.length === 0) tree = normalizeTree(props.levels?.[0]?.options)
  return cascaderLabels(tree, v).join(props.separator)
})
</script>

<template>
  <span class="admin-infolist-text">{{ text }}</span>
</template>
