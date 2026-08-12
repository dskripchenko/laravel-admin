import { inject, type InjectionKey } from 'vue'
import type { AdminBrand } from '../types/bootstrap'

/**
 * The panel's branding — name, logo, favicon, copyright — from bootstrap.brand
 * (config('admin.brand')). It is provided in createAdminApp and consumed by
 * the shell. A host customizes it through the config alone, with no patching
 * of the library.
 */
export const BRAND_KEY: InjectionKey<AdminBrand> = Symbol('adminBrand')

export function useBrand(): AdminBrand {
  return inject(BRAND_KEY, {})
}
