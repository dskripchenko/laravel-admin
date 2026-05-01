<script setup lang="ts">
/**
 * QuillField — wrapper над @vueup/vue-quill для использования в
 * field-registry admin-renderer'а.
 *
 * Подключение в host-проекте:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { QuillField } from '@dskripchenko/laravel-admin-quill'
 *     registerField('wysiwyg', QuillField)
 *
 * Для image upload'ов host регистрирует кастомный handler в Quill Toolbar
 * (наша обёртка предоставляет default — POST /api/admin/uploads).
 */
import { computed, h, defineComponent } from 'vue'
import { useFormState } from '@dskripchenko/laravel-admin'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  /**
   * Quill toolbar config — string или array. См. https://quilljs.com/docs/modules/toolbar/.
   */
  toolbar?: string | unknown[]
  /**
   * Имя темы Quill ('snow' default | 'bubble' | 'core').
   */
  theme?: 'snow' | 'bubble' | 'core'
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  placeholder: null,
  required: false,
  toolbar: 'full',
  theme: 'snow',
  disabled: false,
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string): void {
  form.setField(props.name, next)
}

const Editor = defineComponent({
  props: {
    content: String,
    toolbar: [String, Array],
    theme: String,
    placeholder: String,
    readOnly: Boolean,
  },
  setup() {
    return () => h('div', {
      class: 'quill-fallback',
    }, '@vueup/vue-quill не загружен — host передаёт actual Editor через peer-dep')
  },
})
</script>

<template>
  <div :class="['admin-field', { 'admin-field--invalid': errorMsg !== undefined }]">
    <label v-if="label" class="admin-field__label">
      <span>{{ label }}</span>
      <span v-if="required" class="admin-field__required" aria-hidden="true">*</span>
    </label>
    <div class="admin-field__control">
      <Editor
        :content="value"
        :toolbar="toolbar"
        :theme="theme"
        :placeholder="placeholder ?? undefined"
        :read-only="disabled"
        @update:content="onUpdate"
      />
    </div>
    <p v-if="errorMsg" class="admin-field__error">{{ errorMsg }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>
