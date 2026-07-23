<script setup lang="ts">
/**
 * LoginForm — email/password/remember поверх UidInput/UidButton/UidCheckbox/
 * UidAlert. Архитектура auth-card по docs/design_handoff_laravel_admin/
 * screens-secondary.jsx (LoginScreen).
 */
import { computed, ref } from 'vue'
import { UidAlert, UidButton, UidCheckbox, UidInput } from '@dskripchenko/ui'
import { useAuthStore } from '../../stores/auth'
import { ApiError, NetworkError, ValidationError } from '../../api/errors'

interface Props {
  /** URL «Забыли пароль?» — если задан, показывается link справа от remember. */
  forgotUrl?: string | null
  /** Текст SSO-ссылки или null чтобы скрыть. */
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
      // Throttle-ответ Laravel не в API-envelope — без этой ветки пользователь
      // видел сырое «Request failed with status code 429».
      generalError.value = 'Слишком много попыток входа. Подождите минуту и попробуйте снова'
    } else if (err instanceof ValidationError) {
      fieldErrors.value = err.fields
      generalError.value = err.firstFieldMessage()
    } else if (err instanceof NetworkError) {
      generalError.value = 'Нет соединения с сервером'
    } else if (err instanceof ApiError) {
      generalError.value = err.message || 'Не удалось войти'
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
    />

    <UidInput
      v-model="password"
      type="password"
      label="Пароль"
      autocomplete="current-password"
      :required="true"
      :disabled="submitting"
      :error="passwordError"
      name="password"
    />

    <div class="admin-auth-card__row">
      <UidCheckbox v-model="remember" :disabled="submitting" label="Запомнить меня" />
      <a v-if="forgotUrl" :href="forgotUrl" class="admin-auth-card__link">
        Забыли пароль?
      </a>
    </div>

    <UidButton
      type="submit"
      variant="primary"
      size="lg"
      :loading="submitting"
      :disabled="submitting"
    >
      {{ submitting ? 'Вход…' : 'Войти' }}
    </UidButton>

    <div
      v-if="ssoLinkLabel && ssoUrl"
      style="text-align: center; font-size: var(--uid-font-size-xs); color: var(--uid-text-secondary); padding-top: 4px;"
    >
      или войдите через
      <a :href="ssoUrl" class="admin-auth-card__link">{{ ssoLinkLabel }}</a>
    </div>
  </form>
</template>
