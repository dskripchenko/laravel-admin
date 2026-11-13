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
 *
 * TinymceDriver needs neither: as an engine of the Wysiwyg field
 * (`->driver('tinymce', ['script' => …])`) it loads a self-hosted TinyMCE by
 * its script URL — the one the prebuilt application uses:
 *
 *     import { registerWysiwygDriver } from '@dskripchenko/laravel-admin'
 *     registerWysiwygDriver('tinymce', () => import('@dskripchenko/laravel-admin/tinymce').then(m => m.TinymceDriver))
 */

export { default as TinymceField } from './TinymceField.vue'
export { default as TinymceDriver } from './TinymceDriver.vue'
