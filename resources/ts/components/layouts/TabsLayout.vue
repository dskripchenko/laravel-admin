<script setup lang="ts">
/**
 * TabsLayout, built on UidTabs, UidTab and UidTabPanel.
 * The active tab is bound both ways through v-model:active.
 */
import { ref } from 'vue'
import { UidTabs, UidTab, UidTabPanel, UidStack } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

export interface TabNode {
  /** The tab's unique key; without one, its index is used. */
  key?: string
  /** The tab's text. */
  label: string
  icon?: string | null
  items: LayoutNode[]
}

interface Props {
  items: TabNode[]
  /** The active tab — its key, or its index when there is no key. */
  active?: string | number
  /**
   * The gap between the elements inside a tab.
   *
   * A tab panel used to lay its children out with NO stack: the fields ran
   * together at a gap of zero. Inside `Rows` the very same fields breathed
   * (`--uid-space-md`), while on a tab the next field's label stuck to the
   * previous one's hint — it read as one continuous ribbon, with no telling
   * where one field ended and the next began.
   *
   * The value is one step above the line rhythm: a tab is a set of sets, and
   * the distance between them should be more noticeable than the distance
   * inside a single field.
   */
  gap?: string
}

const props = withDefaults(defineProps<Props>(), { active: 0, gap: 'var(--uid-space-lg)' })
const emit = defineEmits<{ 'update:active': [value: string | number] }>()

function tabKey(tab: TabNode, idx: number): string | number {
  return tab.key ?? idx
}

const localActive = ref<string | number>(props.active ?? tabKey(props.items[0] ?? { label: '' }, 0))

function onUpdate(value: string | number): void {
  localActive.value = value
  emit('update:active', value)
}
</script>

<template>
  <UidTabs :model-value="localActive" @update:model-value="onUpdate">
    <template #list>
      <UidTab
        v-for="(tab, idx) in items"
        :key="tabKey(tab, idx)"
        :value="tabKey(tab, idx)"
      >
        {{ tab.label }}
      </UidTab>
    </template>

    <UidTabPanel
      v-for="(tab, idx) in items"
      :key="`panel-${tabKey(tab, idx)}`"
      :value="tabKey(tab, idx)"
    >
      <UidStack direction="column" :gap="gap" align="stretch">
        <LayoutRenderer
          v-for="(child, cidx) in tab.items"
          :key="cidx"
          :node="child"
        />
      </UidStack>
    </UidTabPanel>
  </UidTabs>
</template>
