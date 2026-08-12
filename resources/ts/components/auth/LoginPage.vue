<script setup lang="ts">
/**
 * LoginPage, following the UID design handoff: a centred 400/440 px auth card
 * on a `--uid-surface-base` background, with the theme and locale toggles in
 * the corner.
 *
 * It shows either LoginForm or TwoFactorForm, depending on
 * auth.isChallengePending. The redirect to the main page hangs off a watch on
 * auth.isAuthenticated, which survives the forms being unmounted as the branch
 * changes.
 *
 * The `?redirect` query is honoured for relative paths alone — that is the
 * guard against an open redirect.
 */
import { computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { UidCard } from '@dskripchenko/ui'
import { useAuthStore } from '../../stores/auth'
import { useBrand } from '../../composables/useBrand'
import LoginForm from './LoginForm.vue'
import TwoFactorForm from './TwoFactorForm.vue'
import ThemeToggle from '../shell/widgets/ThemeToggle.vue'
import LocaleSwitcher from '../shell/widgets/LocaleSwitcher.vue'
import BrandLogo from '../shell/BrandLogo.vue'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  brandName?: string
  /**
   * A custom mark; when set it is rendered instead of BrandLogo. Useful to a
   * host with a brand of its own.
   */
  brandMark?: string | null
  brandLogo?: string | null
  homeRouteName?: string
  redirectQueryKey?: string
  /** The "Forgot your password?" URL, passed on to LoginForm. */
  forgotUrl?: string | null
  /** The SSO link — a label and a url — passed on to LoginForm. */
  ssoLinkLabel?: string | null
  ssoUrl?: string | null
  /** Whether to show the theme and locale toggles in the corner. */
  showCornerActions?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  brandName: undefined,
  brandMark: undefined,
  brandLogo: undefined,
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

// The brand: the explicit props win over bootstrap.brand (config('admin.brand')).
const injectedBrand = useBrand()
const brandName = computed<string>(() => props.brandName ?? injectedBrand.name ?? 'Laravel Admin')
const brandLogo = computed<string | null>(() => props.brandLogo ?? injectedBrand.logo ?? null)
const brandMark = computed<string | null>(() => props.brandMark ?? injectedBrand.mark ?? null)

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
          <span v-else-if="brandMark">{{ brandMark }}</span>
          <BrandLogo v-else :size="40" />
        </div>
        <div class="admin-auth-card__title">
          <template v-if="auth.isChallengePending">{{ tr('Двухфакторная проверка') }}</template>
          <template v-else>{{ brandName }}</template>
        </div>
        <div class="admin-auth-card__sub">
          <template v-if="auth.isChallengePending">
            {{ tr('Введите 6-значный код из приложения-аутентификатора') }}
          </template>
          <template v-else>{{ tr('Войдите, чтобы продолжить работу') }}</template>
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
