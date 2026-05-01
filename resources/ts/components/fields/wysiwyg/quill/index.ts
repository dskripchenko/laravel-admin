/**
 * Public exports для subpath @dskripchenko/laravel-admin/quill.
 *
 * Host подключает через:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { QuillField } from '@dskripchenko/laravel-admin/quill'
 *     registerField('wysiwyg', QuillField)
 *
 * @vueup/vue-quill и quill — peer-deps, host устанавливает их вручную.
 */

export { default as QuillField } from './QuillField.vue'
