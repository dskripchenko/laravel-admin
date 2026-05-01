<script setup lang="ts">
/**
 * Полноценная login-page admin'а. Переключает между LoginForm и TwoFactorForm
 * в зависимости от auth.isChallengePending. После успешного login
 * редиректит на ?redirect либо на admin-home.
 *
 * Brand-block — header с логотипом + названием. Берётся из props (host
 * передаёт из bootstrap.brand) либо дефолт.
 */
import { onMounted, watch } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter, useRoute } from 'vue-router'
import LoginForm from './LoginForm.vue'
import TwoFactorForm from './TwoFactorForm.vue'

interface Props {
  brandName?: string
  brandLogo?: string | null
  /** Имя роута для редиректа после login (default 'admin.home'). */
  homeRouteName?: string
  /** Query-key из которого читать redirect-target. */
  redirectQueryKey?: string
}

const props = withDefaults(defineProps<Props>(), {
  brandName: 'Admin',
  brandLogo: null,
  homeRouteName: 'admin.home',
  redirectQueryKey: 'redirect',
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

// Единая точка редиректа: реактивно следим за auth.isAuthenticated.
// Это покрывает оба сценария — LoginForm success и TwoFactorForm success;
// при этом не зависит от порядка emit'ов и unmount'а форм (когда
// pendingChallenge сбрасывается, Vue может unmount'ить TwoFactorForm
// до доставки emit'а в parent — watch на store-state такого не страдает).
watch(
  () => auth.isAuthenticated,
  (next) => {
    if (next) {
      void redirectToHome()
    }
  },
)

onMounted(() => {
  if (auth.isAuthenticated) {
    void redirectToHome()
  }
})
</script>

<template>
  <div class="admin-login-page">
    <div class="admin-login-page__brand">
      <img v-if="brandLogo" :src="brandLogo" :alt="brandName" class="admin-login-page__logo" />
      <span class="admin-login-page__name">{{ brandName }}</span>
    </div>
    <TwoFactorForm
      v-if="auth.isChallengePending"
      @success="() => undefined"
      @cancel="() => undefined"
    />
    <LoginForm v-else @success="() => undefined" />
  </div>
</template>

<style>
.admin-login-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: var(--admin-bg, #f9fafb);
  padding: 24px;
}
.admin-login-page__brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  font-size: 20px;
  font-weight: 600;
}
.admin-login-page__logo { height: 40px; width: auto; }
</style>
