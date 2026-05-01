<script setup lang="ts">
/**
 * TinymceField — wrapper над @tinymce/tinymce-vue для использования в
 * field-registry admin-renderer'а.
 *
 * Подключение в host-проекте:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { TinymceField } from '@dskripchenko/laravel-admin-tinymce'
 *     registerField('wysiwyg', TinymceField)
 *
 * После этого manifest-узлы `{ type: 'wysiwyg', name: 'body', ... }`
 * рендерятся через TinyMCE.
 *
 * Form-state — через useFormState из core lib. Прокидываем v-model в
 * TinyMCE-component, изменения через onInput → form.setField.
 */
import { computed, h, defineComponent, type PropType } from 'vue'
import { useFormState } from '@dskripchenko/laravel-admin'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /**
   * Конфиг TinyMCE init (height/plugins/toolbar/menubar/language/...).
   * Объединяется с host-default'ами на frontend'е.
   */
  init?: Record<string, unknown>
  /**
   * License key для TinyMCE 7+. null = self-hosted GPL flow.
   */
  apiKey?: string | null
  /**
   * URL-handler для image upload'ов. См. images_upload_handler в TinyMCE
   * docs. По умолчанию использует core admin upload endpoint.
   */
  imageUploadUrl?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  label: null,
  help: null,
  required: false,
  init: () => ({}),
  apiKey: null,
  imageUploadUrl: '/api/admin/uploads',
  disabled: false,
})

const form = useFormState()
const value = computed<string>(() => (form.getField(props.name) as string | undefined) ?? '')
const errorMsg = computed<string | undefined>(() => form.errors[props.name]?.[0])

function onUpdate(next: string): void {
  form.setField(props.name, next)
}

const editorInit = computed<Record<string, unknown>>(() => ({
  height: 400,
  menubar: false,
  branding: false,
  plugins: 'advlist autolink lists link image charmap preview anchor pagebreak searchreplace wordcount visualblocks code fullscreen insertdatetime media table emoticons help paste',
  toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code preview fullscreen',
  language: 'ru',
  paste_data_images: true,
  images_upload_url: props.imageUploadUrl,
  ...(props.init ?? {}),
}))

/**
 * @tinymce/tinymce-vue lazy-loaded через async resolveComponent —
 * не required для tests; host подключает через peer-dep.
 */
const Editor = defineComponent({
  props: {
    modelValue: String,
    init: Object as PropType<Record<string, unknown>>,
    apiKey: String,
    disabled: Boolean,
  },
  setup() {
    return () => h('textarea', {
      class: 'tinymce-fallback',
      placeholder: '@tinymce/tinymce-vue не загружен — host передаёт actual Editor',
    })
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
        :model-value="value"
        :init="editorInit"
        :api-key="apiKey ?? undefined"
        :disabled="disabled"
        @update:model-value="onUpdate"
      />
    </div>
    <p v-if="errorMsg" class="admin-field__error">{{ errorMsg }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>
