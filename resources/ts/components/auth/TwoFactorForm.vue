<script setup lang="ts">
/**
 * 2FA-форма: ввод TOTP-кода либо переключение на recovery-код.
 * Cancel — отзывает pendingChallenge и возвращает на login-форму.
 */
import { computed, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { ApiError, NetworkError, ValidationError } from '../../api/errors'

const emit = defineEmits<{
  /** Login завершён — host перенаправит на main. */
  success: []
  /** User отменил 2FA — host рендерит LoginForm. */
  cancel: []
}>()

const auth = useAuthStore()

const mode = ref<'totp' | 'recovery'>('totp')
const code = ref('')
const recoveryCode = ref('')

const submitting = ref(false)
const generalError = ref<string | null>(null)

const remaining = ref<number | null>(null)

const isValid = computed(() => {
  if (mode.value === 'totp') return code.value.trim().length > 0
  return recoveryCode.value.trim().length > 0
})

async function submit(): Promise<void> {
  if (submitting.value || !isValid.value) return
  submitting.value = true
  generalError.value = null

  try {
    if (mode.value === 'totp') {
      await auth.twoFactorChallenge(code.value.trim())
    } else {
      const res = await auth.twoFactorRecovery(recoveryCode.value.trim())
      remaining.value = res.remaining
    }
    emit('success')
  } catch (err) {
    if (err instanceof ValidationError) {
      generalError.value = err.firstFieldMessage() ?? 'Неверный код'
    } else if (err instanceof NetworkError) {
      generalError.value = 'Нет соединения с сервером'
    } else if (err instanceof ApiError) {
      generalError.value = err.message || 'Не удалось проверить код'
    } else {
      generalError.value = (err as Error).message
    }
  } finally {
    submitting.value = false
  }
}

function cancel(): void {
  auth.cancelChallenge()
  emit('cancel')
}

function switchMode(): void {
  mode.value = mode.value === 'totp' ? 'recovery' : 'totp'
  generalError.value = null
}
</script>

<template>
  <form class="admin-2fa-form" novalidate @submit.prevent="submit">
    <h1 class="admin-2fa-form__title">Двухфакторная аутентификация</h1>
    <p class="admin-2fa-form__subtitle">
      <template v-if="mode === 'totp'">Введите 6-значный код из приложения</template>
      <template v-else>Введите recovery-код</template>
    </p>

    <div v-if="generalError" class="admin-2fa-form__alert" role="alert">
      {{ generalError }}
    </div>
    <div v-if="remaining !== null" class="admin-2fa-form__info" role="status">
      Использован recovery-код. Осталось: {{ remaining }}
    </div>

    <div v-if="mode === 'totp'" class="admin-field">
      <label for="admin-2fa-code" class="admin-field__label">Код</label>
      <input
        id="admin-2fa-code"
        v-model="code"
        type="text"
        inputmode="numeric"
        autocomplete="one-time-code"
        autofocus
        :disabled="submitting"
        class="admin-input"
      />
    </div>
    <div v-else class="admin-field">
      <label for="admin-2fa-recovery" class="admin-field__label">Recovery-код</label>
      <input
        id="admin-2fa-recovery"
        v-model="recoveryCode"
        type="text"
        autocomplete="one-time-code"
        autofocus
        :disabled="submitting"
        class="admin-input"
      />
    </div>

    <button
      type="submit"
      class="admin-2fa-form__submit"
      :disabled="submitting || !isValid"
    >
      {{ submitting ? 'Проверка…' : 'Подтвердить' }}
    </button>

    <div class="admin-2fa-form__links">
      <button type="button" class="admin-2fa-form__link" @click="switchMode">
        <template v-if="mode === 'totp'">Использовать recovery-код</template>
        <template v-else>Вернуться к коду из приложения</template>
      </button>
      <button type="button" class="admin-2fa-form__link" @click="cancel">
        Отмена
      </button>
    </div>
  </form>
</template>

<style>
.admin-2fa-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 360px;
  margin: 0 auto;
  padding: 24px;
}
.admin-2fa-form__title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  text-align: center;
}
.admin-2fa-form__subtitle {
  margin: 0 0 8px;
  font-size: 13px;
  color: var(--admin-muted, #6b7280);
  text-align: center;
}
.admin-2fa-form__alert {
  padding: 8px 12px;
  background: rgba(239, 68, 68, 0.1);
  color: var(--admin-danger, #ef4444);
  border: 1px solid var(--admin-danger, #ef4444);
  border-radius: 6px;
  font-size: 13px;
}
.admin-2fa-form__info {
  padding: 8px 12px;
  background: rgba(59, 130, 246, 0.1);
  color: var(--admin-accent, #3b82f6);
  border: 1px solid var(--admin-accent, #3b82f6);
  border-radius: 6px;
  font-size: 13px;
}
.admin-2fa-form__submit {
  margin-top: 4px;
  padding: 8px 12px;
  background: var(--admin-accent, #3b82f6);
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}
.admin-2fa-form__submit:disabled { opacity: 0.6; cursor: not-allowed; }
.admin-2fa-form__links {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
}
.admin-2fa-form__link {
  background: none;
  border: none;
  padding: 0;
  font-size: 12px;
  color: var(--admin-accent, #3b82f6);
  cursor: pointer;
}
.admin-2fa-form__link:hover { text-decoration: underline; }
</style>
