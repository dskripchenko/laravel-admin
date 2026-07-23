<script setup lang="ts">
/**
 * ProfilePage — профильный экран по эталону handoff'а (screens-secondary.jsx
 * → Profile). Layout 200px nav / 1fr cards.
 *
 * Slot model: каждая section экспонирует свою карточку. По умолчанию
 * library рендерит общие "Основное" + "Безопасность" (на existing-данных
 * auth.user). Host подмешивает свои "API токены" и "Сессии" через slot'ы.
 */
import { computed, ref, watch } from 'vue'
import {
  UidAvatar,
  UidBadge,
  UidButton,
  UidCard,
  UidInput,
  UidSelect,
} from '@dskripchenko/ui'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore } from '../../stores/theme'
import { useLocaleStore } from '../../stores/locale'
import TwoFactorSetup from './TwoFactorSetup.vue'

interface Props {
  /** Заголовок страницы (по умолчанию «Profile»). */
  title?: string
  /** Подзаголовок (по умолчанию из handoff'а). */
  subtitle?: string
  /** Какая section активна. */
  section?: 'general' | 'security' | 'tokens' | 'sessions' | string
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Profile',
  subtitle: 'Личные данные, безопасность, токены',
  section: 'general',
})

const emit = defineEmits<{
  'update:section': [value: string]
  /** Host реагирует — например запросом /me/uploadAvatar. */
  'avatar-replace': []
  /** 2FA disable triggered — host показывает confirmation modal. */
  'two-factor-disable': []
  /** 2FA recovery-codes regenerate. */
  'two-factor-regenerate': []
  /** Save profile fields. */
  save: [payload: { name: string; email: string; locale: string; theme: string }]
}>()

const auth = useAuthStore()
const theme = useThemeStore()
const locale = useLocaleStore()

const navItems = [
  { id: 'general', label: 'Основное', icon: 'user' },
  { id: 'security', label: 'Безопасность', icon: 'shield' },
  { id: 'tokens', label: 'API токены', icon: 'key' },
  { id: 'sessions', label: 'Сессии', icon: 'monitor' },
]

const localSection = ref(props.section)
function selectSection(id: string): void {
  localSection.value = id
  emit('update:section', id)
}

// Form-state для general-tab.
const profile = ref({
  name: auth.user?.name ?? '',
  email: auth.user?.email ?? '',
  locale: locale.current,
  theme: theme.current,
})

const localeOptions = computed(() =>
  locale.available.map((l) => ({ value: l, label: l.toUpperCase() })),
)
const themeOptions = computed(() =>
  theme.available.map((t) => ({ value: t, label: t === 'dark' ? 'Тёмная' : t === 'light' ? 'Светлая' : t })),
)

// Locale / theme применяются мгновенно при изменении select'а — не требуют
// клика "Сохранить". Это стандартный admin-UX (как в GitHub/Vercel/Linear).
watch(
  () => profile.value.locale,
  (next, prev) => {
    if (next === prev || !next) return
    void locale.setLocale(next).catch(() => undefined)
  },
)

watch(
  () => profile.value.theme,
  (next, prev) => {
    if (next === prev || !next) return
    void theme.setTheme(next).catch(() => undefined)
  },
)

function onSave(): void {
  emit('save', { ...profile.value })
}

function onAvatarReplace(): void {
  emit('avatar-replace')
}

// Локальный флаг статуса 2FA — обновляется событиями встроенного визарда
// TwoFactorSetup, чтобы бейдж «Включена/Отключена» реагировал мгновенно.
const twoFAEnabled = ref<boolean>(Boolean(auth.user?.twoFactorEnabled))
const has2FA = computed(() => twoFAEnabled.value)

function onTwoFactorEnabled(): void {
  twoFAEnabled.value = true
  if (auth.user) auth.user.twoFactorEnabled = true
  emit('two-factor-regenerate')
}

function onTwoFactorDisabled(): void {
  twoFAEnabled.value = false
  if (auth.user) auth.user.twoFactorEnabled = false
  emit('two-factor-disable')
}
</script>

<template>
  <section class="admin-page admin-profile">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">{{ title }}</h1>
        <div class="admin-page__count">{{ subtitle }}</div>
      </div>
    </header>

    <div class="admin-profile__layout">
      <nav class="admin-profile__nav" aria-label="Profile sections">
        <button
          v-for="item in navItems"
          :key="item.id"
          type="button"
          :class="[
            'admin-profile__nav-item',
            { 'admin-profile__nav-item--active': localSection === item.id },
          ]"
          @click="selectSection(item.id)"
        >
          <span class="admin-profile__nav-icon" :data-icon="item.icon" />
          <span>{{ item.label }}</span>
        </button>
      </nav>

      <div class="admin-profile__content">
        <!-- General -->
        <UidCard v-if="localSection === 'general'" padding="md">
          <header class="admin-profile__card-hd">
            <h3 class="admin-profile__card-title">Профиль</h3>
          </header>

          <div class="admin-profile__hero">
            <UidAvatar
              :src="auth.user?.avatar ?? undefined"
              :name="auth.user?.name ?? '?'"
              size="lg"
            />
            <div class="admin-profile__hero-text">
              <div class="admin-profile__hero-name">{{ auth.user?.name ?? '—' }}</div>
              <div class="admin-profile__hero-meta">
                {{ auth.user?.email ?? '—' }}
              </div>
            </div>
            <div style="flex:1" />
            <UidButton variant="ghost" size="sm" @click="onAvatarReplace">
              Заменить
            </UidButton>
          </div>

          <div class="admin-profile__form">
            <UidInput
              v-model="profile.name"
              label="Имя"
              name="name"
            />
            <UidInput
              v-model="profile.email"
              label="Email"
              type="email"
              name="email"
            />
            <UidSelect
              v-model="profile.locale"
              :options="localeOptions"
              label="Язык"
            />
            <UidSelect
              v-model="profile.theme"
              :options="themeOptions"
              label="Тема"
            />
          </div>

          <footer class="admin-profile__card-ft">
            <UidButton variant="primary" @click="onSave">Сохранить</UidButton>
          </footer>
        </UidCard>

        <!-- Security -->
        <UidCard v-else-if="localSection === 'security'" padding="md">
          <header class="admin-profile__card-hd">
            <h3 class="admin-profile__card-title">Двухфакторная аутентификация</h3>
            <UidBadge :variant="has2FA ? 'success' : 'default'">
              {{ has2FA ? 'Включена' : 'Отключена' }}
            </UidBadge>
          </header>

          <!--
            Встроенный визард TwoFactorSetup сам ходит в /profile/twoFactor*
            (enable/confirm/disable/regenerate) и рендерит все стадии. Host
            может полностью заменить блок через slot `enable-2fa`.
          -->
          <slot name="enable-2fa">
            <TwoFactorSetup
              :enabled="has2FA"
              @enabled="onTwoFactorEnabled"
              @disabled="onTwoFactorDisabled"
            />
          </slot>
        </UidCard>

        <!-- Tokens / Sessions / другое — host рендерит через slot -->
        <slot v-else :name="localSection" :section="localSection">
          <UidCard padding="md">
            <p class="admin-profile__hint">
              Section «{{ localSection }}» не реализована библиотекой —
              host-проект подключает её через slot {{ localSection }}.
            </p>
          </UidCard>
        </slot>
      </div>
    </div>
  </section>
</template>

<style>
.admin-profile__layout {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: var(--uid-space-lg);
  align-items: start;
}
.admin-profile__nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.admin-profile__nav-item {
  appearance: none;
  border: 0;
  background: transparent;
  text-align: left;
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm) var(--uid-space-sm);
  border-radius: var(--uid-radius-md);
  font-size: 13px;
  color: var(--uid-text-secondary);
  cursor: pointer;
}
.admin-profile__nav-item:hover {
  background: var(--uid-surface-hover);
  color: var(--uid-text-primary);
}
.admin-profile__nav-item--active {
  background: var(--uid-surface-base);
  color: var(--uid-text-primary);
  font-weight: var(--uid-font-weight-medium);
}
.admin-profile__nav-icon {
  width: 14px;
  height: 14px;
  flex: none;
}
.admin-profile__content {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}

.admin-profile__card-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--uid-space-md);
}
.admin-profile__card-title {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
}
.admin-profile__card-ft {
  margin-top: var(--uid-space-md);
  display: flex;
  gap: var(--uid-space-sm);
}
.admin-profile__hint {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-secondary);
}

.admin-profile__hero {
  display: flex;
  align-items: center;
  gap: var(--uid-space-md);
  margin-bottom: var(--uid-space-md);
}
.admin-profile__hero-text { display: flex; flex-direction: column; gap: 4px; }
.admin-profile__hero-name {
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-profile__hero-meta {
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}

.admin-profile__form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--uid-space-md);
}
@media (max-width: 720px) {
  .admin-profile__form { grid-template-columns: 1fr; }
}
</style>
