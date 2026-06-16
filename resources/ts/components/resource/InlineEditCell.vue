<script setup lang="ts">
/**
 * InlineEditCell — обёртка над текстом cell'а с double-click → input.
 *
 * Backend контракт: POST /{slug}/inlineUpdate body {id, column, value}.
 * Резолв правил валидации backend делает на стороне TableColumn->editable.
 *
 * Поведение:
 *   - Idle: рендерит текст (через slot default).
 *   - Edit: input нужного типа (text/number/select/date/textarea/switcher) с
 *     автофокусом, Enter — save, Esc/Blur — cancel.
 *   - Saving: отключённый input, статус.
 *
 * Tип инпута выбирается через prop `inputType`, для select подаются
 * `options: Record<value, label>`. Read-only forced если `editable === false`
 * либо если `rowOverride[column] === false` (per-row override от backend'а).
 */
import { computed, nextTick, ref } from 'vue'
import { adminToast } from '../../stores/toast'

type InlineInputType = 'text' | 'number' | 'select' | 'date' | 'textarea' | 'switcher'

interface Props {
  resourceSlug: string
  rowId: string | number
  column: string
  value: unknown
  /** Если false — двойной клик ничего не делает (read-only cell). */
  editable?: boolean
  /** Тип контрола редактирования. */
  inputType?: InlineInputType
  /** Для inputType='select' — мапа value→label. */
  options?: Record<string | number, string>
  /** Per-row override map; если column ∈ override и значение false — read-only. */
  rowOverride?: Record<string, boolean>
}
const props = withDefaults(defineProps<Props>(), {
  editable: true,
  inputType: 'text',
  options: () => ({}),
  rowOverride: () => ({}),
})

const emit = defineEmits<{
  saved: [value: unknown]
}>()

const editing = ref<boolean>(false)
const draft = ref<string>('')
const saving = ref<boolean>(false)
const inputRef = ref<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null>(null)

const isEditable = computed<boolean>(() => {
  if (!props.editable) return false
  if (props.rowOverride[props.column] === false) return false
  return true
})

async function startEdit(): Promise<void> {
  if (!isEditable.value || saving.value) return
  draft.value = props.value === null || props.value === undefined ? '' : String(props.value)
  editing.value = true
  await nextTick()
  inputRef.value?.focus()
  if (inputRef.value && 'select' in inputRef.value && typeof inputRef.value.select === 'function') {
    inputRef.value.select()
  }
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
    const sendValue: unknown = props.inputType === 'switcher'
      ? draft.value === 'true' || draft.value === '1'
      : draft.value
    await client.post(`/${props.resourceSlug}/inlineUpdate`, {
      id: props.rowId,
      column: props.column,
      value: sendValue,
    })
    emit('saved', sendValue)
    editing.value = false
  } catch (err) {
    if (typeof console !== 'undefined') console.error('[admin] inline-update failed:', err)
    adminToast.error(`Не удалось обновить «${props.column}».`)
  } finally {
    saving.value = false
  }
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter' && props.inputType !== 'textarea') {
    e.preventDefault()
    void commit()
  } else if (e.key === 'Escape') {
    e.preventDefault()
    cancel()
  }
}

function toggleSwitcher(): void {
  if (!isEditable.value || saving.value) return
  draft.value = props.value === true || props.value === 1 || props.value === '1' ? 'false' : 'true'
  editing.value = true
  void commit()
}

const optionEntries = computed(() =>
  Object.entries(props.options).map(([value, label]) => ({ value, label })),
)
</script>

<template>
  <!-- Switcher is one-click toggle, not double-click edit -->
  <span
    v-if="inputType === 'switcher'"
    :class="['admin-inline-edit', { 'admin-inline-edit--editable': isEditable }]"
    @click.stop="toggleSwitcher"
  >
    <slot>{{ value }}</slot>
  </span>
  <template v-else>
    <span
      v-if="!editing"
      :class="['admin-inline-edit', { 'admin-inline-edit--editable': isEditable }]"
      :title="isEditable ? 'Двойной клик для редактирования' : undefined"
      @dblclick.stop="startEdit"
    >
      <slot>{{ value }}</slot>
    </span>
    <input
      v-else-if="inputType === 'number'"
      ref="inputRef"
      v-model="draft"
      type="number"
      class="admin-inline-edit__input"
      :disabled="saving"
      @keydown="onKeydown"
      @blur="commit"
      @click.stop
    />
    <input
      v-else-if="inputType === 'date'"
      ref="inputRef"
      v-model="draft"
      type="date"
      class="admin-inline-edit__input"
      :disabled="saving"
      @keydown="onKeydown"
      @blur="commit"
      @click.stop
    />
    <select
      v-else-if="inputType === 'select'"
      ref="inputRef"
      v-model="draft"
      class="admin-inline-edit__input"
      :disabled="saving"
      @keydown="onKeydown"
      @change="commit"
      @blur="commit"
      @click.stop
    >
      <option
        v-for="opt in optionEntries"
        :key="opt.value"
        :value="opt.value"
      >{{ opt.label }}</option>
    </select>
    <textarea
      v-else-if="inputType === 'textarea'"
      ref="inputRef"
      v-model="draft"
      class="admin-inline-edit__input admin-inline-edit__input--textarea"
      rows="3"
      :disabled="saving"
      @keydown="onKeydown"
      @blur="commit"
      @click.stop
    />
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
.admin-inline-edit__input--textarea {
  height: auto;
  min-height: 64px;
  padding: 6px;
  resize: vertical;
  font-family: inherit;
}
</style>
