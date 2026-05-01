<script setup lang="ts">
/**
 * Tabs: горизонтальные вкладки. Каждый item — это tab `{label, items}`.
 * Активная вкладка — первая по умолчанию или ?tab из URL'а (host-проект
 * может проследить через v-model:active).
 */
import { ref } from 'vue'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

export interface TabNode {
  label: string
  key?: string
  icon?: string | null
  items: LayoutNode[]
}

interface Props {
  items: TabNode[]
  /** Index активной вкладки. Если не задан — first. */
  active?: number
}

const props = withDefaults(defineProps<Props>(), { active: 0 })
const emit = defineEmits<{ 'update:active': [value: number] }>()

const localActive = ref(props.active)

function setActive(index: number): void {
  localActive.value = index
  emit('update:active', index)
}
</script>

<template>
  <div class="admin-layout-tabs">
    <ul class="admin-layout-tabs__list" role="tablist">
      <li v-for="(tab, idx) in items" :key="tab.key ?? idx" role="presentation">
        <button
          type="button"
          :class="[
            'admin-layout-tabs__tab',
            { 'admin-layout-tabs__tab--active': idx === localActive },
          ]"
          role="tab"
          :aria-selected="idx === localActive"
          @click="setActive(idx)"
        >
          {{ tab.label }}
        </button>
      </li>
    </ul>
    <div class="admin-layout-tabs__panel" role="tabpanel">
      <LayoutRenderer
        v-for="(child, idx) in items[localActive]?.items ?? []"
        :key="idx"
        :node="child"
      />
    </div>
  </div>
</template>

<style>
.admin-layout-tabs__list {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--admin-border, #e5e7eb);
}
.admin-layout-tabs__tab {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 13px;
  color: var(--admin-muted, #6b7280);
}
.admin-layout-tabs__tab:hover { color: var(--admin-text, #111827); }
.admin-layout-tabs__tab--active {
  color: var(--admin-text, #111827);
  border-bottom-color: var(--admin-accent, #3b82f6);
  font-weight: 500;
}
</style>
