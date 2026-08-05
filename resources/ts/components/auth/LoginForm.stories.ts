import type { Meta, StoryObj } from '@storybook/vue3-vite'
import { createPinia, setActivePinia } from 'pinia'

import LoginForm from './LoginForm.vue'

/**
 * Форма входа — экран, который видят раньше остального.
 *
 * Здесь её можно посмотреть на любой ширине: раскладка входа ломалась
 * именно на телефоне, а на разработческом мониторе выглядела правильно.
 */
setActivePinia(createPinia())

const meta: Meta<typeof LoginForm> = {
  title: 'Вход/Форма входа',
  component: LoginForm,
  parameters: {
    layout: 'fullscreen',
  },
  render: (args) => ({
    components: { LoginForm },
    setup: () => ({ args }),
    // Карточка живёт в auth-каркасе: без него не видно ни центрирования,
    // ни полей страницы, а ломалось именно это.
    template: `
      <div class="admin-auth-page">
        <div class="uid-card uid-card--pad-none admin-auth-card">
          <div class="uid-card__body">
            <header class="admin-auth-card__hd">
              <div class="admin-auth-card__title">Printable</div>
              <div class="admin-auth-card__sub">Вход в панель</div>
            </header>
            <LoginForm v-bind="args" />
          </div>
        </div>
      </div>
    `,
  }),
}

export default meta
type Story = StoryObj<typeof LoginForm>

export const Обычная: Story = {
  args: {},
}

export const СоСсылками: Story = {
  name: 'Со ссылками',
  args: {
    forgotUrl: '/admin/forgot-password',
    ssoLinkLabel: 'Войти через SSO',
    ssoUrl: '/admin/sso',
  },
}

export const НаТелефоне: Story = {
  name: 'На телефоне',
  args: { forgotUrl: '/admin/forgot-password' },
  globals: { viewport: { value: 'phone' } },
}
