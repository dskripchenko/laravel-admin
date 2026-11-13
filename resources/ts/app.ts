/**
 * The entry of the prebuilt admin application.
 *
 * `npm run build:app` bundles it, with Vue, the UI kit and the default
 * WYSIWYG editor, into the package's public/ directory; `admin:install`
 * publishes that to the host's public/vendor/admin, and the shell loads it
 * when the host has no Vite build of its own. A host that needs its own
 * fields, widgets or pages builds its own entry instead
 * (`admin:install --custom-build`).
 *
 * Quill and TinyMCE, the engines of `Wysiwyg::make(...)->driver(...)`, are
 * registered as lazy chunks: a page without such a field never loads them.
 */
import '@dskripchenko/ui/styles/all.css'
import './styles/admin.css'
import '@dskripchenko/wysiwyg/style.css'

import { createAdminApp } from './createAdminApp'
import { registerBuiltinWysiwygDrivers } from './components/fields/wysiwyg/builtinDrivers'

registerBuiltinWysiwygDrivers()

const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__!)
app.mount('#admin-app')
