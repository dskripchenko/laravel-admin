/**
 * The public exports of the @dskripchenko/laravel-admin/tinymce subpath.
 *
 * A host wires it in like this:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { TinymceField } from '@dskripchenko/laravel-admin/tinymce'
 *     registerField('wysiwyg', TinymceField)
 *
 * @tinymce/tinymce-vue and tinymce are peer dependencies; the host installs them itself.
 */

export { default as TinymceField } from './TinymceField.vue'
