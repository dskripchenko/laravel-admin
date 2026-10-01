<script setup lang="ts">
/**
 * CodeField — the backend's Field\Code: a monospaced editor over UidTextarea
 * with a line-number gutter and editor-like keys.
 *
 * - Tab indents the current line or every selected line, Shift+Tab outdents.
 *   Escape releases the Tab key for one press, so keyboard users can still
 *   leave the field.
 * - `->language('php')` is shown in the header and picks the indent width;
 *   `->lineNumbers(false)` hides the gutter; `->height()` caps the editor.
 * - A readonly or disabled field is drawn by UidCode, read-only, with a copy
 *   button.
 *
 * `->theme()` is accepted and ignored: the editor follows the panel's theme.
 */
import { computed, onMounted, ref } from 'vue'
import { UidCode, UidFormField, UidTextarea } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  language?: string | null
  lineNumbers?: boolean
  /** The editor's maximum height: a number of pixels or a CSS length. */
  height?: number | string | null
  /** The minimum number of visible lines. */
  rows?: number
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  readonly: false,
  language: null,
  lineNumbers: true,
  height: null,
  rows: 6,
})

const form = useFormState()
const value = computed<string>(() => {
  const v = form.getField(props.name)
  if (v === null || v === undefined) return ''
  return typeof v === 'string' ? v : JSON.stringify(v, null, 2)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const isLocked = computed<boolean>(() => props.disabled || props.readonly)

const lineCount = computed<number>(() => value.value.split('\n').length)
// One spare row keeps a horizontal scrollbar from pushing the last line out of
// alignment with the gutter.
const visibleRows = computed<number>(() => Math.max(props.rows, lineCount.value + 1))

const heightCss = computed<string | undefined>(() => {
  if (props.height === null || props.height === '') return undefined
  return typeof props.height === 'number' ? `${props.height}px` : props.height
})

const indent = computed<string>(() =>
  ['php', 'python', 'py', 'java', 'kotlin', 'csharp', 'cs', 'rust'].includes((props.language ?? '').toLowerCase())
    ? '    '
    : '  ',
)

const rootRef = ref<HTMLElement | null>(null)

onMounted(() => {
  const el = rootRef.value?.querySelector('textarea')
  if (el) {
    el.setAttribute('wrap', 'off')
    el.setAttribute('spellcheck', 'false')
    el.setAttribute('autocapitalize', 'off')
    el.setAttribute('autocomplete', 'off')
  }
})

function onUpdate(next: string): void {
  form.setField(props.name, next)
}

let tabReleased = false

function applyEdit(el: HTMLTextAreaElement, next: string, selStart: number, selEnd: number): void {
  onUpdate(next)
  // The textarea is re-rendered from the state on the next tick; set the DOM
  // value now so the caret lands where the edit left it.
  el.value = next
  el.setSelectionRange(selStart, selEnd)
}

function onKeydown(e: KeyboardEvent): void {
  const el = e.target
  if (!(el instanceof HTMLTextAreaElement) || isLocked.value) return

  if (e.key === 'Escape') {
    tabReleased = true
    return
  }
  if (e.key !== 'Tab') {
    tabReleased = false
    return
  }
  if (tabReleased || e.ctrlKey || e.metaKey || e.altKey) {
    tabReleased = false
    return
  }
  e.preventDefault()

  const text = el.value
  const start = el.selectionStart
  const end = el.selectionEnd
  const unit = indent.value
  const lineStart = text.lastIndexOf('\n', start - 1) + 1

  if (!e.shiftKey && start === end) {
    applyEdit(el, text.slice(0, start) + unit + text.slice(end), start + unit.length, start + unit.length)
    return
  }

  const block = text.slice(lineStart, end)
  const lines = block.split('\n')
  let firstDelta = 0
  const edited = lines.map((line, i) => {
    if (!e.shiftKey) {
      if (i === 0) firstDelta = unit.length
      return unit + line
    }
    const strip = /^ */.exec(line)?.[0].length ?? 0
    const cut = line.startsWith('\t') ? 1 : Math.min(strip, unit.length)
    if (i === 0) firstDelta = -cut
    return line.slice(cut)
  })
  const replaced = edited.join('\n')
  const next = text.slice(0, lineStart) + replaced + text.slice(end)
  const newStart = Math.max(lineStart, start + firstDelta)
  applyEdit(el, next, start === end ? newStart : lineStart, lineStart + replaced.length)
}
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <UidCode
      v-if="isLocked"
      :code="value"
      :language="language ?? undefined"
      :line-numbers="lineNumbers"
      :max-height="heightCss"
      copy
    />
    <div
      v-else
      ref="rootRef"
      class="admin-code-field"
      :style="heightCss ? { maxHeight: heightCss } : undefined"
      @keydown="onKeydown"
    >
      <div v-if="language" class="admin-code-field__lang">{{ language }}</div>
      <div class="admin-code-field__body">
        <pre v-if="lineNumbers" class="admin-code-field__gutter" aria-hidden="true">{{
          Array.from({ length: visibleRows }, (_, i) => i < lineCount ? String(i + 1) : '').join('\n')
        }}</pre>
        <UidTextarea
          class="admin-code-field__input"
          :model-value="value"
          :rows="visibleRows"
          :placeholder="placeholder ?? undefined"
          :name="name"
          resize="none"
          @update:model-value="onUpdate"
        />
      </div>
    </div>
  </UidFormField>
</template>

<style>
.admin-code-field {
  --admin-code-line: 20px;
  --admin-code-pad: var(--uid-space-sm, 8px);
  border: 1px solid var(--uid-border-default, var(--uid-border-subtle));
  border-radius: var(--uid-radius-md);
  overflow: auto;
  background: var(--uid-surface-sunken, var(--uid-surface-raised));
}
.admin-code-field:focus-within {
  border-color: var(--uid-accent);
}
.admin-code-field__lang {
  padding: var(--uid-space-2xs, 2px) var(--uid-space-sm, 8px);
  border-bottom: 1px solid var(--uid-border-subtle);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
  text-transform: lowercase;
}
.admin-code-field__body {
  display: flex;
  align-items: stretch;
}
.admin-code-field__gutter {
  margin: 0;
  padding: var(--admin-code-pad) var(--uid-space-xs, 6px);
  min-width: 2.5em;
  text-align: right;
  user-select: none;
  color: var(--uid-text-tertiary);
  border-right: 1px solid var(--uid-border-subtle);
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
  line-height: var(--admin-code-line);
}
.admin-code-field__input {
  flex: 1;
  min-width: 0;
}
.admin-code-field .admin-code-field__input textarea,
.admin-code-field .admin-code-field__input textarea:focus-visible {
  padding: var(--admin-code-pad);
  border: 0;
  border-radius: 0;
  box-shadow: none;
  background: transparent;
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-xs);
  line-height: var(--admin-code-line);
  white-space: pre;
  overflow-x: auto;
  overflow-y: hidden;
  tab-size: 4;
}
</style>
