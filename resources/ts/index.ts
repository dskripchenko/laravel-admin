/**
 * Entry-CSS: токены + темы UI-кита @dskripchenko/ui + admin-каркасные классы.
 * Импортируется через `import '@dskripchenko/laravel-admin/style.css'` в host'е.
 */
import './styles/admin.css'

/**
 * Точка входа SPA-бандла @dskripchenko/laravel-admin.
 *
 * Минимальный host-mount:
 *
 *     import { createAdminApp } from '@dskripchenko/laravel-admin'
 *     import '@dskripchenko/laravel-admin/style.css'
 *
 *     const { app } = createAdminApp(window.__ADMIN_BOOTSTRAP__)
 *     app.mount('#admin-app')
 *
 * Низкоуровневые экспорты (для расширений / партнёров):
 *   - createAdminClient() / AdminClient — axios-обёртка для admin API.
 *   - loadBootstrap() / readInlineBootstrap() / readCsrfFromMeta().
 *   - createAdminRouter() — фабрика router'а с manifest-driven routes.
 *   - useAuthStore() / useManifestStore() / ... — Pinia stores.
 *   - registerField() / registerLayout() / registerWidget() / ... — registries.
 *   - ApiError + подклассы (Unauthenticated/Forbidden/NotFound/Validation/Network).
 */

// Главный helper для host-проектов
export { createAdminApp } from './createAdminApp'
export type {
  CreateAdminAppOptions,
  CreateAdminAppPages,
  AdminAppHandle,
} from './createAdminApp'

// Default page-компоненты (host'ы могут заменить или переэкспортировать)
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
export { NotificationsDrawer } from './components/notifications'

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
