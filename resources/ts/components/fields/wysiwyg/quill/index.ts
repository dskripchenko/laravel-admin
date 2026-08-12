/**
 * The public exports of the @dskripchenko/laravel-admin/quill subpath.
 *
 * A host wires it in like this:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { QuillField } from '@dskripchenko/laravel-admin/quill'
 *     registerField('wysiwyg', QuillField)
 *
 * @vueup/vue-quill and quill are peer dependencies; the host installs them itself.
 */

export { default as QuillField } from './QuillField.vue'
