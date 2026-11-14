<script setup lang="ts">
/**
 * The panel-wide confirmation dialog behind useConfirm(); AdminApp mounts it
 * once.
 *
 * It always answers something done in an open window — leaving a record drawer
 * with unsaved changes, deleting from it — so it stays above drawers and other
 * modals. The kit gives modals and drawers the same layer and teleports each
 * one where it was mounted; mounted first, this dialog would open behind them.
 */
import { computed } from 'vue'
import { UidButton, UidModal } from '@dskripchenko/ui'
import { confirmState, resolveConfirmDialog } from '../../composables/useConfirm'

const open = computed<boolean>({
  get: () => confirmState.open,
  set: (value) => {
    if (!value) resolveConfirmDialog(false)
  },
})
</script>

<template>
  <UidModal v-model="open" size="sm" :title="confirmState.title">
    <p class="admin-confirm__message" data-admin-confirm data-testid="confirm-message">
      {{ confirmState.message }}
    </p>
    <template #footer>
      <UidButton variant="ghost" data-testid="confirm-cancel" @click="resolveConfirmDialog(false)">
        {{ confirmState.cancelLabel }}
      </UidButton>
      <UidButton
        :variant="confirmState.destructive ? 'danger' : 'primary'"
        data-testid="confirm-ok"
        @click="resolveConfirmDialog(true)"
      >
        {{ confirmState.confirmLabel }}
      </UidButton>
    </template>
  </UidModal>
</template>

<style>
.uid-modal-overlay:has([data-admin-confirm]) {
  z-index: calc(var(--uid-z-modal, 400) + 50);
}

.admin-confirm__message {
  margin: 0;
  white-space: pre-line;
}
</style>
