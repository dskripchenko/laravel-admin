<script setup lang="ts">
/**
 * Email + password + remember форма. После успешного login emit'ит
 * 'success' (auth-store сам выставляет user/pendingChallenge).
 *
 * Обработка ошибок:
 *   - ValidationError → field-errors показываются под input'ами
 *   - ApiError (например, 'invalid_credentials') → general error-banner
 *   - NetworkError → general banner «Нет соединения»
 */
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { ApiError, NetworkError, ValidationError } from '../../api/errors'

const emit = defineEmits<{
  /** Успешный login (либо `authenticated` либо `two_factor_required`). */
  success: [result: 'authenticated' | 'two_factor_required']
}>()

const auth = useAuthStore()

const email = ref('')
const password = ref('')
const remember = ref(false)

const submitting = ref(false)
const generalError = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

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
    if (err instanceof ValidationError) {
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
  <form class="admin-login-form" novalidate @submit.prevent="submit">
    <h1 class="admin-login-form__title">Вход в админ-панель</h1>

    <div v-if="generalError" class="admin-login-form__alert" role="alert">
      {{ generalError }}
    </div>

    <div :class="['admin-field', { 'admin-field--invalid': fieldErrors.email }]">
      <label for="login-email" class="admin-field__label">Email</label>
      <input
        id="login-email"
        v-model="email"
        type="email"
        autocomplete="username"
        required
        :disabled="submitting"
        class="admin-input"
      />
      <p v-if="fieldErrors.email" class="admin-field__error">
        {{ fieldErrors.email[0] }}
      </p>
    </div>

    <div :class="['admin-field', { 'admin-field--invalid': fieldErrors.password }]">
      <label for="login-password" class="admin-field__label">Пароль</label>
      <input
        id="login-password"
        v-model="password"
        type="password"
        autocomplete="current-password"
        required
        :disabled="submitting"
        class="admin-input"
      />
      <p v-if="fieldErrors.password" class="admin-field__error">
        {{ fieldErrors.password[0] }}
      </p>
    </div>

    <label class="admin-login-form__remember">
      <input v-model="remember" type="checkbox" :disabled="submitting" />
      <span>Запомнить меня</span>
    </label>

    <button
      type="submit"
      class="admin-login-form__submit"
      :disabled="submitting"
    >
      {{ submitting ? 'Вход…' : 'Войти' }}
    </button>
  </form>
</template>

<style>
.admin-login-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 360px;
  margin: 0 auto;
  padding: 24px;
}
.admin-login-form__title {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 600;
  text-align: center;
}
.admin-login-form__alert {
  padding: 8px 12px;
  background: rgba(239, 68, 68, 0.1);
  color: var(--admin-danger, #ef4444);
  border: 1px solid var(--admin-danger, #ef4444);
  border-radius: 6px;
  font-size: 13px;
}
.admin-login-form__remember {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  cursor: pointer;
}
.admin-login-form__submit {
  margin-top: 8px;
  padding: 8px 12px;
  background: var(--admin-accent, #3b82f6);
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}
.admin-login-form__submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
