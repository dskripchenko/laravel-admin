<script setup lang="ts">
/**
 * WysiwygField — обёртка над `@dskripchenko/wysiwyg` для field-registry.
 *
 * Default-фолбэк для type='wysiwyg' в laravel-admin'е. Использует
 * собственный zero-dep editor пакета `@dskripchenko/wysiwyg` (без
 * Tiptap/ProseMirror/Quill peer-deps). CSS темы прокидываются из
 * admin-стилей через CSS-переменные `--dsk-wysiwyg-*`.
 */
import { computed } from 'vue'
import { DskWysiwyg } from '@dskripchenko/wysiwyg'
import '@dskripchenko/wysiwyg/style.css'
import { useFormState } from '../render/formState'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  disabled?: boolean
  /** Минимальная высота editor-area. */
  minHeight?: string
  /** Максимальная высота (после — overflow). */
  maxHeight?: string
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  disabled: false,
  minHeight: '200px',
  maxHeight: undefined,
})

const form = useFormState()

const value = computed<string>(() => (form.getField(props.name) as string | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(html: string): void {
  form.setField(props.name, html)
}
</script>

<template>
  <div :class="['admin-field', { 'admin-field--invalid': errorMsg !== undefined }]">
    <label v-if="label" class="admin-field__label">
      <span>{{ label }}</span>
      <span v-if="required" class="admin-field__required" aria-hidden="true">*</span>
    </label>
    <div class="admin-field__control">
      <DskWysiwyg
        :model-value="value"
        :placeholder="placeholder ?? undefined"
        :readonly="disabled"
        :min-height="minHeight"
        :max-height="maxHeight"
        @update:model-value="onUpdate"
      />
    </div>
    <p v-if="errorMsg" class="admin-field__error">{{ errorMsg }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>
