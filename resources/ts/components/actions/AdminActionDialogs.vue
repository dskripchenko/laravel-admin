<script setup lang="ts">
/**
 * The dialogs of an action runner (useActionRunner): the confirmation, the
 * ModalAction's form and the AsyncAction's progress. A page that runs actions
 * renders it once and hands it its runner.
 */
import { computed } from 'vue'
import { UidAlert, UidButton, UidModal, UidProgress } from '@dskripchenko/ui'
import type { ActionRunner } from '../../composables/useActionRunner'
import AdminActionForm from './AdminActionForm.vue'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  runner: ActionRunner
}

const props = defineProps<Props>()

const confirmOpen = computed<boolean>({
  get: () => props.runner.confirmState.open,
  set: (open) => {
    if (!open) props.runner.resolveConfirm(false)
  },
})

const modalOpen = computed<boolean>({
  get: () => props.runner.modalState.open,
  set: (open) => {
    if (!open) props.runner.closeModal()
  },
})

const asyncOpen = computed<boolean>({
  get: () => props.runner.asyncState.open,
  set: (open) => {
    if (!open) props.runner.closeAsync()
  },
})

const asyncStatusLabel = computed<string>(() => {
  const s = props.runner.asyncState
  if (s.error) return tr('Ошибка')
  if (s.finished) return tr('Готово')
  return s.status === 'new' ? tr('В очереди…') : tr('Выполняется…')
})
</script>

<template>
  <UidModal
    v-model="confirmOpen"
    size="sm"
    :title="runner.confirmState.title"
  >
    <p class="admin-action-confirm__message" data-testid="action-confirm-message">
      {{ runner.confirmState.message }}
    </p>
    <template #footer>
      <UidButton
        variant="ghost"
        data-testid="action-confirm-cancel"
        @click="runner.resolveConfirm(false)"
      >
        {{ runner.confirmState.cancelLabel }}
      </UidButton>
      <UidButton
        :variant="runner.confirmState.destructive ? 'danger' : 'primary'"
        data-testid="action-confirm-ok"
        @click="runner.resolveConfirm(true)"
      >
        {{ runner.confirmState.confirmLabel }}
      </UidButton>
    </template>
  </UidModal>

  <UidModal
    v-model="modalOpen"
    :size="runner.modalState.size"
    :title="runner.modalState.title"
    :close-on-overlay="false"
  >
    <form
      class="admin-action-modal"
      data-testid="action-modal"
      @submit.prevent="runner.submitModal()"
    >
      <AdminActionForm
        :key="runner.modalState.seq"
        :fields="runner.modalState.fields"
        :values="runner.modalState.values"
        :errors="runner.modalState.errors"
      />
    </form>
    <template #footer>
      <UidButton variant="ghost" data-testid="action-modal-cancel" @click="runner.closeModal()">
        {{ tr('Отмена') }}
      </UidButton>
      <UidButton
        :variant="runner.modalState.action?.destructive ? 'danger' : 'primary'"
        :loading="runner.modalState.submitting"
        data-testid="action-modal-submit"
        @click="runner.submitModal()"
      >
        {{ runner.modalState.submitLabel }}
      </UidButton>
    </template>
  </UidModal>

  <UidModal
    v-model="asyncOpen"
    size="sm"
    :title="runner.asyncState.label"
  >
    <div class="admin-action-async" data-testid="action-async">
      <UidProgress
        :value="runner.asyncState.progress"
        :max="100"
        :label="asyncStatusLabel"
        :variant="runner.asyncState.error ? 'danger' : runner.asyncState.finished ? 'success' : 'default'"
        show-value
      />
      <UidAlert v-if="runner.asyncState.error" variant="danger">
        {{ runner.asyncState.error }}
      </UidAlert>
      <p v-else-if="!runner.asyncState.finished" class="admin-action-async__hint">
        {{ tr('Окно можно закрыть — о результате придёт уведомление.') }}
      </p>
    </div>
    <template #footer>
      <UidButton variant="secondary" data-testid="action-async-close" @click="runner.closeAsync()">
        {{ tr('Закрыть') }}
      </UidButton>
    </template>
  </UidModal>
</template>

<style>
.admin-action-confirm__message {
  margin: 0;
  white-space: pre-line;
}
.admin-action-async {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}
.admin-action-async__hint {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-secondary);
}
</style>
