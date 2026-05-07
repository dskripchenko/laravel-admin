<script setup lang="ts">
/**
 * QuillField — wrapper над @vueup/vue-quill для field-registry admin'а.
 *
 * Подключение в host-проекте (после createAdminApp):
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { QuillField } from '@dskripchenko/laravel-admin/quill'
 *     // peer-deps: `@vueup/vue-quill` + `quill` должны быть установлены
 *     registerField('wysiwyg', QuillField)
 *
 * Если peer-dep не найден на этапе сборки vite — bundler упадёт с
 * "Failed to resolve". Это нормально: subpath /quill явно опциональный,
 * подключайте только если установили peer'ы.
 *
 * CSS темы импортируем здесь — побочный эффект подгружает стили в bundle.
 */
import { computed } from 'vue'
// @ts-expect-error — optional peer-dep, типы доступны только когда host
// установил `@vueup/vue-quill`. Vite-сборка core помечает пакет как external,
// поэтому импорт остаётся в runtime и резолвится в host'е.
import { QuillEditor, type Delta } from '@vueup/vue-quill'
import { useFormState } from '../../../render/formState'

/*
 * CSS-темы (snow/bubble) host подключает сам в своём entry — core их не
 * импортирует, иначе vite-сборка core упадёт на резолве путей в
 * node_modules (peer-dep optional, в core node_modules не установлен).
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
   * Quill toolbar config. Принимает `'essential' | 'minimal' | 'full'`
   * либо raw-конфиг массивом групп.
   */
  toolbar?: string | unknown[]
  /** 'snow' (default toolbar) | 'bubble' (inline) | 'core' (без UI). */
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
  // QuillEditor v-model:content по умолчанию отдаёт HTML строку (contentType
  // = 'html'). На всякий случай — поддерживаем и Delta-объект.
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
/* Quill принимает min-height через CSS — снимает дёрганье инициализации. */
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
