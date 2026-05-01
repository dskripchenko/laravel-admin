<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const open = ref(false)
const root = ref<HTMLElement | null>(null)

const initials = computed(() => {
  const name = auth.user?.name ?? '?'
  return name
    .split(/\s+/)
    .map((part) => part.charAt(0).toUpperCase())
    .slice(0, 2)
    .join('')
})

function toggle(): void {
  open.value = !open.value
}

function close(): void {
  open.value = false
}

async function logout(): Promise<void> {
  close()
  try {
    await auth.logout()
  } finally {
    await router.push({ name: 'admin.login' })
  }
}

function onDocumentClick(event: MouseEvent): void {
  if (root.value && !root.value.contains(event.target as Node)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})
onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <div ref="root" class="admin-user-menu">
    <button
      type="button"
      class="admin-widget admin-user-menu__trigger"
      :aria-expanded="open"
      aria-haspopup="menu"
      @click="toggle"
    >
      <span v-if="auth.user?.avatar" class="admin-user-menu__avatar">
        <img :src="auth.user.avatar" :alt="auth.user.name" />
      </span>
      <span v-else class="admin-user-menu__avatar admin-user-menu__avatar--initials">
        {{ initials }}
      </span>
      <span class="admin-user-menu__name">{{ auth.user?.name ?? 'Гость' }}</span>
    </button>
    <ul v-if="open" class="admin-user-menu__list" role="menu">
      <li role="none">
        <RouterLink to="/profile" class="admin-user-menu__item" role="menuitem" @click="close">
          Профиль
        </RouterLink>
      </li>
      <li role="none">
        <button
          type="button"
          class="admin-user-menu__item admin-user-menu__item--danger"
          role="menuitem"
          @click="logout"
        >
          Выйти
        </button>
      </li>
    </ul>
  </div>
</template>

<style>
.admin-user-menu {
  position: relative;
}
.admin-user-menu__trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 8px;
  background: transparent;
  border: 1px solid var(--admin-border, #e5e7eb);
  height: 32px;
  border-radius: 6px;
  cursor: pointer;
  color: var(--admin-text, #111827);
}
.admin-user-menu__trigger:hover { background: var(--admin-hover, #e5e7eb); }
.admin-user-menu__avatar {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.admin-user-menu__avatar img { width: 100%; height: 100%; object-fit: cover; }
.admin-user-menu__avatar--initials {
  background: var(--admin-accent, #3b82f6);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
}
.admin-user-menu__name { font-size: 13px; }
.admin-user-menu__list {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 160px;
  margin: 0;
  padding: 4px;
  background: var(--admin-popover-bg, #fff);
  border: 1px solid var(--admin-border, #e5e7eb);
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  list-style: none;
  z-index: 50;
}
.admin-user-menu__item {
  display: block;
  width: 100%;
  padding: 6px 12px;
  border: none;
  background: transparent;
  text-align: left;
  font-size: 13px;
  color: var(--admin-text, #111827);
  text-decoration: none;
  border-radius: 4px;
  cursor: pointer;
}
.admin-user-menu__item:hover { background: var(--admin-hover, #e5e7eb); }
.admin-user-menu__item--danger { color: var(--admin-danger, #ef4444); }
</style>
