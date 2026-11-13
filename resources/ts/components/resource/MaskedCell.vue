<script setup lang="ts">
/**
 * MaskedCell — a cell of a masked column (TableColumn::masked()): the value
 * arrives masked from the server; «Show», offered to the users the list's
 * `meta.revealable` names, asks for the open value with a separate call
 * (POST /{slug}/reveal), which the server writes to the audit log.
 */
import { ref } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import { UidIcon } from '@dskripchenko/ui'
import { getAdminClient } from '../../stores/registry'
import { toastError } from '../../stores/toast'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  /** The masked value, as the list carries it. */
  value?: unknown
  resourceSlug: string
  rowId: string | number | null
  column: string
  /** Whether this user may press «Show». */
  revealable?: boolean
}

const props = withDefaults(defineProps<Props>(), { value: undefined, revealable: false })

const opened = ref<string | null>(null)
const loading = ref(false)

async function reveal(): Promise<void> {
  if (props.rowId === null || loading.value) return
  loading.value = true
  try {
    const res = await getAdminClient().post<{ value: unknown }>(`/${props.resourceSlug}/reveal`, {
      id: props.rowId,
      column: props.column,
    })
    opened.value = res.value === null || res.value === undefined ? '' : String(res.value)
  } catch (err) {
    toastError(err)
  } finally {
    loading.value = false
  }
}

function hide(): void {
  opened.value = null
}

const shown = (v: unknown): string => (v === null || v === undefined ? '' : String(v))
</script>

<template>
  <span class="admin-masked-cell" data-testid="masked-cell">
    <span class="admin-masked-cell__value" :class="{ 'admin-masked-cell__value--open': opened !== null }">
      {{ opened !== null ? opened : shown(value) }}
    </span>
    <!-- A click here is not a click on the row: it would open the record. -->
    <button
      v-if="revealable && shown(value) !== '' && opened === null"
      type="button"
      class="admin-masked-cell__btn"
      data-testid="masked-reveal"
      :disabled="loading"
      :aria-label="tr('Показать')"
      :title="tr('Показать')"
      @click.stop="reveal"
    >
      <UidIcon :icon="Eye" :size="13" />
    </button>
    <button
      v-else-if="opened !== null"
      type="button"
      class="admin-masked-cell__btn"
      data-testid="masked-hide"
      :aria-label="tr('Скрыть')"
      :title="tr('Скрыть')"
      @click.stop="hide"
    >
      <UidIcon :icon="EyeOff" :size="13" />
    </button>
  </span>
</template>

<style>
.admin-masked-cell {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 100%;
}
.admin-masked-cell__value {
  overflow: hidden;
  text-overflow: ellipsis;
  font-variant-numeric: tabular-nums;
}
.admin-masked-cell__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: 0;
  border-radius: var(--uid-radius-sm, 4px);
  background: transparent;
  color: var(--uid-text-tertiary);
  cursor: pointer;
  flex: none;
}
.admin-masked-cell__btn:hover {
  color: var(--uid-text-primary);
  background: var(--uid-color-surface-hover, rgba(0, 0, 0, 0.04));
}
</style>
