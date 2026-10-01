<script setup lang="ts">
/**
 * The Infolist layout — read-only entries on any screen, not only on a
 * resource's view page.
 *
 * The entries go through InfolistRenderer and the infolist entry registry, the
 * same as on ResourceViewPage. They read the record from the nearest
 * provideRecord(); a screen provides its state as the record. Inside a form
 * with no record the form's state stands in, so an infolist can show the
 * values being edited next to it.
 *
 * `layout`: 'rows' (the default) stacks the entries, 'columns' puts them side
 * by side in one row, 'grid' flows them into `columns` columns (2 by default).
 */
import { computed } from 'vue'
import { UidGrid, UidStack } from '@dskripchenko/ui'
import InfolistRenderer from '../infolist/InfolistRenderer.vue'
import type { InfolistNode } from '../infolist/InfolistRenderer.vue'
import { provideRecord, tryUseRecord } from '../infolist/recordContext'
import { tryUseFormState } from '../render/formState'

defineOptions({ inheritAttrs: false })

interface Props {
  items?: InfolistNode[]
  layout?: 'rows' | 'columns' | 'grid' | string | null
  columns?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  layout: 'rows',
  columns: null,
})

if (!tryUseRecord()) {
  provideRecord(tryUseFormState()?.state ?? {})
}

const gridCols = computed<number>(() => {
  if (props.layout === 'columns') return Math.max(1, props.items.length)
  if (props.layout === 'grid') return props.columns && props.columns > 0 ? props.columns : 2
  return 1
})
</script>

<template>
  <UidGrid
    v-if="gridCols > 1"
    :cols="gridCols"
    gap="var(--uid-space-md)"
    class="admin-infolist-layout admin-infolist-layout--grid"
  >
    <InfolistRenderer v-for="(entry, idx) in items" :key="idx" :node="entry" />
  </UidGrid>
  <!-- No gap: the entries draw their own dividers between neighbours. -->
  <UidStack
    v-else
    direction="column"
    gap="0"
    align="stretch"
    class="admin-infolist-layout admin-infolist-layout--rows"
  >
    <InfolistRenderer v-for="(entry, idx) in items" :key="idx" :node="entry" />
  </UidStack>
</template>
