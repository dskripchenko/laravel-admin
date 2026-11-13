<script setup lang="ts">
/**
 * The block tree editor — `Layout::blockTree(...)`: the tree, the preview and
 * the properties of the selected block, with the draft's bar below. The tree
 * is a value of the screen's state (`source`); the draft, publish and discard
 * go to the screen's methods. See useBlockTreeEditor and preview.ts.
 *
 * Loaded on first use: it is a chunk of its own, not part of the main bundle.
 */
import { computed, reactive, ref } from 'vue'
import { UidAlert, UidButton, UidIcon } from '@dskripchenko/ui'
import { ChevronRight, Plus, Undo2 } from 'lucide-vue-next'
import { trSafe as tr, intlLocale, currentLocale } from '../../stores/i18n'
import BlockTreeNode from './BlockTreeNode.vue'
import BlockPalette from './BlockPalette.vue'
import BlockPreview from './BlockPreview.vue'
import BlockProperties from './BlockProperties.vue'
import { provideBlockTree, type PaletteTarget } from './context'
import { useBlockTreeEditor } from './useBlockTreeEditor'
import { allowedTypes, findNode, locate, makeRules, nodeTitle, type BlockRef, type BlockTypeDef } from './tree'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  source: string
  title?: string | null
  types?: BlockTypeDef[]
  rootTypes?: string[] | null
  maxDepth?: number | null
  previewUrl?: string | null
  previewParams?: Record<string, unknown> | null
  markerAttribute?: string
  autosaveDelay?: number
  previewDelay?: number
  height?: string | null
  draftMethod?: string | null
  publishMethod?: string | null
  discardMethod?: string | null
  readOnly?: boolean
}>(), {
  title: null,
  types: () => [],
  rootTypes: null,
  maxDepth: null,
  previewUrl: null,
  previewParams: null,
  markerAttribute: 'data-builder-ref',
  autosaveDelay: 1500,
  previewDelay: 700,
  height: null,
  draftMethod: null,
  publishMethod: null,
  discardMethod: null,
  readOnly: false,
})

const rules = computed(() => makeRules(props.types, props.rootTypes, props.maxDepth))

const editor = useBlockTreeEditor({
  source: props.source,
  rules: () => rules.value,
  draftMethod: () => props.draftMethod,
  publishMethod: () => props.publishMethod,
  discardMethod: () => props.discardMethod,
  autosaveDelay: () => props.autosaveDelay,
  readOnly: () => props.readOnly,
})

const collapsed = reactive(new Set<string>())
const dragging = ref<BlockRef | null>(null)
const palette = ref<PaletteTarget | null>(null)

function toggle(ref: BlockRef): void {
  const key = String(ref)
  if (collapsed.has(key)) collapsed.delete(key)
  else collapsed.add(key)
}

function openPalette(target: PaletteTarget): void {
  if (editor.canEdit.value) palette.value = target
}

provideBlockTree({ editor, rules: () => rules.value, collapsed, toggle, dragging, openPalette })

const paletteParent = computed(() => {
  const target = palette.value
  if (!target || target.parentRef === null) return null
  return locate(editor.nodes.value, target.parentRef)
})
const paletteTypes = computed<BlockTypeDef[]>(() => {
  if (!palette.value) return []
  const parent = paletteParent.value
  return allowedTypes(rules.value, parent?.node.type ?? null, parent ? parent.depth + 1 : 1)
})
const palettePlace = computed(() => {
  const parent = paletteParent.value?.node
  return parent ? labelOf(parent.type) : null
})

function pick(def: BlockTypeDef): void {
  const target = palette.value
  palette.value = null
  if (!target) return
  if (target.parentRef !== null) collapsed.delete(String(target.parentRef))
  editor.add(def, target.parentRef, target.index)
}

/** "Add after": the palette for the selected block's level, right after it. */
function addAfterSelected(): void {
  const ref = editor.selectedRef.value
  const at = ref === null ? null : locate(editor.nodes.value, ref)
  if (!at) openPalette({ parentRef: null, index: editor.nodes.value.length })
  else openPalette({ parentRef: at.parent?.ref ?? null, index: at.index + 1 })
}

function labelOf(type: string): string {
  return rules.value.types[type]?.label ?? type
}

function captionOf(ref: BlockRef): string {
  const node = findNode(editor.nodes.value, ref)
  if (!node) return ''
  return nodeTitle(node, rules.value.types[node.type]) || labelOf(node.type)
}

/** Selecting from the preview unfolds the way to the node. */
function selectFromPreview(ref: string): void {
  if (!editor.select(ref)) return
  for (const n of editor.path.value.slice(0, -1)) collapsed.delete(String(n.ref))
  const rows = document.querySelectorAll<HTMLElement>('.admin-block-tree__tree [data-ref]')
  const row = [...rows].find((r) => r.dataset.ref === ref)
  row?.scrollIntoView?.({ block: 'nearest' })
}

/** The properties' form is remounted when the node's data object is replaced (undo, reload). */
const ids = new WeakMap<object, number>()
let nextId = 0
const propertiesKey = computed(() => {
  const node = editor.selected.value
  if (!node) return ''
  const data = node.data as object | null
  if (data && !ids.has(data)) ids.set(data, ++nextId)
  return `${String(node.ref)}:${data ? ids.get(data) : 0}`
})

const previewUrl = computed(() => {
  const own = editor.value.value.previewUrl
  return typeof own === 'string' && own !== '' ? own : props.previewUrl
})

const statusText = computed(() => {
  const changes = editor.diff.value.changes
  const count = changes > 0 ? ` · ${tr('изменений')}: ${changes}` : ''
  switch (editor.status.value) {
    case 'saving':
      return tr('Сохранение черновика…')
    case 'publishing':
      return tr('Публикация…')
    case 'error':
      return tr('Черновик не сохранён') + count
    case 'dirty':
      return (props.draftMethod ? tr('Есть несохранённые изменения') : tr('Изменения не опубликованы')) + count
    case 'saved':
      return (editor.savedAt.value ? `${tr('Черновик сохранён')} ${timeOf(editor.savedAt.value)}` : tr('Черновик сохранён')) + count
    default:
      return changes > 0 ? tr('Изменения не опубликованы') + count : tr('Изменений нет')
  }
})

function timeOf(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleTimeString(intlLocale(currentLocale()), { hour: '2-digit', minute: '2-digit' })
}

function differenceText(d: string | { ref?: BlockRef | null; label?: string; change?: string }): string {
  if (typeof d === 'string') return d
  const who = d.label ?? (d.ref !== undefined && d.ref !== null ? captionOf(d.ref) || String(d.ref) : '')
  return [who, d.change].filter(Boolean).join(' — ')
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z' && !event.shiftKey) {
    const tag = (event.target as HTMLElement | null)?.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || (event.target as HTMLElement | null)?.isContentEditable) return
    if (editor.undo()) event.preventDefault()
  }
}

const boxStyle = computed(() => ({ height: props.height ?? 'calc(100vh - 220px)' }))
</script>

<template>
  <section class="admin-block-tree" :data-source="source" @keydown="onKeydown">
    <h3 v-if="title" class="admin-block-tree__title">{{ title }}</h3>
    <UidAlert
      v-if="editor.conflict.value"
      class="admin-block-tree__conflict"
      variant="warning"
      :title="tr('Публикация остановлена')"
      dismissible
      @dismiss="editor.conflict.value = null"
    >
      <p>{{ editor.conflict.value.message }}</p>
      <ul v-if="editor.conflict.value.differences.length > 0">
        <li v-for="(d, i) in editor.conflict.value.differences" :key="i">{{ differenceText(d) }}</li>
      </ul>
    </UidAlert>
    <div class="admin-block-tree__panes" :style="boxStyle">
      <div class="admin-block-tree__pane admin-block-tree__tree">
        <div class="admin-block-tree__pane-head">
          <strong>{{ tr('Структура') }}</strong>
          <UidButton
            v-if="editor.undoLabel.value"
            size="sm"
            variant="ghost"
            :title="`${tr('Вернуть')}: ${editor.undoLabel.value}`"
            data-action="undo"
            @click="editor.undo()"
          >
            <template #prepend><UidIcon :icon="Undo2" :size="14" /></template>
            {{ tr('Вернуть') }}
          </UidButton>
        </div>
        <ul class="admin-block-tree__nodes" role="tree" :aria-label="tr('Структура')">
          <BlockTreeNode
            v-for="(node, i) in editor.nodes.value"
            :key="String(node.ref)"
            :node="node"
            :parent-ref="null"
            :parent-type="null"
            :index="i"
            :depth="1"
          />
        </ul>
        <p v-if="editor.nodes.value.length === 0" class="admin-block-tree__empty">{{ tr('Блоков пока нет') }}</p>
        <div v-if="editor.canEdit.value" class="admin-block-tree__add">
          <UidButton size="sm" variant="secondary" data-action="add" @click="addAfterSelected">
            <template #prepend><UidIcon :icon="Plus" :size="14" /></template>
            {{ tr('Добавить блок') }}
          </UidButton>
        </div>
        <BlockPalette
          v-if="palette"
          class="admin-block-tree__palette"
          :types="paletteTypes"
          :place="palettePlace"
          @pick="pick"
          @close="palette = null"
        />
      </div>
      <div class="admin-block-tree__pane admin-block-tree__preview">
        <BlockPreview
          :url="previewUrl"
          :params="previewParams"
          :nodes="editor.nodes.value"
          :selected-ref="editor.selectedRef.value"
          :marker-attribute="markerAttribute"
          :delay="previewDelay"
          @select="selectFromPreview"
        />
      </div>
      <div class="admin-block-tree__pane admin-block-tree__props">
        <template v-if="editor.selected.value">
          <nav class="admin-block-tree__path" :aria-label="tr('Путь до блока')">
            <button type="button" class="admin-block-tree__crumb" @click="editor.select(null)">{{ title || tr('Страница') }}</button>
            <template v-for="n in editor.path.value" :key="String(n.ref)">
              <UidIcon :icon="ChevronRight" :size="12" />
              <button
                type="button"
                class="admin-block-tree__crumb"
                :aria-current="n === editor.selected.value ? 'true' : undefined"
                @click="editor.select(n.ref)"
              >
                {{ captionOf(n.ref) }}
              </button>
            </template>
          </nav>
          <BlockProperties
            :key="propertiesKey"
            :node="editor.selected.value"
            :def="rules.types[editor.selected.value.type]"
            :errors="editor.errors.value.get(String(editor.selected.value.ref)) ?? null"
            :read-only="!editor.canEdit.value"
          />
        </template>
        <p v-else class="admin-block-tree__empty">{{ tr('Выберите блок в структуре или в предпросмотре') }}</p>
      </div>
    </div>
    <footer class="admin-block-tree__bar">
      <span class="admin-block-tree__status" :data-status="editor.status.value" role="status">{{ statusText }}</span>
      <span class="admin-block-tree__bar-actions">
        <UidButton
          v-if="editor.canEdit.value"
          size="sm"
          variant="secondary"
          data-action="discard"
          :disabled="editor.diff.value.changes === 0 && editor.status.value !== 'saved'"
          @click="editor.discard()"
        >
          {{ tr('Отменить') }}
        </UidButton>
        <UidButton
          v-if="editor.canPublish.value"
          size="sm"
          variant="primary"
          data-action="publish"
          :loading="editor.status.value === 'publishing'"
          :disabled="editor.status.value === 'publishing'"
          @click="editor.publish()"
        >
          {{ tr('Опубликовать') }}
        </UidButton>
      </span>
    </footer>
  </section>
</template>

<style>
.admin-block-tree { display: flex; flex-direction: column; gap: var(--uid-space-sm, 8px); margin: 0 0 var(--uid-space-md, 12px); min-width: 0; }
.admin-block-tree__title { margin: 0; font-size: var(--uid-font-size-md, 15px); }
.admin-block-tree__panes {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) minmax(320px, 2.2fr) minmax(260px, 1.2fr);
  min-height: 480px;
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md, 8px);
  background: var(--uid-surface-base, #fff);
  overflow: hidden;
}
.admin-block-tree__pane { position: relative; min-width: 0; min-height: 0; overflow: auto; }
.admin-block-tree__pane + .admin-block-tree__pane { border-left: 1px solid var(--uid-border-subtle); }
.admin-block-tree__preview { overflow: hidden; display: flex; flex-direction: column; }
.admin-block-tree__props { padding: var(--uid-space-sm, 8px) var(--uid-space-md, 12px); }
.admin-block-tree__pane-head {
  position: sticky; top: 0; z-index: 1;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  padding: 6px 8px; min-height: 36px;
  background: var(--uid-surface-base, #fff);
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-block-tree__nodes, .admin-block-tree-node__children { list-style: none; margin: 0; padding: 0; }
.admin-block-tree__nodes { padding: 4px 0; }
.admin-block-tree__empty { color: var(--uid-text-secondary); padding: 8px 12px; margin: 0; }
.admin-block-tree__add { padding: 4px 8px 12px; }
.admin-block-tree__palette {
  position: absolute; inset: 36px 6px auto 6px; z-index: 3; max-height: calc(100% - 48px);
}
.admin-block-tree-node__row {
  display: flex; align-items: center; gap: 4px;
  padding-top: 3px; padding-bottom: 3px; padding-right: 6px;
  cursor: pointer; border-top: 2px solid transparent; border-bottom: 2px solid transparent;
  outline: none;
}
.admin-block-tree-node__row:hover { background: var(--uid-color-surface-hover, rgba(0, 0, 0, 0.04)); }
.admin-block-tree-node__row:focus-visible { box-shadow: inset 0 0 0 2px var(--uid-focus-ring, #2f80ed); }
.admin-block-tree-node__row.is-selected { background: var(--uid-accent-subtle, rgba(47, 128, 237, 0.12)); }
.admin-block-tree-node__row.is-dragging { opacity: 0.45; }
.admin-block-tree-node__row.is-drop-before { border-top-color: var(--uid-accent, #2f80ed); }
.admin-block-tree-node__row.is-drop-after { border-bottom-color: var(--uid-accent, #2f80ed); }
.admin-block-tree-node__row.is-drop-inside { box-shadow: inset 0 0 0 2px var(--uid-accent, #2f80ed); }
.admin-block-tree-node__grip { color: var(--uid-text-secondary); cursor: grab; display: inline-flex; }
.admin-block-tree-node__toggle {
  display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; flex: none;
  border: 0; padding: 0; background: none; color: inherit; cursor: pointer;
}
.admin-block-tree-node__icon { flex: none; color: var(--uid-text-secondary); }
.admin-block-tree-node__caption { display: flex; align-items: baseline; gap: 6px; min-width: 0; flex: 1; }
.admin-block-tree-node__title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.admin-block-tree-node__type, .admin-block-tree-node__count {
  color: var(--uid-text-secondary); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.admin-block-tree-node__marks { display: inline-flex; gap: 3px; flex: none; }
.admin-block-tree-mark {
  font-size: 11px; line-height: 16px; padding: 0 5px; border-radius: 8px;
  background: var(--uid-color-bg-subtle, rgba(0, 0, 0, 0.06)); color: var(--uid-text-secondary);
}
.admin-block-tree-mark.is-new { background: var(--uid-success-subtle, #e3f6e8); color: var(--uid-success, #1e7b34); }
.admin-block-tree-mark.is-changed { background: var(--uid-info-subtle, #e5efff); color: var(--uid-info, #1f5fbf); }
.admin-block-tree-mark.is-warning { background: var(--uid-warning-subtle, #fff3dc); color: var(--uid-warning, #8a5a00); }
.admin-block-tree-mark.is-error { background: var(--uid-danger-subtle, #fde7e7); color: var(--uid-danger, #b42318); }
.admin-block-tree-node__actions { display: none; gap: 2px; flex: none; }
.admin-block-tree-node__row:hover .admin-block-tree-node__actions,
.admin-block-tree-node__row.is-selected .admin-block-tree-node__actions,
.admin-block-tree-node__row:focus-within .admin-block-tree-node__actions { display: inline-flex; }
.admin-block-tree-node__actions button {
  display: inline-flex; border: 0; background: none; padding: 2px; border-radius: 4px; color: var(--uid-text-secondary); cursor: pointer;
}
.admin-block-tree-node__actions button:hover { color: var(--uid-text-primary); background: var(--uid-color-bg-subtle, rgba(0, 0, 0, 0.06)); }
.admin-block-tree-palette {
  display: flex; flex-direction: column; gap: 8px; padding: 8px; overflow: auto;
  background: var(--uid-surface-raised, #fff);
  border: 1px solid var(--uid-border-subtle); border-radius: var(--uid-radius-md, 8px);
  box-shadow: var(--uid-shadow-lg, 0 8px 24px rgba(0, 0, 0, 0.16));
}
.admin-block-tree-palette__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.admin-block-tree-palette__list { display: flex; flex-direction: column; gap: 2px; }
.admin-block-tree-palette__group { margin: 8px 0 2px; font-size: 12px; color: var(--uid-text-secondary); text-transform: uppercase; }
.admin-block-tree-palette__item {
  display: flex; align-items: center; gap: 8px; width: 100%; text-align: left;
  border: 0; background: none; padding: 6px; border-radius: 6px; color: inherit; cursor: pointer;
}
.admin-block-tree-palette__item:hover, .admin-block-tree-palette__item:focus-visible { background: var(--uid-color-surface-hover, rgba(0, 0, 0, 0.05)); }
.admin-block-tree-palette__thumb { width: 48px; height: 32px; object-fit: cover; border-radius: 4px; flex: none; }
.admin-block-tree-palette__text { display: flex; flex-direction: column; min-width: 0; }
.admin-block-tree-palette__text small { color: var(--uid-text-secondary); }
.admin-block-tree-palette__empty { color: var(--uid-text-secondary); margin: 4px 0; }
.admin-block-tree-preview { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.admin-block-tree-preview__bar {
  display: flex; align-items: center; gap: 8px; padding: 4px 8px; min-height: 36px;
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-block-tree-preview__devices { display: inline-flex; gap: 2px; margin-right: auto; }
.admin-block-tree-preview__state { color: var(--uid-text-secondary); font-size: 12px; }
.admin-block-tree-preview__stage {
  position: relative; flex: 1; min-height: 0; display: flex; flex-direction: column; align-items: center;
  background: var(--uid-color-bg-subtle, #f3f4f6); overflow: auto;
}
.admin-block-tree-preview__frame { flex: 1; max-width: 100%; border: 0; background: #fff; transition: width 0.2s ease; }
.admin-block-tree-preview__empty { color: var(--uid-text-secondary); margin: auto; }
.admin-block-tree-preview__error {
  margin: 0; width: 100%; padding: 6px 12px; color: var(--uid-danger, #b42318);
  background: var(--uid-danger-subtle, #fde7e7);
}
.admin-block-tree__path { display: flex; flex-wrap: wrap; align-items: center; gap: 2px; margin-bottom: 12px; font-size: 13px; }
.admin-block-tree__crumb { border: 0; background: none; padding: 2px 4px; border-radius: 4px; color: var(--uid-accent-text, #1f5fbf); cursor: pointer; }
.admin-block-tree__crumb[aria-current='true'] { color: var(--uid-text-primary); font-weight: 600; cursor: default; }
.admin-block-tree-properties__fields { border: 0; margin: 0; padding: 0; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
.admin-block-tree-properties__errors { margin: 0 0 12px; padding: 8px 12px 8px 28px; color: var(--uid-danger, #b42318); background: var(--uid-danger-subtle, #fde7e7); border-radius: 6px; }
.admin-block-tree-properties__empty { color: var(--uid-text-secondary); }
.admin-block-tree__bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.admin-block-tree__status { color: var(--uid-text-secondary); }
.admin-block-tree__status[data-status='error'] { color: var(--uid-danger, #b42318); }
.admin-block-tree__bar-actions { display: inline-flex; gap: 8px; }
@media (max-width: 1100px) {
  .admin-block-tree__panes { grid-template-columns: minmax(200px, 1fr) minmax(260px, 1.4fr); grid-template-rows: 1fr auto; height: auto !important; }
  .admin-block-tree__preview { grid-column: 1 / -1; grid-row: 2; min-height: 420px; border-left: 0 !important; border-top: 1px solid var(--uid-border-subtle); }
}
</style>
