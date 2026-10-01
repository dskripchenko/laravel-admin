<script setup lang="ts">
/**
 * The panel-wide confirmation dialog behind useConfirm(); AdminApp mounts it
 * once.
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
    <p class="admin-confirm__message" data-testid="confirm-message">
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
.admin-confirm__message {
  margin: 0;
  white-space: pre-line;
}
</style>
