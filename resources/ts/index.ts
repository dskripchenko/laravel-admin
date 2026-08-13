/**
 * The entry CSS: the tokens and themes of the @dskripchenko/ui kit plus the
 * admin shell's classes. A host imports it as
 * `import '@dskripchenko/laravel-admin/style.css'`.
 */
import './styles/admin.css'

/**
 * The entry point of the @dskripchenko/laravel-admin SPA bundle.
 *
 * The minimal mount in a host:
 *
 *     import { createAdminApp } from '@dskripchenko/laravel-admin'
 *     import '@dskripchenko/laravel-admin/style.css'
 *
 *     const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
 *     app.mount('#admin-app')
 *
 * The lower-level exports, for extensions and partners:
 *   - createAdminClient() / AdminClient — the axios wrapper of the admin API.
 *   - loadBootstrap() / readInlineBootstrap() / readCsrfFromMeta().
 *   - createAdminRouter() — the router factory, with manifest-driven routes.
 *   - useAuthStore() / useManifestStore() / … — the Pinia stores.
 *   - registerField() / registerLayout() / registerWidget() / … — the registries.
 *   - ApiError and its subclasses: Unauthenticated, Forbidden, NotFound,
 *     Validation, Network.
 */

// The main helper for the host projects
export { createAdminApp } from './createAdminApp'
export type {
  CreateAdminAppOptions,
  CreateAdminAppPages,
  AdminAppHandle,
} from './createAdminApp'

// The default page components; a host may replace or re-export them
export { default as AdminApp } from './components/AdminApp.vue'
export { default as HomePage } from './components/HomePage.vue'
export { default as ForbiddenPage } from './components/ForbiddenPage.vue'
export { default as NotFoundPage } from './components/NotFoundPage.vue'
export { default as SettingsPage } from './components/SettingsPage.vue'
export { default as UnknownScreenPage } from './components/UnknownScreenPage.vue'
export { default as ScreenPage } from './components/ScreenPage.vue'

export { createAdminClient } from './api/client'
export type { AdminClient, ClientOptions } from './api/client'

export { loadBootstrap, readInlineBootstrap, readCsrfFromMeta } from './api/bootstrap'
export { useBrand, BRAND_KEY } from './composables/useBrand'

export {
  isSuccess,
  isError,
} from './api/envelope'
export type {
  ApiEnvelope,
  SuccessEnvelope,
  ErrorEnvelope,
} from './api/envelope'

export {
  ApiError,
  UnauthenticatedError,
  ForbiddenError,
  NotFoundError,
  ValidationError,
  NetworkError,
  toApiError,
} from './api/errors'

export type {
  AdminBootstrap,
  AdminUser,
  AdminBrand,
  AdminBootstrapConfig,
} from './types/bootstrap'

// Pinia stores
export {
  setAdminClient,
  getAdminClient,
  hasAdminClient,
  clearAdminClient,
  useAuthStore,
  useManifestStore,
  useThemeStore,
  useLocaleStore,
  useNotificationsStore,
} from './stores'
export type {
  LoginPayload,
  PendingChallenge,
  AdminManifest,
  ManifestResourceMeta,
  ManifestScreenMeta,
  ManifestSettingsMeta,
  NotificationItem,
  NotificationFilter,
} from './stores'

// Router
export {
  createAdminRouter,
  buildRoutesFromManifest,
  createAuthGuard,
  createTitleGuard,
} from './router'
export type {
  AdminRouter,
  AdminRouterOptions,
  RouteComponentResolver,
  AdminRouteComponent,
  RouteMeta,
  AuthGuardOptions,
  TitleGuardOptions,
} from './router'

// Menu store
export { useMenuStore } from './stores/menu'
export type { MenuItem, MenuGroup } from './stores/menu'

// i18n. Exported because a host cannot translate its own components without
// it — and until now it could not: the store lived here but never reached the
// public entry, so every string in a host component stayed in the language it
// was typed in, however carefully the host filled `lang/{locale}.json`.
//
// The dictionary itself has always been delivered: BootstrapBuilder merges the
// host's JSON translations into the bootstrap bag. Only the reader was missing.
//
//   import { trSafe as tr } from '@dskripchenko/laravel-admin'
//   tr('The key is required')   // key = the source string, as on the server
export { trSafe, useI18nStore, tRaw } from './stores/i18n'

// Shell components
export {
  AdminShell,
  AdminTopBar,
  AdminSidebar,
  GlobalSearch,
  ThemeToggle,
  LocaleSwitcher,
  NotificationBell,
  UserMenu,
} from './components/shell'

// Auth pages (login + 2FA)
export { LoginPage, LoginForm, TwoFactorForm } from './components/auth'

// Resource pages (index/form/view)
export { ResourceIndexPage, ResourceFormPage, ResourceViewPage } from './components/resource'

// Notifications drawer
export { NotificationsDrawer, NotificationsPage } from './components/notifications'

// Profile page
export { ProfilePage } from './components/profile'

// Import wizard
export { ImportWizardPage } from './components/import'

// Field gallery / docs page
export { FieldGalleryPage } from './components/gallery'

// Dashboard
export {
  DashboardPage,
  WidgetRenderer,
  StatWidget,
  BarChartWidget,
  DonutChartWidget,
  RecentTableWidget,
  HeatmapWidget,
  GaugeWidget,
  MarkdownWidget,
  UnknownWidget,
  registerWidget,
  registerWidgets,
  getWidget,
  hasWidget,
  listWidgets,
  clearWidgetRegistry,
  registerBuiltinWidgets,
} from './components/dashboard'
export type { WidgetNode } from './components/dashboard'

// Resource stores
export { useResourceIndexStore } from './stores/resourceIndex'
export type { IndexMeta, IndexParams } from './stores/resourceIndex'
export { useResourceFormStore } from './stores/resourceForm'
export type { FormMode } from './stores/resourceForm'

// Infolist (read-only display)
export {
  InfolistRenderer,
  TextEntry,
  BadgeEntry,
  IconEntry,
  KeyValueEntry,
  UnknownEntry,
  registerInfolistEntry,
  registerInfolistEntries,
  getInfolistEntry,
  hasInfolistEntry,
  listInfolistEntries,
  clearInfolistRegistry,
  registerBuiltinInfolistEntries,
  provideRecord,
  useRecord,
  tryUseRecord,
} from './components/infolist'
export type { InfolistNode } from './components/infolist'

// JSON-driven rendering: registry, renderers, builtin fields/layouts, form-state
export {
  FieldRenderer,
  LayoutRenderer,
  registerField,
  registerLayout,
  getField,
  getLayout,
  hasField,
  hasLayout,
  listFields,
  listLayouts,
  clearRegistry,
  registerComponents,
  registerBuiltinComponents,
  provideFormState,
  useFormState,
  tryUseFormState,
  TextField,
  TextAreaField,
  NumberField,
  SelectField,
  ComboboxField,
  CheckboxField,
  DateField,
  UnknownField,
  RowsLayout,
  ColumnsLayout,
  SectionLayout,
  TabsLayout,
} from './components/render'
export type {
  FieldNode,
  LayoutNode,
  ComponentBundle,
  FormStateContext,
} from './components/render'
