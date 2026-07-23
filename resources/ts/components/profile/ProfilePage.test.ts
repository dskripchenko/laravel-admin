import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import ProfilePage from './ProfilePage.vue'
import { setAdminClient, clearAdminClient } from '../../stores/registry'
import { createAdminClient } from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore } from '../../stores/theme'
import { useLocaleStore } from '../../stores/locale'
import type { AdminBootstrap, AdminUser } from '../../types/bootstrap'

const mkUser = (overrides: Partial<AdminUser> = {}): AdminUser => ({
  id: 1, name: 'Иван Петров', email: 'ivan@example.com',
  avatar: null, locale: null, theme: null, twoFactorEnabled: false,
  ...overrides,
})

const mkBootstrap = (overrides: Partial<AdminBootstrap> = {}): AdminBootstrap => ({
  csrf: '', baseUrl: '', apiUrl: '', locale: 'ru',
  availableLocales: ['ru', 'en'], theme: 'light', availableThemes: ['light', 'dark'],
  brand: {}, user: mkUser(), permissions: [], manifestVersion: null,
  plugins: [], unread_notifications_count: 0,
  config: { manifest: { etag: true }, bootstrap: { strategy: 'inline' } },
  ...overrides,
})

describe('ProfilePage', () => {
  let mock: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    const c = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(c)
    mock = new MockAdapter(c.raw)
    useAuthStore().hydrate(mkBootstrap())
    useThemeStore().hydrate(mkBootstrap())
    useLocaleStore().hydrate(mkBootstrap())
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
  })

  it('renders page header', () => {
    const w = mount(ProfilePage)
    expect(w.find('.admin-page__title').text()).toBe('Profile')
  })

  it('renders 4 nav items', () => {
    const w = mount(ProfilePage)
    const items = w.findAll('.admin-profile__nav-item').map((b) => b.text())
    expect(items).toEqual(['Основное', 'Безопасность', 'API токены', 'Сессии'])
  })

  it('General section is active by default and shows user fields', () => {
    const w = mount(ProfilePage)
    const inputs = w.findAll('input')
    // Имя input prefilled
    expect((inputs[0].element as HTMLInputElement).value).toBe('Иван Петров')
    expect((inputs[1].element as HTMLInputElement).value).toBe('ivan@example.com')
  })

  it('clicking nav switches section', async () => {
    const w = mount(ProfilePage)
    const nav = w.findAll('.admin-profile__nav-item')
    await nav[1].trigger('click')
    expect(w.find('.admin-profile__card-title').text()).toContain('Двухфакторная')
    expect(w.emitted('update:section')?.[0]).toEqual(['security'])
  })

  it('Security section shows 2FA badge "Отключена" when not enabled', async () => {
    const w = mount(ProfilePage)
    await w.findAll('.admin-profile__nav-item')[1].trigger('click')
    expect(w.text()).toContain('Отключена')
  })

  it('Security section shows "Включена" + встроенный визард (disable/regenerate) when 2FA on', async () => {
    useAuthStore().hydrate(mkBootstrap({ user: mkUser({ twoFactorEnabled: true }) }))
    const w = mount(ProfilePage)
    await w.findAll('.admin-profile__nav-item')[1].trigger('click')
    expect(w.text()).toContain('Включена')
    // Кнопки живут во встроенном TwoFactorSetup (.admin-2fa), не placeholder-footer.
    const buttons = w.findAll('.admin-2fa button').map((b) => b.text())
    expect(buttons).toContain('Отключить 2FA')
    expect(buttons.some((t) => t.includes('Перегенерировать'))).toBe(true)
  })

  it('Security section renders the working enable-2fa wizard (no disabled placeholder) when off', async () => {
    const w = mount(ProfilePage)
    await w.findAll('.admin-profile__nav-item')[1].trigger('click')
    const enableBtn = w.findAll('.admin-2fa button').find((b) => b.text() === 'Включить 2FA')
    expect(enableBtn).toBeTruthy()
    expect(enableBtn!.attributes('disabled')).toBeUndefined()
  })

  it('emits save with profile fields', async () => {
    const w = mount(ProfilePage)
    const saveBtn = w.findAll('button').find((b) => b.text() === 'Сохранить')
    await saveBtn!.trigger('click')
    const ev = w.emitted('save')?.[0]?.[0] as Record<string, unknown> | undefined
    expect(ev?.name).toBe('Иван Петров')
    expect(ev?.email).toBe('ivan@example.com')
    expect(ev?.locale).toBe('ru')
    expect(ev?.theme).toBe('light')
  })

  it('emits avatar-replace on Заменить click', async () => {
    const w = mount(ProfilePage)
    const replaceBtn = w.findAll('button').find((b) => b.text() === 'Заменить')
    await replaceBtn!.trigger('click')
    expect(w.emitted('avatar-replace')).toBeTruthy()
  })

  it('renders host slot for tokens section', async () => {
    const w = mount(ProfilePage, {
      slots: {
        tokens: '<div class="my-tokens">my custom tokens table</div>',
      },
    })
    await w.findAll('.admin-profile__nav-item')[2].trigger('click')
    expect(w.find('.my-tokens').exists()).toBe(true)
  })
})
