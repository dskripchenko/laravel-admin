<script setup lang="ts">
/**
 * MarkdownField — the backend's Field\Markdown: a markdown source in a
 * UidTextarea, a small formatting toolbar and a live preview.
 *
 * The preview is drawn by the built-in renderer (support/markdown.ts), which
 * escapes the source first, so raw HTML in the text is never executed. Three
 * modes: write, preview, and split (both side by side). `->preview(false)`
 * hides the preview, `->toolbar(false)` the toolbar, `->height()` sets the
 * editor's height.
 */
import { computed, nextTick, ref } from 'vue'
import type { Component } from 'vue'
import { Bold, Code, Heading2, Italic, Link, List } from 'lucide-vue-next'
import { UidButton, UidFormField, UidIcon, UidTextarea } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { renderMarkdown } from './support/markdown'
import { trSafe as tr } from '../../stores/i18n'

type Mode = 'write' | 'preview' | 'split'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  /** Whether the preview is available; true by default. */
  preview?: boolean
  /** Whether the formatting toolbar is shown; true by default. */
  toolbar?: boolean
  /** The editor's height: a number of pixels or a CSS length. */
  height?: number | string | null
  rows?: number
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  readonly: false,
  preview: true,
  toolbar: true,
  height: null,
  rows: 8,
})

const form = useFormState()
const value = computed<string>(() => {
  const v = form.getField(props.name)
  return v === null || v === undefined ? '' : String(v)
})
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])
const isLocked = computed<boolean>(() => props.disabled || props.readonly)

const mode = ref<Mode>('write')
const html = computed<string>(() => renderMarkdown(value.value))
const showEditor = computed<boolean>(() => !props.preview || mode.value !== 'preview')
const showPreview = computed<boolean>(() => props.preview && mode.value !== 'write')

const heightCss = computed<string | undefined>(() => {
  if (props.height === null || props.height === '') return undefined
  return typeof props.height === 'number' ? `${props.height}px` : props.height
})

const MODES: Array<{ key: Mode; label: string }> = [
  { key: 'write', label: tr('Редактор') },
  { key: 'preview', label: tr('Просмотр') },
  { key: 'split', label: tr('Рядом') },
]

const editorRef = ref<HTMLElement | null>(null)

function onUpdate(next: string): void {
  form.setField(props.name, next)
}

function textarea(): HTMLTextAreaElement | null {
  return editorRef.value?.querySelector('textarea') ?? null
}

/**
 * Wraps the selection in `before`/`after`; with nothing selected, inserts
 * `placeholder` between them and selects it, so typing replaces it.
 */
async function wrapSelection(before: string, after: string, placeholder: string): Promise<void> {
  const el = textarea()
  const text = value.value
  const start = el?.selectionStart ?? text.length
  const end = el?.selectionEnd ?? text.length
  const selected = text.slice(start, end) || placeholder
  onUpdate(text.slice(0, start) + before + selected + after + text.slice(end))
  await nextTick()
  el?.focus()
  el?.setSelectionRange(start + before.length, start + before.length + selected.length)
}

/** Prefixes every selected line (or the current one) with `prefix`. */
async function prefixLines(prefix: string): Promise<void> {
  const el = textarea()
  const text = value.value
  const start = el?.selectionStart ?? text.length
  const end = el?.selectionEnd ?? text.length
  const lineStart = text.lastIndexOf('\n', start - 1) + 1
  const block = text.slice(lineStart, end)
  const prefixed = block.split('\n').map((l) => prefix + l).join('\n')
  onUpdate(text.slice(0, lineStart) + prefixed + text.slice(end))
  await nextTick()
  el?.focus()
  el?.setSelectionRange(lineStart, lineStart + prefixed.length)
}

const ACTIONS: Array<{ key: string; label: string; icon: Component; run: () => Promise<void> }> = [
  { key: 'bold', label: tr('Жирный'), icon: Bold, run: () => wrapSelection('**', '**', tr('текст')) },
  { key: 'italic', label: tr('Курсив'), icon: Italic, run: () => wrapSelection('_', '_', tr('текст')) },
  { key: 'heading', label: tr('Заголовок'), icon: Heading2, run: () => prefixLines('## ') },
  { key: 'link', label: tr('Ссылка'), icon: Link, run: () => wrapSelection('[', '](https://)', tr('текст')) },
  { key: 'list', label: tr('Список'), icon: List, run: () => prefixLines('- ') },
  { key: 'code', label: tr('Код'), icon: Code, run: () => wrapSelection('`', '`', 'code') },
]
</script>

<template>
  <UidFormField
    :label="label ?? undefined"
    :hint="help ?? undefined"
    :error="errorMsg"
    :required="required"
    :disabled="disabled"
  >
    <div class="admin-markdown-field" :class="{ 'admin-markdown-field--split': showEditor && showPreview }">
      <div v-if="(toolbar && !isLocked) || preview" class="admin-markdown-field__bar">
        <div v-if="toolbar && !isLocked && showEditor" class="admin-markdown-field__tools">
          <UidButton
            v-for="a in ACTIONS"
            :key="a.key"
            variant="ghost"
            size="sm"
            :aria-label="a.label"
            :title="a.label"
            :data-action="a.key"
            @click="a.run"
          >
            <UidIcon :icon="a.icon" :size="14" aria-hidden="true" />
          </UidButton>
        </div>
        <div v-if="preview" class="admin-markdown-field__modes" role="group">
          <UidButton
            v-for="m in MODES"
            :key="m.key"
            :variant="mode === m.key ? 'secondary' : 'ghost'"
            size="sm"
            :aria-pressed="mode === m.key"
            :data-mode="m.key"
            @click="mode = m.key"
          >
            {{ m.label }}
          </UidButton>
        </div>
      </div>
      <div class="admin-markdown-field__panes">
        <div v-if="showEditor" ref="editorRef" class="admin-markdown-field__editor" :style="heightCss ? { '--admin-md-height': heightCss } : undefined">
          <UidTextarea
            :model-value="value"
            :rows="rows"
            :placeholder="placeholder ?? undefined"
            :disabled="disabled"
            :readonly="readonly"
            :name="name"
            resize="vertical"
            @update:model-value="onUpdate"
          />
        </div>
        <!-- eslint-disable-next-line vue/no-v-html -- renderMarkdown escapes the source before adding markup -->
        <div v-if="showPreview" class="admin-markdown admin-markdown-field__preview" :style="heightCss ? { minHeight: heightCss } : undefined" v-html="html" />
      </div>
    </div>
  </UidFormField>
</template>

<style>
.admin-markdown-field {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs, 6px);
}
.admin-markdown-field__bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--uid-space-sm, 8px);
  flex-wrap: wrap;
}
.admin-markdown-field__tools,
.admin-markdown-field__modes {
  display: flex;
  gap: var(--uid-space-2xs, 2px);
}
.admin-markdown-field__modes {
  margin-left: auto;
}
.admin-markdown-field__panes {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--uid-space-sm, 8px);
}
.admin-markdown-field--split .admin-markdown-field__panes {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.admin-markdown-field__editor textarea {
  font-family: var(--uid-font-family-mono);
  font-size: var(--uid-font-size-sm);
  min-height: var(--admin-md-height, auto);
}
.admin-markdown-field__preview {
  padding: var(--uid-space-sm, 8px) var(--uid-space-md, 12px);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  background: var(--uid-surface-raised);
  overflow: auto;
}
@media (max-width: 720px) {
  .admin-markdown-field--split .admin-markdown-field__panes {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
