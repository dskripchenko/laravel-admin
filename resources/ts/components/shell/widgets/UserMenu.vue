<script setup lang="ts">
/**
 * UserMenu с avatar + dropdown через UidMenu из @dskripchenko/ui.
 * В trigger'е — UidAvatar (с инициалами либо src, размер sm).
 */
import { UidAvatar, UidMenu, UidMenuItem, UidMenuSeparator } from '@dskripchenko/ui'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'
import { trSafe as tr } from '../../../stores/i18n'

const auth = useAuthStore()
const router = useRouter()

async function logout(): Promise<void> {
  try {
    await auth.logout()
  } finally {
    await router.push({ name: 'admin.login' })
  }
}

function goProfile(): void {
  void router.push({ name: 'admin.profile' })
}
</script>

<template>
  <UidMenu>
    <template #trigger>
      <button
        type="button"
        class="admin-topbar__icon-btn admin-user-menu__trigger"
        :aria-label="tr('Меню пользователя')"
        aria-haspopup="menu"
        style="width: auto; padding: 0 4px; gap: 8px;"
      >
        <UidAvatar
          :src="auth.user?.avatar ?? undefined"
          :name="auth.user?.name ?? '?'"
          :alt="auth.user?.name ?? 'User'"
          size="sm"
        />
      </button>
    </template>

    <UidMenuItem @click="goProfile">{{ tr('Профиль') }}</UidMenuItem>
    <UidMenuSeparator />
    <UidMenuItem variant="danger" @click="logout">{{ tr('Выйти') }}</UidMenuItem>
  </UidMenu>
</template>
