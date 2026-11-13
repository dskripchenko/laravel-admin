<script setup lang="ts">
/**
 * One row of the block tree, and its children below it: the caption, the
 * marks (new, changed, not shown, outside the rules, an error), the actions
 * and the drop zones of a drag — before, inside, after — offered only where
 * the types allow the dragged block.
 */
import { computed, ref } from 'vue'
import { UidIcon } from '@dskripchenko/ui'
import { Box, ChevronDown, ChevronRight, Copy, GripVertical, Plus, Trash2 } from 'lucide-vue-next'
import { resolveIcon } from '../shell/iconRegistry'
import { trSafe as tr } from '../../stores/i18n'
import { useBlockTree } from './context'
import { allowedTypes, canMove, canPlace, nodeTitle, sameRef, type BlockNode, type BlockRef } from './tree'

defineOptions({ name: 'BlockTreeNode' })

const props = defineProps<{
  node: BlockNode
  parentRef: BlockRef | null
  parentType: string | null
  index: number
  depth: number
}>()

const { editor, rules, collapsed, toggle, dragging, openPalette } = useBlockTree()

const def = computed(() => rules().types[props.node.type])
const label = computed(() => def.value?.label ?? props.node.type)
const title = computed(() => nodeTitle(props.node, def.value))
const icon = computed(() => resolveIcon(def.value?.icon) ?? Box)
const key = computed(() => String(props.node.ref))
const isCollapsed = computed(() => collapsed.has(key.value))
const hasChildren = computed(() => (props.node.children?.length ?? 0) > 0)
const isSelected = computed(() => sameRef(editor.selectedRef.value, props.node.ref))
const status = computed(() => editor.diff.value.status.get(key.value) ?? null)
const errors = computed(() => editor.errors.value.get(key.value) ?? null)
const errorCount = computed(() =>
  errors.value ? errors.value.messages.length + Object.values(errors.value.fields).reduce((n, m) => n + m.length, 0) : 0,
)
const hidden = computed(
  () => props.node.meta?.rendersOnSite === false || (props.node.meta?.rendersOnSite === undefined && def.value?.rendersOnSite === false),
)
const unknown = computed(() => !def.value)
const outsideRules = computed(() => !unknown.value && !canPlace(rules(), props.parentType, props.node.type, props.depth))
const canAddInside = computed(() => editor.canEdit.value && allowedTypes(rules(), props.node.type, props.depth + 1).length > 0)

type Zone = 'before' | 'inside' | 'after'
const zone = ref<Zone | null>(null)

function onDragStart(event: DragEvent): void {
  if (!editor.canEdit.value) return
  dragging.value = props.node.ref
  event.dataTransfer?.setData('text/plain', key.value)
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onDragEnd(): void {
  dragging.value = null
  zone.value = null
}

function allowed(z: Zone): boolean {
  const moving = dragging.value
  if (moving === null || sameRef(moving, props.node.ref)) return false
  const nodes = editor.nodes.value
  return z === 'inside' ? canMove(nodes, rules(), moving, props.node.ref) : canMove(nodes, rules(), moving, props.parentRef)
}

function onDragOver(event: DragEvent): void {
  if (dragging.value === null) return
  const row = event.currentTarget as HTMLElement
  const rect = row.getBoundingClientRect()
  const y = rect.height > 0 ? (event.clientY - rect.top) / rect.height : 0.5
  const wanted: Zone = y < 0.3 ? 'before' : y > 0.7 ? 'after' : 'inside'
  const order: Zone[] = wanted === 'inside' ? ['inside', y < 0.5 ? 'before' : 'after'] : [wanted, 'inside']
  const found = order.find(allowed) ?? null
  zone.value = found
  if (found) {
    event.preventDefault()
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  }
}

function onDrop(event: DragEvent): void {
  const moving = dragging.value
  const z = zone.value
  zone.value = null
  dragging.value = null
  if (moving === null || z === null) return
  event.preventDefault()
  if (z === 'inside') {
    editor.move(moving, props.node.ref, props.node.children.length)
    collapsed.delete(key.value)
  } else {
    editor.move(moving, props.parentRef, z === 'before' ? props.index : props.index + 1)
  }
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    editor.select(props.node.ref)
  } else if ((event.key === 'Delete' || event.key === 'Backspace') && editor.canEdit.value) {
    event.preventDefault()
    editor.remove(props.node.ref)
  } else if (event.key === 'ArrowLeft' && hasChildren.value && !isCollapsed.value) {
    toggle(props.node.ref)
  } else if (event.key === 'ArrowRight' && isCollapsed.value) {
    toggle(props.node.ref)
  }
}
</script>

<template>
  <li class="admin-block-tree-node" role="none">
    <div
      class="admin-block-tree-node__row"
      :class="{
        'is-selected': isSelected,
        'is-dragging': dragging !== null && String(dragging) === key,
        [`is-drop-${zone}`]: zone !== null,
      }"
      role="treeitem"
      :aria-selected="isSelected"
      :aria-expanded="hasChildren ? !isCollapsed : undefined"
      :aria-level="depth"
      :data-ref="key"
      :style="{ paddingLeft: `${(depth - 1) * 16 + 4}px` }"
      tabindex="0"
      :draggable="editor.canEdit.value"
      @click="editor.select(node.ref)"
      @keydown="onKey"
      @dragstart="onDragStart"
      @dragend="onDragEnd"
      @dragover="onDragOver"
      @dragleave="zone = null"
      @drop="onDrop"
    >
      <span v-if="editor.canEdit.value" class="admin-block-tree-node__grip" aria-hidden="true">
        <UidIcon :icon="GripVertical" :size="14" />
      </span>
      <button
        v-if="hasChildren"
        type="button"
        class="admin-block-tree-node__toggle"
        :aria-label="isCollapsed ? tr('Развернуть') : tr('Свернуть')"
        @click.stop="toggle(node.ref)"
      >
        <UidIcon :icon="isCollapsed ? ChevronRight : ChevronDown" :size="14" />
      </button>
      <span v-else class="admin-block-tree-node__toggle" aria-hidden="true" />
      <UidIcon class="admin-block-tree-node__icon" :icon="icon" :size="15" />
      <span class="admin-block-tree-node__caption">
        <span class="admin-block-tree-node__title">{{ title || label }}</span>
        <span v-if="title" class="admin-block-tree-node__type">{{ label }}</span>
        <span v-if="isCollapsed && hasChildren" class="admin-block-tree-node__count">({{ node.children.length }})</span>
      </span>
      <span class="admin-block-tree-node__marks">
        <span v-if="errorCount > 0" class="admin-block-tree-mark is-error" :title="tr('Есть ошибки')">{{ errorCount }}</span>
        <span v-if="status === 'new'" class="admin-block-tree-mark is-new">{{ tr('новый') }}</span>
        <span v-else-if="status === 'changed'" class="admin-block-tree-mark is-changed">{{ tr('изменён') }}</span>
        <span v-if="unknown" class="admin-block-tree-mark is-warning">{{ tr('неизвестный тип') }}</span>
        <span v-else-if="outsideRules" class="admin-block-tree-mark is-warning">{{ tr('вне правил') }}</span>
        <span v-if="hidden" class="admin-block-tree-mark is-muted">{{ tr('не отображается') }}</span>
      </span>
      <span v-if="editor.canEdit.value" class="admin-block-tree-node__actions">
        <button
          v-if="canAddInside"
          type="button"
          :title="tr('Добавить внутрь')"
          :aria-label="tr('Добавить внутрь')"
          data-action="add-inside"
          @click.stop="openPalette({ parentRef: node.ref, index: node.children.length })"
        >
          <UidIcon :icon="Plus" :size="14" />
        </button>
        <button
          type="button"
          :title="tr('Дублировать')"
          :aria-label="tr('Дублировать')"
          data-action="duplicate"
          @click.stop="editor.duplicate(node.ref)"
        >
          <UidIcon :icon="Copy" :size="14" />
        </button>
        <button
          type="button"
          :title="tr('Удалить')"
          :aria-label="tr('Удалить')"
          data-action="remove"
          @click.stop="editor.remove(node.ref)"
        >
          <UidIcon :icon="Trash2" :size="14" />
        </button>
      </span>
    </div>
    <ul v-if="hasChildren && !isCollapsed" class="admin-block-tree-node__children" role="group">
      <BlockTreeNode
        v-for="(child, i) in node.children"
        :key="String(child.ref)"
        :node="child"
        :parent-ref="node.ref"
        :parent-type="node.type"
        :index="i"
        :depth="depth + 1"
      />
    </ul>
  </li>
</template>
