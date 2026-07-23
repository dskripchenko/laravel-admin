import { inject, type InjectionKey } from 'vue'
import type { AdminBrand } from '../types/bootstrap'

/**
 * Брендинг панели (name/logo/favicon/copyright) из bootstrap.brand
 * (config('admin.brand')). Провайдится в createAdminApp, потребляется
 * shell'ом. Host кастомизирует чисто через config — без патча библиотеки.
 */
export const BRAND_KEY: InjectionKey<AdminBrand> = Symbol('adminBrand')

export function useBrand(): AdminBrand {
  return inject(BRAND_KEY, {})
}
