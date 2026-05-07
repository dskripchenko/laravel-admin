<script setup lang="ts">
/**
 * ForgotPasswordPage — простой email-input + submit.
 * Backend: POST /auth/forgotPassword body {email} → отправляет письмо.
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Mail } from 'lucide-vue-next'
import { UidButton, UidCard, UidIcon, UidInput } from '@dskripchenko/ui'
import { adminToast } from '../../stores/toast'
import BrandLogo from '../shell/BrandLogo.vue'

interface Props {
  brandName?: string
  loginRouteName?: string
}
withDefaults(defineProps<Props>(), {
  brandName: 'Laravel Admin',
  loginRouteName: 'admin.login',
})

const router = useRouter()
const email = ref<string>('')
const submitted = ref<boolean>(false)
const busy = ref<boolean>(false)

async function submit(): Promise<void> {
  if (email.value.trim() === '') {
    adminToast.error('Введите email.')
    return
  }
  busy.value = true
  try {
    const { getAdminClient } = await import('../../stores/registry')
    const client = getAdminClient()
    await client.post('/auth/forgotPassword', { email: email.value.trim() })
    submitted.value = true
  } catch {
    // Backend для безопасности не палит существование email — отображаем
    // success в любом случае.
    submitted.value = true
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
        <div class="admin-auth-card__title">Восстановление пароля</div>
        <div class="admin-auth-card__sub">
          {{ submitted
            ? 'Если такой email зарегистрирован — мы отправили письмо со ссылкой на сброс.'
            : 'Введите email — отправим ссылку для сброса пароля.' }}
        </div>
      </div>
      <div class="admin-auth-card__body">
        <form v-if="!submitted" @submit.prevent="submit">
          <UidInput
            v-model="email"
            type="email"
            placeholder="you@example.com"
            autocomplete="email"
            required
          />
          <UidButton
            type="submit"
            variant="primary"
            class="admin-auth-card__submit"
            :loading="busy"
          >
            <template #prepend><UidIcon :icon="Mail" :size="14" /></template>
            Отправить ссылку
          </UidButton>
        </form>
        <UidButton
          variant="ghost"
          class="admin-auth-card__back"
          @click="router.push({ name: loginRouteName })"
        >
          ← Назад ко входу
        </UidButton>
      </div>
    </UidCard>
  </div>
</template>

<style>
.admin-auth-card__submit { width: 100%; margin-top: var(--uid-space-sm); }
.admin-auth-card__back { width: 100%; margin-top: var(--uid-space-sm); }
</style>
