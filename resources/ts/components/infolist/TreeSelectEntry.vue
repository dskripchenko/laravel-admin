<script setup lang="ts">
/**
 * TreeSelectEntry — the view of a TreeSelect field: the chosen node's path
 * through the tree ("Electronics / Phones"), one line per value when several
 * are chosen.
 */
import { computed } from 'vue'
import { useEntryValue, isEmpty } from './entryValue'
import { findLabelPath, normalizeTree } from '../fields/support/tree'

interface Props {
  name?: string
  value?: unknown
  tree?: unknown
  separator?: string
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  name: '',
  value: undefined,
  tree: () => [],
  separator: ' / ',
  placeholder: '—',
})

const raw = useEntryValue(props)
const lines = computed<string[]>(() => {
  const v = raw.value
  if (isEmpty(v)) return []
  const tree = normalizeTree(props.tree)
  return (Array.isArray(v) ? v : [v]).map((x) => (findLabelPath(tree, x) ?? [String(x)]).join(props.separator))
})
</script>

<template>
  <span v-if="lines.length === 0" class="admin-infolist-text">{{ placeholder }}</span>
  <span v-else class="admin-infolist-text">{{ lines.join('\n') }}</span>
</template>
