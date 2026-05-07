<script setup lang="ts">
/**
 * ResetPasswordPage — форма сброса пароля по token + email из ссылки в письме.
 * URL: /admin/reset-password?token=...&email=... (параметры из query).
 *
 * Backend: POST /auth/resetPassword {token, email, password, password_confirmation}.
 */
import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Check } from 'lucide-vue-next'
import { UidButton, UidCard, UidIcon, UidInput } from '@dskripchenko/ui'
import { adminToast } from '../../stores/toast'
import BrandLogo from '../shell/BrandLogo.vue'

interface Props {
  loginRouteName?: string
}
const props = withDefaults(defineProps<Props>(), {
  loginRouteName: 'admin.login',
})

const router = useRouter()
const route = useRoute()

const token = computed<string>(() => String(route.query.token ?? ''))
const email = ref<string>(String(route.query.email ?? ''))
const password = ref<string>('')
const confirm = ref<string>('')
const busy = ref<boolean>(false)
const error = ref<string>('')

async function submit(): Promise<void> {
  error.value = ''
  if (password.value.length < 8) {
    error.value = 'Пароль должен быть не менее 8 символов.'
    return
  }
  if (password.value !== confirm.value) {
    error.value = 'Пароли не совпадают.'
    return
  }
  busy.value = true
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post('/auth/resetPassword', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirm.value,
    })
    adminToast.success('Пароль обновлён. Войдите с новым паролем.')
    await router.push({ name: props.loginRouteName })
  } catch {
    error.value = 'Не удалось сбросить пароль. Возможно, ссылка устарела.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="admin-auth-page">
    <UidCard padding="none" class="admin-auth-card">
      <div class="admin-auth-card__hd">
        <div class="admin-auth-card__logo">
          <BrandLogo :size="40" />
        </div>
        <div class="admin-auth-card__title">Новый пароль</div>
        <div class="admin-auth-card__sub">Задайте новый пароль для своего аккаунта.</div>
      </div>
      <div class="admin-auth-card__body">
        <form @submit.prevent="submit">
          <UidInput v-model="email" type="email" placeholder="email" required />
          <UidInput
            v-model="password"
            type="password"
            placeholder="Новый пароль (мин. 8)"
            autocomplete="new-password"
            required
          />
          <UidInput
            v-model="confirm"
            type="password"
            placeholder="Повторите пароль"
            autocomplete="new-password"
            required
          />
          <p v-if="error" class="admin-auth-card__error">{{ error }}</p>
          <UidButton
            type="submit"
            variant="primary"
            class="admin-auth-card__submit"
            :loading="busy"
          >
            <template #prepend><UidIcon :icon="Check" :size="14" /></template>
            Сохранить пароль
          </UidButton>
        </form>
      </div>
    </UidCard>
  </div>
</template>

<style>
.admin-auth-card__error {
  color: var(--uid-color-danger, #dc2626);
  font-size: 13px;
  margin: var(--uid-space-xs) 0 0;
}
</style>
