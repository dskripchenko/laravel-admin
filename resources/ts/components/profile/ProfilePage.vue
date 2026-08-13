<script setup lang="ts">
/**
 * ProfilePage — the profile screen, following the handoff
 * (screens-secondary.jsx → Profile). The layout is 200px of navigation and
 * 1fr of cards.
 *
 * Each section exposes its own card as a slot. By default the library renders
 * the shared "General" and "Security" sections, off the data already in
 * auth.user, and a host adds its own "API tokens" and "Sessions" through the
 * slots.
 *
 * A tab the host does NOT fill is not shown at all. It used to be listed
 * unconditionally, and clicking it produced a developer's note — "the library
 * does not implement this section" — in the face of the end user. A host that
 * has moved its credentials elsewhere should not have to keep a dead tab.
 */
import { computed, ref, useSlots, watch } from 'vue'
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
import { trSafe as tr, tRaw } from '../../stores/i18n'

interface Props {
  /** The page's title; "Profile" by default. */
  title?: string
  /** The subtitle; taken from the handoff by default. */
  subtitle?: string
  /** Which section is active. */
  section?: 'general' | 'security' | 'tokens' | 'sessions' | string
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Profile',
  subtitle: undefined,
  section: 'general',
})

const emit = defineEmits<{
  'update:section': [value: string]
  /** The host reacts — with a request to /me/uploadAvatar, for instance. */
  'avatar-replace': []
  /** Disabling 2FA was requested; the host shows a confirmation modal. */
  'two-factor-disable': []
  /** 2FA recovery-codes regenerate. */
  'two-factor-regenerate': []
  /** Save profile fields. */
  save: [payload: { name: string; email: string; locale: string; theme: string }]
}>()

const auth = useAuthStore()
const theme = useThemeStore()
const locale = useLocaleStore()

const slots = useSlots()

// "General" and "Security" are the library's own; the rest belong to whoever
// fills them.
const navItems = computed(() => [
  { id: 'general', label: tr('Основное'), icon: 'user' },
  { id: 'security', label: tr('Безопасность'), icon: 'shield' },
  ...(slots.tokens ? [{ id: 'tokens', label: tr('API токены'), icon: 'key' }] : []),
  ...(slots.sessions ? [{ id: 'sessions', label: tr('Сессии'), icon: 'monitor' }] : []),
])

/**
 * An address may name a section this host does not provide — an old link to a
 * tab that has since moved elsewhere. Opening "General" is a better answer than
 * a card explaining that the library does not implement the section.
 */
function known(id: string): string {
  return navItems.value.some((item) => item.id === id) ? id : 'general'
}

const localSection = ref(known(props.section))
function selectSection(id: string): void {
  localSection.value = id
  emit('update:section', id)
}

watch(
  () => props.section,
  (next) => {
    localSection.value = known(next)
  },
)

// The form state of the general tab.
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
  theme.available.map((t) => ({ value: t, label: t === 'dark' ? tr('Тёмная') : t === 'light' ? tr('Светлая') : t })),
)

// The locale and the theme apply the moment the select changes, with no need
// to press "Save" — which is how admin panels usually behave (GitHub, Vercel,
// Linear).
watch(
  () => profile.value.locale,
  (next, prev) => {
    if (next === prev || !next) return
    // A reload after the locale changes bootstraps the menu, the manifest and the i18n bag afresh.
    void locale
      .setLocale(next)
      .then(() => {
        if (typeof window !== 'undefined') window.location.reload()
      })
      .catch(() => undefined)
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

// A local flag for the 2FA status, updated by the embedded TwoFactorSetup
// wizard's events, so that the "Enabled/Disabled" badge reacts at once.
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
        <div class="admin-page__count">{{ subtitle ?? tr('Личные данные, безопасность, токены') }}</div>
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
            <h3 class="admin-profile__card-title">{{ tr('Профиль') }}</h3>
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
              {{ tr('Заменить') }}
            </UidButton>
          </div>

          <div class="admin-profile__form">
            <UidInput
              v-model="profile.name"
              :label="tr('Имя')"
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
              :label="tr('Язык')"
            />
            <UidSelect
              v-model="profile.theme"
              :options="themeOptions"
              :label="tr('Тема')"
            />
          </div>

          <footer class="admin-profile__card-ft">
            <UidButton variant="primary" @click="onSave">{{ tr('Сохранить') }}</UidButton>
          </footer>
        </UidCard>

        <!-- Security -->
        <UidCard v-else-if="localSection === 'security'" padding="md">
          <header class="admin-profile__card-hd">
            <h3 class="admin-profile__card-title">{{ tr('Двухфакторная аутентификация') }}</h3>
            <UidBadge :variant="has2FA ? 'success' : 'default'">
              {{ has2FA ? tr('Включена') : tr('Отключена') }}
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
              {{ tRaw('Раздел «:section» библиотекой не реализован —', { section: localSection }) }}
              {{ tRaw('проект подключает его через слот :section.', { section: localSection }) }}
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
  /* minmax(0,1fr) rather than 1fr: a `1fr` column is never narrower than its content, and
     широкое поле формы растягивало сетку за пределы экрана. */
  grid-template-columns: 200px minmax(0, 1fr);
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
/* On a phone the columns do not fit side by side. The sections move into a row above
   содержимым и прокручиваются горизонтально сами — раньше вторая колонка
   просто уходила за край, и видна была только левая часть формы. */
@media (max-width: 720px) {
  .admin-profile__form { grid-template-columns: 1fr; }
  .admin-profile__layout { grid-template-columns: minmax(0, 1fr); }
  .admin-profile__nav {
    flex-direction: row;
    overflow-x: auto;
    gap: var(--uid-space-xs);
    padding-bottom: var(--uid-space-xs);
    scrollbar-width: thin;
  }
  .admin-profile__nav-item {
    flex: none;
    white-space: nowrap;
  }
}
</style>
