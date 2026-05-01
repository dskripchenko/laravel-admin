<script setup lang="ts">
/**
 * LoginPage поверх UID design handoff: centered auth-card 400/440 px на
 * `--uid-surface-base`-фоне с corner-actions (theme + locale toggles).
 *
 * Композиция: LoginForm либо TwoFactorForm в зависимости от
 * auth.isChallengePending. Редирект на main завязан на watch
 * (auth.isAuthenticated) — это переживает unmount форм при смене ветки.
 *
 * `?redirect`-query учитывает только относительные пути (защита от
 * open-redirect).
 */
import { onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { UidCard } from '@dskripchenko/ui'
import { useAuthStore } from '../../stores/auth'
import LoginForm from './LoginForm.vue'
import TwoFactorForm from './TwoFactorForm.vue'
import ThemeToggle from '../shell/widgets/ThemeToggle.vue'
import LocaleSwitcher from '../shell/widgets/LocaleSwitcher.vue'

interface Props {
  brandName?: string
  brandMark?: string
  brandLogo?: string | null
  homeRouteName?: string
  redirectQueryKey?: string
  /** URL «Забыли пароль?» — пробрасывается в LoginForm. */
  forgotUrl?: string | null
  /** SSO-link (label + url) — пробрасывается в LoginForm. */
  ssoLinkLabel?: string | null
  ssoUrl?: string | null
  /** Показывать ли theme/locale toggle'ы в углу. */
  showCornerActions?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  brandName: 'Laravel Admin',
  brandMark: 'L',
  brandLogo: null,
  homeRouteName: 'admin.home',
  redirectQueryKey: 'redirect',
  forgotUrl: null,
  ssoLinkLabel: null,
  ssoUrl: null,
  showCornerActions: true,
})

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

async function redirectToHome(): Promise<void> {
  const target = route.query[props.redirectQueryKey]
  if (typeof target === 'string' && target.length > 0 && target.startsWith('/')) {
    await router.push(target)
    return
  }
  await router.push({ name: props.homeRouteName })
}

watch(
  () => auth.isAuthenticated,
  (next) => {
    if (next) void redirectToHome()
  },
)
onMounted(() => {
  if (auth.isAuthenticated) void redirectToHome()
})
</script>

<template>
  <div class="admin-auth-page">
    <div v-if="showCornerActions" class="admin-auth-page__corner">
      <ThemeToggle />
      <LocaleSwitcher />
    </div>

    <UidCard
      :class="['admin-auth-card', auth.isChallengePending ? 'admin-auth-card--wide' : '']"
      padding="none"
    >
      <div class="admin-auth-card__hd">
        <div
          :class="['admin-auth-card__logo', auth.isChallengePending ? 'admin-auth-card__logo--accent' : '']"
        >
          <img v-if="brandLogo && !auth.isChallengePending" :src="brandLogo" :alt="brandName" />
          <span v-else-if="auth.isChallengePending" aria-hidden="true">🛡</span>
          <span v-else>{{ brandMark }}</span>
        </div>
        <div class="admin-auth-card__title">
          <template v-if="auth.isChallengePending">Двухфакторная проверка</template>
          <template v-else>{{ brandName }}</template>
        </div>
        <div class="admin-auth-card__sub">
          <template v-if="auth.isChallengePending">
            Введите 6-значный код из приложения-аутентификатора
          </template>
          <template v-else>Войдите, чтобы продолжить работу</template>
        </div>
      </div>

      <TwoFactorForm
        v-if="auth.isChallengePending"
        @success="() => undefined"
        @cancel="() => undefined"
      />
      <LoginForm
        v-else
        :forgot-url="forgotUrl"
        :sso-link-label="ssoLinkLabel"
        :sso-url="ssoUrl"
        @success="() => undefined"
      />
    </UidCard>
  </div>
</template>
