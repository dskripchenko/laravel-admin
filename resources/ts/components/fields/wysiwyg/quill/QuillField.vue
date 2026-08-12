<script setup lang="ts">
/**
 * QuillField — the wrapper around @vueup/vue-quill, for the admin's field
 * registry.
 *
 * Wiring it into a host project, after createAdminApp:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { QuillField } from '@dskripchenko/laravel-admin/quill'
 *     // the peer dependencies `@vueup/vue-quill` and `quill` must be installed
 *     registerField('wysiwyg', QuillField)
 *
 * When a peer dependency is missing at build time, vite's bundler fails with
 * "Failed to resolve". That is expected: the /quill subpath is deliberately
 * optional, so import it only once the peers are in place.
 */
import { computed } from 'vue'
// @ts-expect-error — an optional peer dependency, whose types exist only once
// the host has installed `@vueup/vue-quill`. The core's vite build marks the
// package as external, so the import survives to runtime and resolves in the
// host.
import { QuillEditor, type Delta } from '@vueup/vue-quill'
import { useFormState } from '../../../render/formState'

/*
 * The host imports the CSS themes — snow and bubble — in its own entry. The
 * core does not, or its vite build would fail resolving the paths inside
 * node_modules: the peer dependency is optional and is not installed in the
 * core.
 *
 *     // demo/resources/js/admin.js
 *     import '@vueup/vue-quill/dist/vue-quill.snow.css'
 *     import '@vueup/vue-quill/dist/vue-quill.bubble.css'
 */

interface Props {
  name: string
  label?: string | null
  help?: string | null
  required?: boolean
  placeholder?: string | null
  /**
   * Quill's toolbar configuration. It takes `'essential' | 'minimal' | 'full'`
   * or a raw array of groups.
   */
  toolbar?: string | unknown[]
  /** 'snow' — the default toolbar; 'bubble' — inline; 'core' — no UI at all. */
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

function onUpdate(next: string | Delta): void {
  // QuillEditor's v-model:content gives an HTML string by default, since
  // contentType is 'html'. A Delta object is supported too, just in case.
  if (typeof next === 'string') {
    form.setField(props.name, next)
  } else {
    form.setField(props.name, JSON.stringify(next))
  }
}
</script>

<template>
  <div :class="['admin-field', { 'admin-field--invalid': errorMsg !== undefined }]">
    <label v-if="label" class="admin-field__label">
      <span>{{ label }}</span>
      <span v-if="required" class="admin-field__required" aria-hidden="true">*</span>
    </label>
    <div class="admin-field__control admin-field__control--quill">
      <QuillEditor
        :content="value"
        :toolbar="toolbar"
        :theme="theme"
        :placeholder="placeholder ?? undefined"
        :read-only="disabled"
        content-type="html"
        @update:content="onUpdate"
      />
    </div>
    <p v-if="errorMsg" class="admin-field__error">{{ errorMsg }}</p>
    <p v-else-if="help" class="admin-field__help">{{ help }}</p>
  </div>
</template>

<style>
/* Quill takes its min-height from CSS, which removes the jolt on initialization. */
.admin-field__control--quill .ql-container {
  min-height: 200px;
  font-size: 14px;
  font-family: inherit;
}
.admin-field__control--quill .ql-toolbar.ql-snow,
.admin-field__control--quill .ql-container.ql-snow {
  border-color: var(--uid-border-subtle);
}
.admin-field__control--quill .ql-toolbar.ql-snow {
  border-radius: var(--uid-radius-md) var(--uid-radius-md) 0 0;
  background: var(--uid-surface-base);
}
.admin-field__control--quill .ql-container.ql-snow {
  border-radius: 0 0 var(--uid-radius-md) var(--uid-radius-md);
  background: var(--uid-surface-raised);
  color: var(--uid-text-primary);
}
</style>
