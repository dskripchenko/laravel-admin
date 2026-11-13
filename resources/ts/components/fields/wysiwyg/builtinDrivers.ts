/**
 * The engines of the prebuilt application (app.ts): Quill and TinyMCE, each a
 * dynamic import, so each is a chunk of its own that a page downloads only
 * when it shows a field with `->driver('quill')` or `->driver('tinymce')`.
 *
 * Quill (BSD-3-Clause) is bundled into that chunk with its snow theme.
 * TinyMCE is not: the chunk holds only the driver, which loads the host's
 * self-hosted TinyMCE by URL (see tinymce/loadTinymce.ts).
 *
 * Not exported from the library: a host with its own build registers the
 * drivers it has installed, with registerWysiwygDriver().
 */
import { registerWysiwygDriver } from './drivers'

export function registerBuiltinWysiwygDrivers(): void {
  registerWysiwygDriver('quill', () =>
    Promise.all([import('./quill/QuillDriver.vue'), import('quill/dist/quill.snow.css')]).then(([m]) => m.default),
  )
  registerWysiwygDriver('tinymce', () => import('./tinymce/TinymceDriver.vue').then(m => m.default))
}
