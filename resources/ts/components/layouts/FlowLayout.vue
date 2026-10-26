<script setup lang="ts">
/**
 * The Flow layout — `Layout::flow(...)`: a process or state machine drawn by
 * FlowView of @dskripchenko/flow, read-only, with live monitoring.
 *
 * - The model is inline (`graph`) or read from the screen's state (`source`).
 * - `poll` names a screen method returning the monitoring snapshot; it is
 *   called through the screen context's `call` — the screen's runMethod, so
 *   the session, CSRF, layers and the server's permission checks apply — with
 *   `{path, ref, instance}`.
 * - `toggle` names the method that persists a switch; a rejection rolls the
 *   switch back and FlowView shows the message in a toast.
 * - `subgraphs.method` loads a string `subgraph` reference; `subgraphs.graphs`
 *   holds the ones given inline.
 * - `instance` / `instanceSource` — the instance in focus, handed to FlowView
 *   as `pollParams` and sent to the poll method; a change polls at once.
 * - `actions` are buttons in the details drawer, each calling its screen
 *   method with the selection; the monitoring is refreshed after.
 *
 * The package is loaded on first use. Outside a screen page (no `call`) the
 * graph is drawn without monitoring.
 */
import { computed, ref } from 'vue'
import { UidAlert, UidButton, UidSkeleton } from '@dskripchenko/ui'
import type { Graph, FlowNode, MonitorSnapshot } from '@dskripchenko/flow'
import type { ToggleChange } from '@dskripchenko/flow/vue'
import { tryUseFormState } from '../render/formState'
import { tryUseRecord } from '../infolist/recordContext'
import { useScreenContext, type ScreenActionLike } from '../render/screenContext'
import type { ScreenMethodResult } from '../../stores/screen'
import { blankGraph, cssLength, flowLocale, toGraph, useFlowModules } from '../flow/loadFlow'
import { confirmDialog } from '../../composables/useConfirm'
import { adminToast, toastAlerts, toastError } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

interface Props {
  title?: string | null
  graph?: Graph | null
  source?: string | null
  snapshot?: MonitorSnapshot | null
  snapshotSource?: string | null
  poll?: { method: string; interval?: number } | null
  toggle?: { method: string } | null
  subgraphs?: { method?: string | null; graphs?: Record<string, Graph> } | null
  instance?: string | number | null
  instanceSource?: string | null
  actions?: ScreenActionLike[]
  direction?: 'LR' | 'TB' | 'auto' | null
  edgeStyle?: 'orthogonal' | 'smooth' | null
  height?: string | number | null
  minimap?: boolean
  details?: boolean
  statusBar?: boolean
  toggleable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  graph: null,
  source: null,
  snapshot: null,
  snapshotSource: null,
  poll: null,
  toggle: null,
  subgraphs: null,
  instance: null,
  instanceSource: null,
  actions: () => [],
  direction: null,
  edgeStyle: null,
  height: null,
  minimap: true,
  details: true,
  statusBar: true,
  toggleable: false,
})

const { modules, failed } = useFlowModules()
const form = tryUseFormState()
const record = tryUseRecord()
const screen = useScreenContext()
const call = screen?.call

/** A key of the screen's state: the form's working copy, else the record. */
function read(key: string): unknown {
  const fromForm = form?.getField(key)
  if (fromForm !== undefined) return fromForm
  return record ? record[key] : undefined
}

const model = computed<Graph>(() => {
  if (props.graph) return props.graph
  return (props.source ? toGraph(read(props.source)) : null) ?? blankGraph()
})

const instanceValue = computed<unknown>(() =>
  props.instanceSource ? (read(props.instanceSource) ?? null) : props.instance,
)

/** A PHP empty array arrives as `[]` where the snapshot wants an object. */
function asSnapshot(extra: unknown): MonitorSnapshot {
  const s = (extra && typeof extra === 'object' && !Array.isArray(extra) ? { ...extra } : {}) as Record<string, unknown>
  for (const key of ['nodes', 'edges']) {
    if (Array.isArray(s[key])) s[key] = {}
  }
  return s as MonitorSnapshot
}

const pollOptions = computed(() => {
  const poll = props.poll
  if (!poll || !call) return undefined
  return {
    intervalMs: poll.interval ?? 5000,
    fetch: async (signal: AbortSignal, context: { path: string[]; ref?: string; params?: unknown }) => {
      const res = await call(
        poll.method,
        [{ path: context.path, ref: context.ref ?? null, instance: context.params ?? null }],
        { signal },
      )
      return asSnapshot(res.extra)
    },
  }
})

// The starting state; with polling, the polled snapshots replace it.
const fixedSnapshot = computed<MonitorSnapshot | undefined>(() => {
  const s = props.snapshot ?? (props.snapshotSource ? read(props.snapshotSource) : null)
  return s && typeof s === 'object' ? asSnapshot(s) : undefined
})

const onToggle = computed(() => {
  const toggle = props.toggle
  if (!toggle || !call) return undefined
  return async (change: ToggleChange): Promise<void> => {
    await call(toggle.method, [change])
  }
})

const loadSubgraph = computed(() => {
  const method = props.subgraphs?.method
  if (!method || !call) return undefined
  return async (ref: string, node: FlowNode): Promise<Graph> => {
    const res = await call(method, [ref, node])
    const graph = toGraph(res.extra)
    if (!graph) throw new Error(tr('Не удалось загрузить подпроцесс'))
    return graph
  }
})

const viewRef = ref<{ refresh?: () => void } | null>(null)

const runningAction = ref<string | null>(null)

async function runAction(
  action: ScreenActionLike,
  selection: { kind: string; element: { id: string }; path: string[] },
): Promise<void> {
  const method = action.attributes?.method
  if (!call || typeof method !== 'string' || method === '') return
  if (action.confirm?.message && !(await confirmDialog(action.confirm))) return
  runningAction.value = action.name ?? method
  try {
    const res: ScreenMethodResult = await call(method, [{
      kind: selection.kind,
      id: selection.element.id,
      path: selection.path,
      instance: instanceValue.value,
    }])
    if (res.message) adminToast.success(res.message)
    toastAlerts(res.alerts, res.message)
    viewRef.value?.refresh?.()
  } catch (err) {
    toastError(err, tr('Не удалось выполнить действие'))
  } finally {
    runningAction.value = null
  }
}

const boxStyle = computed(() => ({ height: cssLength(props.height, '480px') }))
</script>

<template>
  <figure class="admin-flow-layout">
    <figcaption v-if="title" class="admin-flow-layout__title">{{ title }}</figcaption>
    <div class="admin-flow-layout__box" :style="boxStyle">
      <UidAlert v-if="failed" variant="danger">{{ tr('Не удалось загрузить схему процесса') }}</UidAlert>
      <UidSkeleton v-else-if="!modules" height="100%" />
      <component
        :is="modules.vue.FlowView"
        v-else
        ref="viewRef"
        class="admin-flow-layout__view"
        :graph="model"
        :state="fixedSnapshot"
        :poll="pollOptions"
        :poll-params="instanceValue"
        :toggleable="toggleable"
        :on-toggle="onToggle"
        :subgraphs="subgraphs?.graphs ?? {}"
        :load-subgraph="loadSubgraph"
        :details="details"
        :minimap="minimap"
        :status-bar="statusBar"
        :direction="direction ?? undefined"
        :edge-style="edgeStyle ?? 'orthogonal'"
        :locale="flowLocale()"
        :toast-provider="false"
      >
        <template v-if="actions.length > 0 && call" #details="selection">
          <div class="admin-flow-layout__actions">
            <UidButton
              v-for="action in actions"
              :key="action.name"
              size="sm"
              :variant="action.destructive ? 'danger' : action.primary ? 'primary' : 'secondary'"
              :loading="runningAction === (action.name ?? action.attributes?.method)"
              :disabled="runningAction !== null"
              @click="runAction(action, selection)"
            >
              {{ action.label }}
            </UidButton>
          </div>
        </template>
      </component>
    </div>
  </figure>
</template>

<style>
.admin-flow-layout { margin: 0 0 var(--uid-space-md, 12px); min-width: 0; }
.admin-flow-layout__title {
  font-weight: var(--uid-font-weight-semibold, 600);
  margin-bottom: var(--uid-space-xs, 6px);
}
.admin-flow-layout__box {
  position: relative;
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  overflow: hidden;
  background: var(--uid-surface-base);
}
.admin-flow-layout__view { width: 100%; height: 100%; }
.admin-flow-layout__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--uid-space-xs, 6px);
  margin-top: var(--uid-space-sm, 8px);
}
</style>
