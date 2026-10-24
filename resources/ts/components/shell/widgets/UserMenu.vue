<script setup lang="ts">
/**
 * The user menu: an avatar plus a dropdown, through UidMenu from
 * @dskripchenko/ui. The trigger is a small UidAvatar, showing initials or a src.
 *
 * «View as a role…» is there for those the backend allows to
 * (user.canPreviewRoles — a Super Admin by default).
 */
import { ref } from 'vue'
import { UidAvatar, UidMenu, UidMenuItem, UidMenuSeparator } from '@dskripchenko/ui'
import RolePreviewDialog from './RolePreviewDialog.vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'
import { trSafe as tr } from '../../../stores/i18n'

const auth = useAuthStore()
const router = useRouter()

async function logout(): Promise<void> {
  let redirect: string | null = null
  try {
    redirect = await auth.logout()
  } finally {
    if (redirect !== null) {
      // The identity provider's own sign-out: a full navigation, it comes
      // back to the login page by itself.
      window.location.assign(redirect)
    } else {
      await router.push({ name: 'admin.login' })
    }
  }
}

function goProfile(): void {
  void router.push({ name: 'admin.profile' })
}

const rolePreviewOpen = ref<boolean>(false)
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
    <UidMenuItem
      v-if="auth.user?.canPreviewRoles"
      data-testid="role-preview-open"
      @click="rolePreviewOpen = true"
    >
      {{ tr('Смотреть как роль…') }}
    </UidMenuItem>
    <UidMenuSeparator />
    <UidMenuItem variant="danger" @click="logout">{{ tr('Выйти') }}</UidMenuItem>
  </UidMenu>
  <RolePreviewDialog v-if="auth.user?.canPreviewRoles" v-model="rolePreviewOpen" />
</template>
