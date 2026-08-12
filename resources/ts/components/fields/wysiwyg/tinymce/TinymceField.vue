<script setup lang="ts">
/**
 * TinymceField — the wrapper around @tinymce/tinymce-vue, for the admin
 * renderer's field registry.
 *
 * Wiring it into a host project:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { TinymceField } from '@dskripchenko/laravel-admin/tinymce'
 *     registerField('wysiwyg', TinymceField)
 *
 * After that the manifest's `{ type: 'wysiwyg', name: 'body', ... }` nodes are
 * drawn by TinyMCE.
 *
 * The form state comes from the core library's useFormState: v-model is passed
 * into the TinyMCE component and the changes travel back through onInput into
 * form.setField.
 */
import { computed, h, defineComponent, type PropType } from 'vue'
import { useFormState } from '../../../render/formState'
import { trSafe as tr } from '../../../../stores/i18n'

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  /**
   * The TinyMCE init configuration: height, plugins, toolbar, menubar,
   * language and so on. It is merged with the host's defaults on the frontend.
   */
  init?: Record<string, unknown>
  /**
   * The licence key for TinyMCE 7 and later; null means the self-hosted GPL
   * route.
   */
  apiKey?: string | null
  /**
   * The handler behind image uploads; see images_upload_handler in TinyMCE's
   * documentation. By default it uses the core admin's upload endpoint.
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
 * @tinymce/tinymce-vue is lazy-loaded through an async resolveComponent, so it
 * is not required by the tests; a host installs it as a peer dependency.
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
      placeholder: tr('@tinymce/tinymce-vue не загружен — host передаёт actual Editor'),
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
