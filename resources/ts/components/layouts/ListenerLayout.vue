<script setup lang="ts">
/**
 * The Listener layout — a part of the form the server re-renders as the
 * watched fields change.
 *
 * It watches the `listen` fields in the form state. After `debounce`
 * milliseconds of quiet it posts `{listener: id, state}` to the endpoint the
 * page provides (see listenerContext), then swaps its children for the ones
 * that came back and merges the returned state patch into the form.
 *
 * - A newer change cancels the request in flight; an answer to an older
 *   request is dropped.
 * - A patched key the person has edited since the request left is not
 *   overwritten: their input wins over a stale answer.
 * - The children are swapped in place, keyed by position, so a field that
 *   keeps its place keeps its component — and the focus.
 * - A patch to a watched field does not trigger the listener again.
 * - On mount, a listener the server could not render against the real state
 *   (`primed: false`, a resource form) asks once, to sync its children; that
 *   first answer changes no values.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { UidSpinner } from '@dskripchenko/ui'
import RowsLayout from './RowsLayout.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'
import { tryUseFormState } from '../render/formState'
import { useListenerEndpoint } from '../render/listenerContext'
import { getAdminClient, hasAdminClient } from '../../stores/registry'
import { ValidationError } from '../../api/errors'
import { toastError } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

interface Props {
  id: string
  items?: LayoutNode[]
  /** The watched field names. */
  listen?: string[]
  /** The quiet period before a request, in milliseconds. */
  debounce?: number
  /** Whether the server rendered the children against the form's real state. */
  primed?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  listen: () => [],
  debounce: 300,
  primed: false,
})

interface ListenerAnswer {
  listener?: string
  state?: Record<string, unknown>
  layouts?: LayoutNode[]
}

const form = tryUseFormState()
const endpoint = useListenerEndpoint()

const current = ref<LayoutNode[]>(props.items)
const loading = ref(false)

// A new tree from the server (the screen reloaded) replaces ours.
watch(
  () => props.items,
  (next) => {
    current.value = next
  },
)

function readField(name: string): unknown {
  if (!form) return undefined
  const direct = form.getField(name)
  if (direct !== undefined || !name.includes('.')) return direct
  // A dotted name reads into nested objects: `address.country`.
  let value: unknown = form.state
  for (const part of name.split('.')) {
    if (value === null || typeof value !== 'object') return undefined
    value = (value as Record<string, unknown>)[part]
  }
  return value
}

const encode = (value: unknown): string => JSON.stringify(value ?? null)

const watchedKey = computed(() => encode(props.listen.map(readField)))

/** The watched values the last request was made for (or a patch produced). */
let settledKey = watchedKey.value
let timer: ReturnType<typeof setTimeout> | null = null
let inflight: AbortController | null = null
let sequence = 0

function hasValue(value: unknown): boolean {
  if (value === null || value === undefined || value === '') return false
  if (Array.isArray(value)) return value.length > 0
  return true
}

async function request(applyState: boolean): Promise<void> {
  if (!form || !endpoint || !hasAdminClient()) return

  inflight?.abort()
  const controller = new AbortController()
  inflight = controller
  const seq = ++sequence

  const sent = JSON.parse(JSON.stringify(form.state)) as Record<string, unknown>
  settledKey = watchedKey.value
  loading.value = true

  try {
    const answer = await getAdminClient().post<ListenerAnswer>(
      endpoint.url(),
      { ...(endpoint.extra?.() ?? {}), listener: props.id, state: sent },
      { signal: controller.signal },
    )
    if (seq !== sequence) return

    if (Array.isArray(answer.layouts)) current.value = answer.layouts

    if (applyState && answer.state && typeof answer.state === 'object') {
      for (const [key, value] of Object.entries(answer.state)) {
        // The person changed this field while the request was out: keep it.
        if (encode(form.getField(key)) !== encode(sent[key])) continue
        if (encode(form.getField(key)) === encode(value)) continue
        form.setField(key, value)
      }
      // A patch to a watched field is the server's answer, not a new change.
      settledKey = watchedKey.value
    }
  } catch (err) {
    if (controller.signal.aborted || seq !== sequence) return
    if (err instanceof ValidationError) {
      for (const [field, messages] of Object.entries(err.fields)) form.setError(field, messages)
    } else {
      toastError(err, tr('Не удалось обновить форму'))
    }
  } finally {
    if (seq === sequence) {
      loading.value = false
      inflight = null
    }
  }
}

function schedule(): void {
  if (timer !== null) clearTimeout(timer)
  timer = setTimeout(() => {
    timer = null
    void request(true)
  }, Math.max(0, props.debounce))
}

watch(watchedKey, (key) => {
  if (key === settledKey) {
    // Back to the values already answered: nothing to ask, and a pending
    // request for the values in between is moot.
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
    return
  }
  schedule()
})

onMounted(() => {
  if (!props.primed && props.listen.some((name) => hasValue(readField(name)))) {
    void request(false)
  }
})

onBeforeUnmount(() => {
  if (timer !== null) clearTimeout(timer)
  sequence++
  inflight?.abort()
})
</script>

<template>
  <div
    class="admin-listener"
    :class="{ 'admin-listener--loading': loading }"
    :aria-busy="loading ? 'true' : 'false'"
    :data-listener="id"
  >
    <RowsLayout :items="current" />
    <span v-if="loading" class="admin-listener__spinner">
      <UidSpinner size="sm" :label="tr('Обновление…')" />
    </span>
  </div>
</template>

<style>
.admin-listener {
  position: relative;
  min-width: 0;
}
.admin-listener--loading > :first-child {
  opacity: 0.6;
  transition: opacity 0.15s ease;
}
.admin-listener__spinner {
  position: absolute;
  top: 0;
  right: 0;
  pointer-events: none;
}
</style>
