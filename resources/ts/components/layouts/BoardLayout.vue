<script setup lang="ts">
/**
 * The board layout — `Layout::board()`: a collaborative board, BoardEditor of
 * @dskripchenko/board, edited by several people at once.
 *
 * It only draws. A board package serves the board at `endpoint` (snapshot,
 * batches, poll, presence) and builds this layout; the transport is
 * adminBoardProvider over the panel's API client. With `realtime` and the
 * client registered (setBoardRealtime) the others' changes come on the
 * channel, otherwise by polling every `pollInterval` ms.
 *
 * When the board has moved past what the server still logs (410), the editor
 * is mounted again with a new provider, which loads the board afresh.
 */
import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue'
import type { BoardProvider } from '@dskripchenko/board'
import DocumentFrame from '../documents/DocumentFrame.vue'
import { loadBoardVue } from '../documents/loaders'
import { useLazyModule } from '../documents/useDocumentSource'
import { createAdminBoardProvider } from '../board/adminBoardProvider'
import { cssLength, flowLocale } from '../flow/loadFlow'
import { apiErrorMessage } from '../../api/errors'
import { adminToast } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  endpoint: string
  readonly?: boolean
  user?: { id?: string | null; name?: string | null; color?: string | null } | null
  realtime?: { ticketUrl: string; channel: string } | null
  pollInterval?: number
  title?: string | null
  height?: string | number | null
}>(), {
  readonly: false,
  user: null,
  realtime: null,
  pollInterval: 1500,
  title: null,
  height: null,
})

const { module, failed } = useLazyModule(loadBoardVue)

/** Bumped to mount the editor again — after a 410, or when the board changes. */
const generation = ref(0)
const provider = shallowRef<BoardProvider | null>(null)

function connect(): void {
  provider.value?.close?.()
  provider.value = createAdminBoardProvider({
    endpoint: props.endpoint,
    pollInterval: props.pollInterval,
    realtime: props.realtime,
    readonly: props.readonly,
    onReload: () => {
      connect()
      generation.value++
    },
    onRejected: (error) => adminToast.error(apiErrorMessage(error, tr('Изменение на доске не принято.'))),
  })
}

watch(() => [props.endpoint, props.realtime?.channel, props.readonly], () => {
  connect()
  generation.value++
}, { immediate: true })

onBeforeUnmount(() => provider.value?.close?.())

/** BoardEditor takes the user without nulls. */
const user = computed(() => {
  const u = props.user
  if (!u) return null
  const out: { id?: string; name?: string; color?: string } = {}
  if (u.id) out.id = u.id
  if (u.name) out.name = u.name
  if (u.color) out.color = u.color
  return out
})
</script>

<template>
  <DocumentFrame
    kind="board"
    :title="title"
    :height="cssLength(height, '600px')"
    :loading="!module"
    :error="failed ? tr('Не удалось загрузить модуль доски') : null"
  >
    <component
      :is="module.BoardEditor"
      v-if="module && provider"
      :key="generation"
      :provider="provider"
      :readonly="readonly"
      :user="user"
      :locale="flowLocale()"
      class="admin-document-layout__viewer admin-board"
      data-testid="board-editor"
    />
  </DocumentFrame>
</template>
