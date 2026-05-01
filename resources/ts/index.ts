/**
 * Entry-CSS: токены + темы UI-кита @dskripchenko/ui + admin-каркасные классы.
 * Импортируется через `import '@dskripchenko/laravel-admin/style.css'` в host'е.
 */
import './styles/admin.css'

/**
 * Точка входа SPA-бандла @dskripchenko/laravel-admin.
 *
 * Текущий публичный API:
 *   - createAdminClient() / AdminClient — axios-обёртка для admin API
 *     с автоматической обработкой envelope `{success, payload}`,
 *     CSRF-token'ов и подкласс'ов ApiError.
 *   - loadBootstrap() — резолв payload'а в обеих стратегиях (inline/xhr).
 *   - readInlineBootstrap() / readCsrfFromMeta() — низкоуровневые helpers.
 *   - ApiError + подклассы (Unauthenticated/Forbidden/NotFound/Validation/
 *     Network) для type-narrow'инга в потребителях.
 *
 * createAdmin() (mount Vue-приложения) — на следующих фазах после
 * api-stack'а: stores, router, renderers.
 */

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
  ThemeToggle,
  LocaleSwitcher,
  NotificationBell,
  UserMenu,
} from './components/shell'

// Auth pages (login + 2FA)
export { LoginPage, LoginForm, TwoFactorForm } from './components/auth'

// Resource pages (index/form/view)
export { ResourceIndexPage } from './components/resource'

// Resource index store
export { useResourceIndexStore } from './stores/resourceIndex'
export type { IndexMeta, IndexParams } from './stores/resourceIndex'

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
