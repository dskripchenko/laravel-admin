<script setup lang="ts">
/**
 * The kanban layout — `Layout::kanban(...)`: a board written as markup,
 * drawn by the kit's UidKanban.
 *
 * The markup is inline (`content`), a key of the screen's state (`source`)
 * or fetched from `url`. With `saveMethod` the board is editable: each change
 * is sent to that screen method as the new markup, and a refusal rolls the
 * board back with a toast. `lockMethod` shows the lock button; `locked` /
 * `lockedSource` make the board read-only. See useMarkupEditing.
 */
import { UidKanban } from '@dskripchenko/ui'
import DocumentFrame from '../documents/DocumentFrame.vue'
import { cssHeight, useDocumentSource } from '../documents/useDocumentSource'
import { useMarkupEditing } from '../documents/useMarkupEditing'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  title?: string | null
  content?: string | null
  source?: string | null
  url?: string | null
  height?: string | number | null
  editable?: boolean
  saveMethod?: string | null
  lockable?: boolean
  lockMethod?: string | null
  locked?: boolean
  lockedSource?: string | null
}>(), {
  title: null,
  content: null,
  source: null,
  url: null,
  height: null,
  editable: false,
  saveMethod: null,
  lockable: false,
  lockMethod: null,
  locked: false,
  lockedSource: null,
})

const { text, loading, error } = useDocumentSource(props)
const { markup, canEdit, canLock, isLocked, onChange, onLock } = useMarkupEditing(props, text)
</script>

<template>
  <DocumentFrame
    kind="kanban"
    :title="title"
    :height="cssHeight(height)"
    :loading="loading || text === null"
    :error="error"
    scroll
  >
    <UidKanban
      :source="markup"
      :editable="canEdit"
      :lockable="canLock"
      :locked="isLocked"
      @change="onChange"
      @lock="onLock"
    />
  </DocumentFrame>
</template>
