/**
 * Public exports для subpath @dskripchenko/laravel-admin/tinymce.
 *
 * Host подключает через:
 *
 *     import { registerField } from '@dskripchenko/laravel-admin'
 *     import { TinymceField } from '@dskripchenko/laravel-admin/tinymce'
 *     registerField('wysiwyg', TinymceField)
 *
 * @tinymce/tinymce-vue и tinymce — peer-deps, host устанавливает их вручную.
 */

export { default as TinymceField } from './TinymceField.vue'
