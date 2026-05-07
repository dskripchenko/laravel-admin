<script setup lang="ts">
/**
 * Default home-страница admin'а — показывает первый зарегистрированный
 * Dashboard (host регистрирует через `Admin::screens([DashboardScreen])`),
 * иначе fallback на welcome-карточку со списком Resource'ов.
 *
 * Host может полностью переопределить через
 * `createAdminApp({ pages: { home: MyHome } })`.
 */
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { UidCard } from '@dskripchenko/ui'
import { useManifestStore } from '../stores/manifest'
import { useAuthStore } from '../stores/auth'
import { useDashboardStore } from '../stores/dashboard'
import DashboardPage from './dashboard/DashboardPage.vue'

interface DashboardManifest {
  slug: string
  label?: string
  description?: string | null
  widgets: unknown[]
}

const manifest = useManifestStore()
const auth = useAuthStore()
const router = useRouter()

const userName = computed(() => auth.user?.name ?? '')
const resources = computed(() => manifest.manifest?.resources ?? [])

const dashboards = computed<DashboardManifest[]>(
  () => (manifest.manifest?.dashboards ?? []) as DashboardManifest[],
)
const primaryDashboardSlug = computed<string | null>(
  () => dashboards.value[0]?.slug ?? null,
)

// Загружаем persisted layout из dashboard store на mount/смену slug.
const dashboardStore = useDashboardStore()
watch(
  primaryDashboardSlug,
  (slug) => {
    if (slug) void dashboardStore.openDashboard(slug)
  },
  { immediate: true },
)
</script>

<template>
  <DashboardPage
    v-if="primaryDashboardSlug"
    :slug="primaryDashboardSlug"
  />

  <div v-else class="admin-home">
    <UidCard padding="lg" class="admin-home__hero">
      <h1 class="admin-home__title">Добро пожаловать{{ userName ? ', ' + userName : '' }}</h1>
      <p class="admin-home__lead">
        Админ-панель готова. Зарегистрируйте DashboardScreen чтобы увидеть widget-grid здесь,
        либо переходите к ресурсам ниже.
      </p>
    </UidCard>

    <section v-if="resources.length" class="admin-home__resources">
      <h2 class="admin-home__heading">Ресурсы</h2>
      <ul class="admin-home__grid">
        <li v-for="r in resources" :key="r.slug">
          <button
            type="button"
            class="admin-home__card"
            @click="router.push({ name: `admin.resource.${r.slug}.index` })"
          >
            <span class="admin-home__card-label">{{ r.label }}</span>
            <span class="admin-home__card-slug">{{ r.slug }}</span>
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<style>
.admin-home {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-lg);
  padding: var(--uid-space-md);
}
.admin-home__title {
  font-size: var(--uid-font-size-2xl);
  font-weight: var(--uid-font-weight-bold);
  margin: 0 0 var(--uid-space-sm);
}
.admin-home__lead {
  color: var(--uid-text-secondary);
  margin: 0;
}
.admin-home__heading {
  font-size: var(--uid-font-size-lg);
  font-weight: var(--uid-font-weight-semibold);
  margin: 0 0 var(--uid-space-sm);
}
.admin-home__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--uid-space-sm);
  list-style: none;
  padding: 0;
  margin: 0;
}
.admin-home__card {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-2xs);
  padding: var(--uid-space-md);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  background: var(--uid-surface-raised);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s ease, transform 0.15s ease;
}
.admin-home__card:hover {
  border-color: var(--uid-accent);
  transform: translateY(-1px);
}
.admin-home__card-label {
  font-weight: var(--uid-font-weight-medium);
}
.admin-home__card-slug {
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
}
</style>
