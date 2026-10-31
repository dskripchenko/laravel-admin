<script setup lang="ts">
/**
 * MarkdownEditorField — the backend's Field\MarkdownEditor:
 * @dskripchenko/markdown-editor bound to the form state. The value is a
 * Markdown string; the user edits it visually (the WYSIWYG engine), as
 * source, or both side by side.
 *
 * The panel's markdown block renderers (the built-in ones, or what a host has
 * registered with the kit's markdownBlockRenderersKey) reach the editor, so
 * ```mermaid, ```bpmn, ```kanban and the other block languages draw inside
 * it. The editor speaks the panel's language (English or Russian).
 *
 * - `modes` — the modes offered, the first shown first;
 * - `placeholder`, `minHeight`, `maxHeight`;
 * - `readonly` / `disabled` — browse only.
 *
 * The package is loaded on first use.
 */
import { computed, inject, ref } from 'vue'
import { markdownBlockRenderersKey, UidAlert, UidFormField, UidSkeleton } from '@dskripchenko/ui'
import { useFormState } from '../render/formState'
import { builtinMarkdownRenderers } from '../markdown/blockRenderers'
import { loadMarkdownEditor } from '../documents/loaders'
import { cssHeight, useLazyModule } from '../documents/useDocumentSource'
import { flowLocale } from '../flow/loadFlow'
import { trSafe as tr } from '../../stores/i18n'

type Mode = 'visual' | 'source' | 'split'

defineOptions({ inheritAttrs: false })

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  readonly?: boolean
  modes?: Mode[] | null
  minHeight?: number | string | null
  maxHeight?: number | string | null
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  placeholder: null,
  disabled: false,
  readonly: false,
  modes: null,
  minHeight: null,
  maxHeight: null,
})

const form = useFormState()
const blockRenderers = inject(markdownBlockRenderersKey, builtinMarkdownRenderers)
const { module, failed } = useLazyModule(loadMarkdownEditor)

const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

const value = computed<string>(() => {
  const v = form.getField(props.name)
  return v === null || v === undefined ? '' : String(v)
})

/** The editor speaks English and Russian, like the other packages. */
const locale = computed(() => flowLocale() as 'en' | 'ru')

/** The mode shown; the first one offered to begin with. */
const mode = ref<Mode>(props.modes?.[0] ?? 'visual')

function onUpdate(next: string): void {
  if (next !== value.value) form.setField(props.name, next)
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
    <div class="admin-editor-field admin-editor-field--markdown" :class="{ 'admin-editor-field--invalid': !!errorMsg }">
      <UidAlert v-if="failed" variant="danger">{{ tr('Не удалось загрузить редактор Markdown') }}</UidAlert>
      <UidSkeleton v-else-if="!module" :height="cssHeight(minHeight) ?? '240px'" />
      <component
        :is="module.MarkdownEditor"
        v-else
        v-model:mode="mode"
        :model-value="value"
        :modes="modes ?? undefined"
        :placeholder="placeholder ?? undefined"
        :readonly="readonly || disabled"
        :min-height="cssHeight(minHeight)"
        :max-height="cssHeight(maxHeight)"
        :block-renderers="blockRenderers"
        :locale="locale"
        @update:model-value="onUpdate"
      />
    </div>
  </UidFormField>
</template>
