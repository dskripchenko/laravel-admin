<script setup lang="ts">
/**
 * ResourceTreePage — the tree screen of hierarchical resources.
 *
 * It kicks in when `manifest.resource.view_mode === 'tree'`, which is detected
 * from the Eloquent self-references or forced by the `--tree` flag during
 * generation. It is the alternative to the usual ResourceIndexPage with its
 * UidTable.
 *
 * What it is made of:
 *   - the page header: the title, the count and the create button
 *   - a minimal toolbar: search plus an expand-all/collapse-all dropdown
 *   - the selected-node toolbar, which replaces the search once something is
 *     selected: edit, view and delete for that one node
 *   - a UidTreeView over the TreeNode[] (key/label/children) from POST /{slug}/tree
 *   - the loading, empty and error states
 *
 * The interaction: one click on a node selects it — separately from the
 * toolbar's actions — and a double click navigates to its edit page. Per-node
 * icons and actions are not shown; that would need UidTreeItem extended, which
 * is out of scope for v1.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronDown, ChevronsDownUp, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import {
  UidButton,
  UidEmptyState,
  UidErrorState,
  UidIcon,
  UidInput,
  UidMenu,
  UidMenuItem,
  UidSkeleton,
  UidTreeView,
} from '@dskripchenko/ui'
import { useManifestStore } from '../../stores/manifest'
import { getAdminClient } from '../../stores/registry'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  slug: string
  title?: string | null
  subtitle?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  subtitle: null,
})

interface TreeNodeAction {
  id: string
  label: string
  icon?: string
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  kind: 'navigate'
  to: {
    slug: string
    screen: 'create' | 'edit' | 'view'
    params?: Record<string, string | number>
  }
}

interface TreeNode {
  key: string | number
  label: string
  children?: TreeNode[]
  record?: Record<string, unknown>
  /**
   * When set, the edit, view and delete navigation goes to another resource by
   * this slug — used by the cross-resource leaves, see
   * Resource::treeExtraLeaves. It defaults to the current props.slug.
   */
  slug?: string
  /**
   * The per-node contextual actions (see Resource::treeNodeActions). They are
   * rendered in the toolbar once a node is selected.
   */
  actions?: TreeNodeAction[]
}

interface TreeResponse {
  data: TreeNode[]
  meta: {
    total: number
    max_depth: number
    parent_key: string
    label_column: string
  }
}

const router = useRouter()
const manifest = useManifestStore()
const resourceMeta = computed(() => manifest.getResource(props.slug))

const nodes = ref<TreeNode[]>([])
const total = ref(0)
const loading = ref(false)
const error = ref<Error | null>(null)
const search = ref('')
const expandedKeys = ref<Array<string | number>>([])
const selectedKeys = ref<Array<string | number>>([])

const headerTitle = computed(() => props.title ?? resourceMeta.value?.label ?? props.slug)
const headerSubtitle = computed(() => props.subtitle ?? null)
const canCreate = computed(() => Boolean(resourceMeta.value?.permissions?.create))
const virtualRootLabel = computed(() => resourceMeta.value?.label ?? props.slug)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const client = getAdminClient()
    const body: Record<string, unknown> = {}
    if (search.value.trim() !== '') body.q = search.value.trim()
    const res = await client.post<TreeResponse>(`/${props.slug}/tree`, body)
    nodes.value = res.data
    total.value = res.meta.total
  } catch (err) {
    error.value = err instanceof Error ? err : new Error(String(err))
    nodes.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function collectAllKeys(list: TreeNode[]): Array<string | number> {
  const acc: Array<string | number> = []
  const walk = (xs: TreeNode[]): void => {
    for (const n of xs) {
      acc.push(n.key)
      if (n.children?.length) walk(n.children)
    }
  }
  walk(list)
  return acc
}

function expandAll(): void {
  expandedKeys.value = collectAllKeys(nodes.value)
}
function collapseAll(): void {
  expandedKeys.value = []
}

const selectedNodeKey = computed(() => selectedKeys.value[0] ?? null)

// Cross-resource navigation: for the leaves coming from treeExtraLeaves —
// templates inside a tree of groups, say — the backend puts a `slug` and the
// record's real id into `record.id`. Otherwise we use the current props.slug
// and selectedNodeKey, the main resource's id. That lets a single tree page
// lead to the edit pages of different resources.
function findNode(list: TreeNode[], key: string | number): TreeNode | null {
  for (const n of list) {
    if (n.key === key) return n
    if (n.children?.length) {
      const found = findNode(n.children, key)
      if (found) return found
    }
  }
  return null
}
const selectedNode = computed<TreeNode | null>(() =>
  selectedNodeKey.value === null ? null : findNode(nodes.value, selectedNodeKey.value),
)
const selectedSlug = computed<string>(() => selectedNode.value?.slug ?? props.slug)
const selectedRecordId = computed<string | number | null>(() => {
  const node = selectedNode.value
  if (!node) return null
  // For a cross-resource leaf the real id comes from record.id rather than
  // node.key, which may be composite — "tpl:1" and the like.
  const recId = node.record?.id
  if (typeof recId === 'string' || typeof recId === 'number') return recId
  return node.key
})

function gotoCreate(): void {
  router.push({ name: `admin.resource.${props.slug}.create` })
}
function gotoEdit(): void {
  if (selectedRecordId.value === null) return
  router.push({
    name: `admin.resource.${selectedSlug.value}.edit`,
    params: { id: String(selectedRecordId.value) },
  })
}
function gotoView(): void {
  if (selectedRecordId.value === null) return
  router.push({
    name: `admin.resource.${selectedSlug.value}.view`,
    params: { id: String(selectedRecordId.value) },
  })
}

const selectedNodeActions = computed<TreeNodeAction[]>(
  () => selectedNode.value?.actions ?? [],
)

/**
 * Substitutes values from the record into `params`: `{id}` becomes record.id
 * and so on, while every other key is left as it is. Used below to build the
 * query of a navigate action.
 */
function resolveActionParams(
  params: Record<string, string | number> | undefined,
  record: Record<string, unknown> | undefined,
): Record<string, string | number> {
  const out: Record<string, string | number> = {}
  if (!params) return out
  for (const [k, v] of Object.entries(params)) {
    if (typeof v === 'string') {
      out[k] = v.replace(/\{([a-zA-Z_][\w]*)\}/g, (_, field) => {
        const rv = record?.[field]
        return rv == null ? '' : String(rv)
      })
    } else {
      out[k] = v
    }
  }
  return out
}

function runAction(action: TreeNodeAction): void {
  if (action.kind !== 'navigate') return
  const node = selectedNode.value
  const record = node?.record ?? {}
  const query = resolveActionParams(action.to.params, record)
  router.push({
    name: `admin.resource.${action.to.slug}.${action.to.screen}`,
    query,
  })
}

async function deleteSelected(): Promise<void> {
  if (selectedRecordId.value === null) return
  if (!confirm(tr('Удалить выбранный узел?'))) return
  try {
    const client = getAdminClient()
    await client.post(`/${selectedSlug.value}/delete`, { id: selectedRecordId.value })
    selectedKeys.value = []
    await load()
  } catch (err) {
    error.value = err instanceof Error ? err : new Error(String(err))
  }
}

// The search is debounced: every change refetches, and the server filters by
// searchableFields. It could have been done locally, but the server's way is
// simpler and scales further.
let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimer !== null) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    void load()
  }, 300)
})

onMounted(load)
</script>

<template>
  <div class="admin-resource-tree-page">
    <header class="admin-resource-tree-page__header">
      <div>
        <h1 class="admin-resource-tree-page__title">
          {{ headerTitle }}
          <span v-if="total > 0" class="admin-resource-tree-page__count">{{ total }}</span>
        </h1>
        <p v-if="headerSubtitle" class="admin-resource-tree-page__subtitle">
          {{ headerSubtitle }}
        </p>
      </div>
      <div class="admin-resource-tree-page__actions">
        <UidButton v-if="canCreate" variant="primary" @click="gotoCreate">
          <UidIcon :icon="Plus" /> {{ tr('Создать') }}
        </UidButton>
      </div>
    </header>

    <div class="admin-resource-tree-page__toolbar">
      <UidInput
        v-model="search"
        :placeholder="tr('Поиск по дереву…')"
        class="admin-resource-tree-page__search"
      />
      <UidMenu>
        <template #trigger>
          <UidButton variant="ghost">
            <UidIcon :icon="ChevronsDownUp" /> {{ tr('Развернуть') }}
            <UidIcon :icon="ChevronDown" />
          </UidButton>
        </template>
        <UidMenuItem @click="expandAll">{{ tr('Развернуть все') }}</UidMenuItem>
        <UidMenuItem @click="collapseAll">{{ tr('Свернуть все') }}</UidMenuItem>
      </UidMenu>
      <template v-if="selectedNodeKey !== null">
        <span class="admin-resource-tree-page__selection-divider" aria-hidden="true">|</span>
        <span class="admin-resource-tree-page__selection-label">
          {{ selectedNode?.label ?? selectedNodeKey }}
        </span>
        <UidButton variant="ghost" @click="gotoView">
          <UidIcon :icon="Pencil" /> {{ tr('Открыть') }}
        </UidButton>
        <UidButton variant="ghost" @click="gotoEdit">
          <UidIcon :icon="Pencil" /> {{ tr('Редактировать') }}
        </UidButton>
        <UidButton
          v-for="act in selectedNodeActions"
          :key="act.id"
          :variant="act.variant ?? 'secondary'"
          @click="runAction(act)"
        >
          {{ act.label }}
        </UidButton>
        <UidButton
          v-if="resourceMeta?.permissions?.delete"
          variant="danger"
          @click="deleteSelected"
        >
          <UidIcon :icon="Trash2" /> {{ tr('Удалить') }}
        </UidButton>
        <UidButton variant="ghost" @click="selectedKeys = []">{{ tr('Снять выбор') }}</UidButton>
      </template>
    </div>

    <div class="admin-resource-tree-page__body">
      <template v-if="loading && nodes.length === 0">
        <UidSkeleton v-for="i in 6" :key="i" />
      </template>
      <UidErrorState v-else-if="error" :message="error.message" @retry="load" />
      <UidEmptyState
        v-else-if="nodes.length === 0"
        :title="tr('Нет данных')"
        :hint="search.length > 0 ? tr('Ничего не найдено по запросу.') : null"
      />
      <UidTreeView
        v-else
        :nodes="nodes"
        :virtual-root="virtualRootLabel"
        v-model:expanded-keys="expandedKeys"
        v-model:selected-keys="selectedKeys"
        selectable="single"
        show-guides
      />
    </div>
  </div>
</template>

<style scoped>
.admin-resource-tree-page {
  display: flex;
  flex-direction: column;
  gap: var(--uid-spacing-md, 16px);
  padding: var(--uid-spacing-lg, 24px);
}
.admin-resource-tree-page__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-spacing-md, 16px);
}
.admin-resource-tree-page__title {
  display: flex;
  align-items: center;
  gap: var(--uid-spacing-sm, 8px);
  font-size: var(--uid-font-size-xl, 20px);
  font-weight: 600;
  margin: 0;
}
.admin-resource-tree-page__count {
  background: var(--uid-color-surface-2, #eef0f3);
  color: var(--uid-color-text-secondary, #62686f);
  border-radius: 999px;
  padding: 2px 10px;
  font-size: var(--uid-font-size-sm, 12px);
}
.admin-resource-tree-page__subtitle {
  margin: 4px 0 0;
  color: var(--uid-color-text-secondary, #62686f);
}
.admin-resource-tree-page__toolbar {
  display: flex;
  align-items: center;
  gap: var(--uid-spacing-sm, 8px);
  flex-wrap: wrap;
}
.admin-resource-tree-page__search {
  max-width: 320px;
  flex: 1;
}
.admin-resource-tree-page__selection-divider {
  color: var(--uid-border-subtle, #e5e7eb);
  padding: 0 2px;
}
.admin-resource-tree-page__selection-label {
  color: var(--uid-color-text-secondary, #62686f);
  font-weight: 500;
}
.admin-resource-tree-page__body {
  background: var(--uid-color-surface-1, #fff);
  border: 1px solid var(--uid-color-border, #e5e7eb);
  border-radius: var(--uid-radius-md, 8px);
  padding: var(--uid-spacing-md, 16px);
  min-height: 240px;
}

/* A tidier tree: the focus outline — a heavy teal frame — is removed,
   выделение selected делаем неагрессивным — лёгкий бэкграунд + цветной
   акцент только на тексте/иконке, без border. `:deep()` нужен потому что
   стили scoped'нуты, а .uid-tree-* приходит из @dskripchenko/ui. */
.admin-resource-tree-page :deep(.uid-tree-item__row) {
  border-radius: 6px;
}
.admin-resource-tree-page :deep(.uid-tree-item__row:focus-visible) {
  outline: none;
  background: var(--uid-color-bg-subtle, rgba(0, 0, 0, 0.03));
}
.admin-resource-tree-page :deep(.uid-tree-item__row:hover) {
  background: var(--uid-color-bg-subtle, rgba(0, 0, 0, 0.03));
}
.admin-resource-tree-page :deep(.uid-tree-item--selected > .uid-tree-item__row) {
  background: color-mix(in srgb, var(--uid-accent, #14b8a6) 10%, transparent);
  color: var(--uid-accent, #14b8a6);
  font-weight: 500;
  outline: none;
}
.admin-resource-tree-page :deep(.uid-tree-item--selected > .uid-tree-item__row .uid-tree-item__icon),
.admin-resource-tree-page :deep(.uid-tree-item--selected > .uid-tree-item__row .uid-tree-item__chevron) {
  color: var(--uid-accent, #14b8a6);
}
</style>
