<script setup lang="ts">
/**
 * TabsLayout поверх UidTabs/UidTab/UidTabPanel.
 * Активная вкладка двусторонне связана через v-model:active.
 */
import { ref } from 'vue'
import { UidTabs, UidTab, UidTabPanel } from '@dskripchenko/ui'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

export interface TabNode {
  /** Уникальный ключ вкладки. Если не задан — берётся индекс. */
  key?: string
  /** Текст вкладки. */
  label: string
  icon?: string | null
  items: LayoutNode[]
}

interface Props {
  items: TabNode[]
  /** Активная вкладка (key либо индекс при отсутствии key). */
  active?: string | number
}

const props = withDefaults(defineProps<Props>(), { active: 0 })
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
      <LayoutRenderer
        v-for="(child, cidx) in tab.items"
        :key="cidx"
        :node="child"
      />
    </UidTabPanel>
  </UidTabs>
</template>
