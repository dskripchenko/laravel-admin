<script setup lang="ts">
/**
 * ResourceRecordDrawer — a record of the list opened in a panel over it
 * (Layout::resourceIndex()->rowOpen('drawer'), Resource::rowOpen()).
 *
 * It is made of the record pages' own parts: the infolist of the view page
 * (InfolistRenderer over the record), the form of the edit page (RowsLayout
 * over the form state), the record's actions through useActionRunner — and
 * the same endpoints and server-side permissions (`/{slug}/read`, `/update`,
 * `/action`, `/delete`). The list stays under it: a save, an action or a
 * delete is announced (`done`, `removed`) for the list to reload and, when
 * asked, to move on to the next record. ‹ › in the header and Alt+↑ / Alt+↓
 * walk the list.
 */
import { computed, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronDown, ChevronUp, ExternalLink, Pencil, Trash2 } from 'lucide-vue-next'
import { UidAlert, UidButton, UidDrawer, UidIcon, UidSkeleton } from '@dskripchenko/ui'
import { useResourceFormStore } from '../../stores/resourceForm'
import { useManifestStore } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'
import { provideFormState } from '../render/formState'
import { provideListenerEndpoint } from '../render/listenerContext'
import { provideRecord } from '../infolist/recordContext'
import InfolistRenderer from '../infolist/InfolistRenderer.vue'
import type { InfolistNode } from '../infolist/InfolistRenderer.vue'
import RowsLayout from '../layouts/RowsLayout.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'
import { deleteConfirmText, deletedToast, recordFallbackTitle, recordTitleOf } from './resourceNoun'
import { trSafe as tr, tRaw } from '../../stores/i18n'
import { adminToast } from '../../stores/toast'
import { actionReveal, revealOnce } from '../../composables/useReveal'
import { confirmDialog, deleteWording } from '../../composables/useConfirm'
import {
  actionErrorMessage,
  needsSelection,
  normalizeActions,
  useActionRunner,
  type AdminAction,
} from '../../composables/useActionRunner'
import AdminActionButton from '../actions/AdminActionButton.vue'
import AdminActionDialogs from '../actions/AdminActionDialogs.vue'

interface Props {
  slug: string
  /** The open record; null — the drawer is closed. */
  recordId: string | number | null
  mode?: 'view' | 'edit'
  /** sm | md | lg | xl or a CSS length. */
  size?: string | null
  /** The user may edit records: features.editable and the update permission. */
  canEdit?: boolean
  hasNext?: boolean
  hasPrev?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  mode: 'view',
  size: null,
  canEdit: false,
  hasNext: false,
  hasPrev: false,
})

const emit = defineEmits<{
  close: []
  next: []
  prev: []
  'update:mode': [mode: 'view' | 'edit']
  /** A save or an action went through: the list reloads, maybe moves on. */
  done: []
  /** The record was deleted. */
  removed: []
}>()

const form = useResourceFormStore()
const manifest = useManifestStore()
const auth = useAuthStore()
const router = useRouter()

provideRecord(form.state)
const ctx = provideFormState(form.state, form.errors, 'update')
watch(
  () => form.errors,
  (next) => ctx.setErrors({ ...next }),
)
provideListenerEndpoint({
  url: () => `/${props.slug}/listener`,
  extra: () => ({ context: 'update', id: props.recordId }),
})

const resourceMeta = computed(() => manifest.getResource(props.slug))
const open = computed<boolean>(() => props.recordId !== null)

const WIDTHS: Record<string, string> = { sm: '420px', md: '560px', lg: '720px', xl: '960px' }
const width = computed<string>(() => (props.size ? WIDTHS[props.size] ?? props.size : WIDTHS.lg))

const infolistNodes = computed<InfolistNode[]>(
  () => (resourceMeta.value?.infolist ?? resourceMeta.value?.fields ?? []) as InfolistNode[],
)
const formNodes = computed<LayoutNode[]>(() => (resourceMeta.value?.fields ?? []) as LayoutNode[])

const canDelete = computed<boolean>(() => {
  const features = (resourceMeta.value?.features ?? {}) as Record<string, unknown>
  const permission = resourceMeta.value?.permissions?.delete
  return features.editable !== false && (typeof permission !== 'string' || auth.hasPermission(permission))
})

/** The page of the record, when the user may open it. */
const pageRoute = computed<string | null>(() => {
  const name = `admin.resource.${props.slug}.view`
  return router.hasRoute(name) ? name : null
})

const title = computed<string>(() => {
  if (props.recordId === null) return ''
  if (form.recordTitle && form.recordTitle !== `#${props.recordId}`) return form.recordTitle
  return recordTitleOf(form.state as Record<string, unknown>)
    ?? recordFallbackTitle(resourceMeta.value, props.slug, props.recordId)
})

async function load(): Promise<void> {
  if (props.recordId === null) return
  await form.load(props.slug, props.recordId, props.mode === 'edit' ? 'edit' : 'view').catch(() => undefined)
}

watch(
  () => [props.slug, props.recordId, props.mode] as const,
  () => {
    void load()
  },
  { immediate: true },
)

/** Unsaved edits are not dropped by a move to another record or a close without asking. */
async function mayLeave(): Promise<boolean> {
  if (props.mode !== 'edit' || !form.isDirty) return true
  return confirmDialog({
    title: tr('Несохранённые изменения'),
    message: tr('Несохранённые изменения будут потеряны. Продолжить?'),
    confirmLabel: tr('Уйти без сохранения'),
    destructive: true,
  })
}

async function requestClose(): Promise<void> {
  if (await mayLeave()) emit('close')
}

async function goNext(): Promise<void> {
  if (props.hasNext && (await mayLeave())) emit('next')
}

async function goPrev(): Promise<void> {
  if (props.hasPrev && (await mayLeave())) emit('prev')
}

async function toView(): Promise<void> {
  if (await mayLeave()) emit('update:mode', 'view')
}

function openPage(): void {
  if (pageRoute.value === null || props.recordId === null) return
  void router.push({ name: pageRoute.value, params: { id: String(props.recordId) } }).catch(() => undefined)
}

async function onSave(andNext = false): Promise<void> {
  try {
    await form.save()
  } catch {
    // Validation errors are on the fields, the rest in the alert.
    return
  }
  adminToast.success(tr('Изменения сохранены.'))
  if (andNext) {
    emit('done')
    emit('next')
    return
  }
  emit('update:mode', 'view')
  emit('done')
}

async function onDelete(): Promise<void> {
  if (!(await confirmDialog({ ...deleteWording(), message: deleteConfirmText(resourceMeta.value, props.slug, title.value) }))) return
  await form.destroy().catch(() => undefined)
  if (form.hasError) return
  adminToast.success(deletedToast(resourceMeta.value, props.slug))
  emit('removed')
}

/** The actions that apply to records, as buttons: approve, reject, next. */
const recordActions = computed<AdminAction[]>(
  () => normalizeActions(resourceMeta.value?.actions).filter((a) => needsSelection(a)),
)

const runner = useActionRunner({
  ids: () => (props.recordId === null ? [] : [props.recordId]),
  async execute(action, payload) {
    const { getAdminClient } = await import('../../stores/registry')
    return getAdminClient().post<{ affected?: number; message?: string }>(
      `/${props.slug}/action`,
      { key: action.name, ids: props.recordId === null ? [] : [props.recordId], ...(payload ? { payload } : {}) },
    )
  },
  async onSuccess(action, raw) {
    const result = (raw ?? {}) as { message?: string }
    const message = result.message ?? tRaw('Действие «:action» выполнено.', { action: action.label })
    const reveal = actionReveal(raw)
    if (reveal) void revealOnce({ ...reveal, title: action.label, message })
    else adminToast.success(message)
    await load()
    emit('done')
  },
  onError(action, err) {
    if (typeof console !== 'undefined') console.error('[admin] action failed:', err)
    adminToast.error(actionErrorMessage(action, err))
  },
  async refresh() {
    await load()
    emit('done')
  },
})

function onKeydown(e: KeyboardEvent): void {
  if (!open.value || !e.altKey) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    void goNext()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    void goPrev()
  }
}

if (typeof window !== 'undefined') window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => {
  if (typeof window !== 'undefined') window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <UidDrawer
    :model-value="open"
    :title="title"
    side="right"
    :width="width"
    @update:model-value="(v: boolean) => { if (!v) void requestClose() }"
  >
    <div class="admin-record-drawer" data-testid="record-drawer">
      <div class="admin-record-drawer__bar">
        <div class="admin-record-drawer__nav">
          <UidButton
            variant="ghost"
            size="sm"
            :disabled="!hasPrev"
            :aria-label="tr('Предыдущая запись')"
            :title="`${tr('Предыдущая запись')} (Alt+↑)`"
            data-testid="record-drawer-prev"
            @click="goPrev"
          >
            <UidIcon :icon="ChevronUp" :size="16" />
          </UidButton>
          <UidButton
            variant="ghost"
            size="sm"
            :disabled="!hasNext"
            :aria-label="tr('Следующая запись')"
            :title="`${tr('Следующая запись')} (Alt+↓)`"
            data-testid="record-drawer-next"
            @click="goNext"
          >
            <UidIcon :icon="ChevronDown" :size="16" />
          </UidButton>
        </div>
        <span class="admin-record-drawer__spacer" />
        <UidButton
          v-if="pageRoute"
          variant="ghost"
          size="sm"
          data-testid="record-drawer-page"
          @click="openPage"
        >
          <template #prepend><UidIcon :icon="ExternalLink" :size="14" /></template>
          {{ tr('Открыть страницу') }}
        </UidButton>
      </div>

      <UidAlert v-if="form.hasError" variant="danger" role="alert" class="admin-record-drawer__alert">
        {{ form.error?.message ?? tr('Не удалось загрузить запись') }}
      </UidAlert>

      <div v-if="form.loading" class="admin-record-drawer__loading">
        <UidSkeleton v-for="i in 6" :key="i" height="24px" />
      </div>
      <div v-else-if="mode === 'edit'" class="admin-record-drawer__form" data-testid="record-drawer-form">
        <RowsLayout :items="formNodes" />
      </div>
      <div v-else class="admin-record-drawer__view" data-testid="record-drawer-view">
        <InfolistRenderer v-for="(node, idx) in infolistNodes" :key="idx" :node="node" />
      </div>
    </div>

    <template #footer>
      <template v-if="mode === 'edit'">
        <UidButton variant="ghost" :disabled="form.saving" @click="toView">{{ tr('Отмена') }}</UidButton>
        <UidButton
          v-if="hasNext"
          variant="secondary"
          :disabled="form.saving || form.loading"
          data-testid="record-drawer-save-next"
          @click="onSave(true)"
        >
          {{ tr('Сохранить и дальше') }}
        </UidButton>
        <UidButton
          variant="primary"
          :loading="form.saving"
          :disabled="form.saving || form.loading"
          data-testid="record-drawer-save"
          @click="onSave(false)"
        >
          {{ tr('Сохранить') }}
        </UidButton>
      </template>
      <template v-else>
        <UidButton
          v-if="canDelete"
          variant="ghost"
          :aria-label="tr('Удалить')"
          :title="tr('Удалить')"
          data-testid="record-drawer-delete"
          @click="onDelete"
        >
          <UidIcon :icon="Trash2" :size="16" />
        </UidButton>
        <span class="admin-record-drawer__spacer" />
        <AdminActionButton
          v-for="action in recordActions"
          :key="action.name"
          :action="action"
          size="md"
          :disabled="form.loading"
          @run="(a: AdminAction) => runner.run(a)"
        />
        <UidButton
          v-if="canEdit"
          variant="secondary"
          data-testid="record-drawer-edit"
          @click="emit('update:mode', 'edit')"
        >
          <template #prepend><UidIcon :icon="Pencil" :size="14" /></template>
          {{ tr('Редактировать') }}
        </UidButton>
        <UidButton
          v-if="hasNext"
          variant="primary"
          data-testid="record-drawer-next-footer"
          @click="goNext"
        >
          {{ tr('Следующая') }}
        </UidButton>
      </template>
    </template>
  </UidDrawer>
  <AdminActionDialogs :runner="runner" />
</template>

<style>
.admin-record-drawer__bar {
  display: flex;
  align-items: center;
  gap: var(--uid-space-xs, 4px);
  margin-bottom: var(--uid-space-md);
}
.admin-record-drawer__nav {
  display: inline-flex;
  gap: var(--uid-space-2xs, 2px);
}
.admin-record-drawer__spacer {
  flex: 1;
}
.admin-record-drawer__alert {
  margin-bottom: var(--uid-space-md);
}
.admin-record-drawer__loading,
.admin-record-drawer__view {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
</style>
