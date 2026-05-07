<script setup lang="ts">
/**
 * InlineEditCell — обёртка над текстом cell'а с double-click → input.
 *
 * Backend контракт: POST /{slug}/inlineUpdate body {id, column, value}.
 * Резолв правил валидации backend делает на стороне TableColumn->editable.
 *
 * Поведение:
 *   - Idle: рендерит текст (через slot default).
 *   - Edit: input (text) с автофокусом, Enter — save, Esc/Blur — cancel.
 *   - Saving: отключённый input, статус.
 */
import { nextTick, ref } from 'vue'
import { adminToast } from '../../stores/toast'

interface Props {
  resourceSlug: string
  rowId: string | number
  column: string
  value: unknown
  /** Если false — двойной клик ничего не делает (read-only cell). */
  editable?: boolean
}
const props = withDefaults(defineProps<Props>(), {
  editable: true,
})

const emit = defineEmits<{
  saved: [value: unknown]
}>()

const editing = ref<boolean>(false)
const draft = ref<string>('')
const saving = ref<boolean>(false)
const inputRef = ref<HTMLInputElement | null>(null)

async function startEdit(): Promise<void> {
  if (!props.editable || saving.value) return
  draft.value = props.value === null || props.value === undefined ? '' : String(props.value)
  editing.value = true
  await nextTick()
  inputRef.value?.focus()
  inputRef.value?.select()
}

function cancel(): void {
  editing.value = false
}

async function commit(): Promise<void> {
  if (!editing.value || saving.value) return
  // Без изменений — просто закрываем.
  if (draft.value === String(props.value ?? '')) {
    editing.value = false
    return
  }
  saving.value = true
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post(`/${props.resourceSlug}/inlineUpdate`, {
      id: props.rowId,
      column: props.column,
      value: draft.value,
    })
    emit('saved', draft.value)
    editing.value = false
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] inline-update failed:', err)
    adminToast.error(`Не удалось обновить «${props.column}».`)
  } finally {
    saving.value = false
  }
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter') {
    e.preventDefault()
    void commit()
  } else if (e.key === 'Escape') {
    e.preventDefault()
    cancel()
  }
}
</script>

<template>
  <span
    v-if="!editing"
    :class="['admin-inline-edit', { 'admin-inline-edit--editable': editable }]"
    :title="editable ? 'Двойной клик для редактирования' : undefined"
    @dblclick.stop="startEdit"
  >
    <slot>{{ value }}</slot>
  </span>
  <input
    v-else
    ref="inputRef"
    v-model="draft"
    type="text"
    class="admin-inline-edit__input"
    :disabled="saving"
    @keydown="onKeydown"
    @blur="commit"
    @click.stop
  />
</template>

<style>
.admin-inline-edit {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}
.admin-inline-edit--editable {
  cursor: text;
  border-radius: 3px;
  transition: background 120ms ease;
}
.admin-inline-edit--editable:hover {
  background: color-mix(in srgb, var(--uid-accent) 8%, transparent);
}
.admin-inline-edit__input {
  width: 100%;
  height: 26px;
  padding: 0 6px;
  border: 1px solid var(--uid-accent);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-sm);
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
  font: inherit;
  font-size: 13px;
  color: var(--uid-text-primary);
}
</style>
