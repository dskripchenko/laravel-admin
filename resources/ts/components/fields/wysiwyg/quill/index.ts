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
 *
 * QuillDriver is Quill 2 without @vueup/vue-quill, as an engine of the
 * Wysiwyg field (`->driver('quill')`) — the one the prebuilt application uses:
 *
 *     import { registerWysiwygDriver } from '@dskripchenko/laravel-admin'
 *     import 'quill/dist/quill.snow.css'
 *     registerWysiwygDriver('quill', () => import('@dskripchenko/laravel-admin/quill').then(m => m.QuillDriver))
 */

export { default as QuillField } from './QuillField.vue'
export { default as QuillDriver } from './QuillDriver.vue'
