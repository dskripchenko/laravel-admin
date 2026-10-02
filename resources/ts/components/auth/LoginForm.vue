<script setup lang="ts">
/**
 * LoginForm — email, password and remember, over UidInput, UidButton,
 * UidCheckbox and UidAlert. The auth card follows
 * docs/design_handoff_laravel_admin/screens-secondary.jsx (LoginScreen).
 */
import { computed, ref } from 'vue'
import { UidAlert, UidButton, UidCheckbox, UidInput } from '@dskripchenko/ui'
import { useAuthStore } from '../../stores/auth'
import { ApiError, NetworkError, ValidationError } from '../../api/errors'
import { trSafe as tr, tRaw } from '../../stores/i18n'
import type { AdminDemoAccount } from '../../types/bootstrap'

interface Props {
  /** The "Forgot your password?" URL; when set, a link appears to the right of remember. */
  forgotUrl?: string | null
  /** The SSO link's text, or null to hide it. */
  ssoLinkLabel?: string | null
  ssoUrl?: string | null
}

withDefaults(defineProps<Props>(), {
  forgotUrl: null,
  ssoLinkLabel: null,
  ssoUrl: null,
})

const emit = defineEmits<{
  success: [result: 'authenticated' | 'two_factor_required']
}>()

const auth = useAuthStore()

const email = ref('')
const password = ref('')
const remember = ref(false)

const submitting = ref(false)
const generalError = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const emailError = computed<string | undefined>(() => fieldErrors.value.email?.[0])
const passwordError = computed<string | undefined>(() => fieldErrors.value.password?.[0])

// Demo mode (admin.demo.accounts): one click signs in as a demo account,
// through the same login request as the form.
const demoAccounts = computed<AdminDemoAccount[]>(() => auth.demo?.accounts ?? [])

function signInAs(account: AdminDemoAccount): void {
  email.value = account.email
  password.value = account.password
  void submit()
}

async function submit(): Promise<void> {
  if (submitting.value) return
  submitting.value = true
  generalError.value = null
  fieldErrors.value = {}

  try {
    const result = await auth.login({
      email: email.value,
      password: password.value,
      remember: remember.value,
    })
    emit('success', result)
  } catch (err) {
    const httpStatus =
      err instanceof ApiError
        ? err.status
        : (err as { response?: { status?: number } })?.response?.status
    if (httpStatus === 429) {
      // Laravel's throttle response does not come in the API envelope;
      // without this branch one saw a raw "Request failed with status code
      // 429".
      generalError.value = tr('Слишком много попыток входа. Подождите минуту и попробуйте снова')
    } else if (err instanceof ValidationError) {
      fieldErrors.value = err.fields
      generalError.value = err.firstFieldMessage()
    } else if (err instanceof NetworkError) {
      generalError.value = tr('Нет соединения с сервером')
    } else if (err instanceof ApiError) {
      generalError.value = err.message || tr('Не удалось войти')
    } else {
      generalError.value = (err as Error).message
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="admin-auth-card__bd" novalidate @submit.prevent="submit">
    <UidAlert
      v-if="generalError"
      variant="danger"
      class="admin-auth-card__alert"
      role="alert"
    >
      {{ generalError }}
    </UidAlert>

    <UidInput
      v-model="email"
      type="email"
      label="Email"
      placeholder="you@company.com"
      autocomplete="username"
      :required="true"
      :disabled="submitting"
      :error="emailError"
      name="email"
      data-testid="login-email"
    />

    <UidInput
      v-model="password"
      type="password"
      :label="tr('Пароль')"
      autocomplete="current-password"
      :required="true"
      :disabled="submitting"
      :error="passwordError"
      name="password"
      data-testid="login-password"
    />

    <div class="admin-auth-card__row">
      <UidCheckbox v-model="remember" :disabled="submitting" :label="tr('Запомнить меня')" />
      <a v-if="forgotUrl" :href="forgotUrl" class="admin-auth-card__link">
        {{ tr('Забыли пароль?') }}
      </a>
    </div>

    <UidButton
      type="submit"
      variant="primary"
      size="lg"
      :loading="submitting"
      :disabled="submitting"
      data-testid="login-submit"
    >
      {{ submitting ? tr('Вход…') : tr('Войти') }}
    </UidButton>

    <div v-if="demoAccounts.length > 0" class="admin-auth-demo" data-testid="login-demo">
      <div class="admin-auth-demo__title">{{ tr('Демо-доступ') }}</div>
      <UidButton
        v-for="account in demoAccounts"
        :key="account.email"
        type="button"
        variant="secondary"
        class="admin-auth-demo__account"
        :disabled="submitting"
        :data-testid="`login-demo-${account.email}`"
        @click="signInAs(account)"
      >
        <span class="admin-auth-demo__label">{{ tRaw('Войти как :name', { name: account.label }) }}</span>
        <span v-if="account.description" class="admin-auth-demo__description">{{ account.description }}</span>
      </UidButton>
    </div>

    <div
      v-if="ssoLinkLabel && ssoUrl"
      style="text-align: center; font-size: var(--uid-font-size-xs); color: var(--uid-text-secondary); padding-top: 4px;"
    >
      {{ tr('или войдите через') }}
      <a :href="ssoUrl" class="admin-auth-card__link">{{ ssoLinkLabel }}</a>
    </div>
  </form>
</template>

<style>
.admin-auth-demo {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-xs, 6px);
  padding-top: var(--uid-space-sm, 8px);
  border-top: 1px solid var(--uid-border-subtle);
}
.admin-auth-demo__title {
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.admin-auth-demo__account.uid-button {
  height: auto;
  min-height: var(--uid-size-lg, 40px);
  padding-top: var(--uid-space-xs, 6px);
  padding-bottom: var(--uid-space-xs, 6px);
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  white-space: normal;
  text-align: left;
}
.admin-auth-demo__label { font-weight: var(--uid-font-weight-medium); }
.admin-auth-demo__description {
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
  font-weight: normal;
  line-height: 1.3;
}
</style>
