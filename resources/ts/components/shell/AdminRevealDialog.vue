<script setup lang="ts">
/**
 * The dialog behind useReveal(): a value shown once, with a copy button.
 * AdminApp mounts it once. Only the buttons close it — not a click outside,
 * which would lose the value by accident.
 */
import { computed } from 'vue'
import { Copy } from 'lucide-vue-next'
import { UidButton, UidIcon, UidModal } from '@dskripchenko/ui'
import { closeReveal, revealState } from '../../composables/useReveal'
import { adminToast } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'

const open = computed<boolean>({
  get: () => revealState.open,
  set: (value) => {
    if (!value) closeReveal()
  },
})

async function copy(): Promise<void> {
  try {
    await navigator.clipboard.writeText(revealState.value)
    adminToast.success(tr('Скопировано.'))
  } catch {
    adminToast.warning(tr('Скопируйте вручную.'))
  }
}

function selectAll(event: Event): void {
  const el = event.target as HTMLElement
  const range = document.createRange()
  range.selectNodeContents(el)
  const selection = window.getSelection()
  selection?.removeAllRanges()
  selection?.addRange(range)
}
</script>

<template>
  <UidModal v-model="open" size="md" :title="revealState.title || tr('Готово')" :close-on-overlay="false">
    <p v-if="revealState.message" class="admin-reveal__message" data-testid="reveal-message">
      {{ revealState.message }}
    </p>
    <div v-if="revealState.label" class="admin-reveal__label">{{ revealState.label }}</div>
    <code class="admin-reveal__value" data-testid="reveal-value" tabindex="0" @click="selectAll" @focus="selectAll">{{ revealState.value }}</code>
    <p class="admin-reveal__warn">
      {{ tr('Значение показывается один раз — после закрытия его не вернуть.') }}
    </p>
    <template #footer>
      <UidButton variant="ghost" data-testid="reveal-close" @click="closeReveal()">
        {{ tr('Готово') }}
      </UidButton>
      <UidButton variant="primary" data-testid="reveal-copy" @click="copy">
        <template #prepend><UidIcon :icon="Copy" :size="14" /></template>
        {{ tr('Скопировать') }}
      </UidButton>
    </template>
  </UidModal>
</template>

<style>
.admin-reveal__message {
  margin: 0 0 12px;
  white-space: pre-line;
}
.admin-reveal__label {
  margin-bottom: 4px;
  font-size: 12px;
  color: var(--uid-text-secondary);
}
.admin-reveal__value {
  display: block;
  padding: 8px 10px;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-sm);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 13px;
  word-break: break-all;
  user-select: all;
}
.admin-reveal__warn {
  margin: 8px 0 0;
  font-size: 12px;
  color: var(--uid-text-secondary);
}
</style>
