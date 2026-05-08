<script setup lang="ts">
/**
 * ScreenPage — рендер произвольного Screen.
 *
 * Получает slug из route.params либо из props. Загружает state-snapshot
 * через useScreenStore. provideFormState даёт реактивную state-проекцию
 * для Field-компонентов в layout'е. commandBar отрисовывается как UidButton'ы;
 * клик по button с `attributes.method` диспатчит `runMethod` в store.
 *
 * Поддерживает:
 *   - confirm (если задан) — confirm() перед runMethod
 *   - destructive — variant=danger
 *   - primary — variant=primary
 *   - icon — пробрасывается в UidButton
 *   - alerts — UidAlert над body на основе lastMessage / store.error
 *   - field validation errors — через FormState (auto-clear на setField)
 */
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { UidAlert, UidButton, UidCard, UidSkeleton } from '@dskripchenko/ui'
import { useScreenStore, type ScreenAction } from '../stores/screen'
import { provideFormState } from './render/formState'
import { provideRecord } from './infolist/recordContext'
import LayoutRenderer, { type LayoutNode } from './render/LayoutRenderer.vue'

interface Props {
  /** Slug screen'а. Если null — берётся из route.params.slug. */
  slug?: string | null
}

const props = withDefaults(defineProps<Props>(), { slug: null })

const route = useRoute()
const screen = useScreenStore()

const resolvedSlug = computed<string>(() => {
  if (props.slug) return props.slug
  return String(route.params.slug ?? route.meta?.slug ?? '')
})

// provideFormState ОБЯЗАН вызываться в setup — связываем со store.state.
// Двойной provide: FormState для редактируемых полей + Record для Infolist'ов.
const ctx = provideFormState(screen.state, screen.errors)
provideRecord(screen.state)

// При обновлении store.errors (после ValidationError) — синхронизируем в form-context.
watch(
  () => screen.errors,
  (next) => {
    ctx.setErrors({ ...next })
  },
  { deep: true },
)

const layoutNodes = computed<LayoutNode[]>(() =>
  screen.layout
    .filter((n) => typeof n.type === 'string')
    .map((n) => n as unknown as LayoutNode),
)
const isReady = computed(() => !screen.loading && resolvedSlug.value !== '')

onMounted(async () => {
  if (resolvedSlug.value) {
    await screen.load(resolvedSlug.value).catch(() => undefined)
  }
})

watch(
  () => resolvedSlug.value,
  async (next) => {
    if (next) {
      await screen.load(next).catch(() => undefined)
    } else {
      screen.reset()
    }
  },
)

async function onRunAction(action: ScreenAction): Promise<void> {
  const method = action.attributes?.method as string | undefined
  if (!method) return

  if (action.confirm?.message) {
    if (!confirm(action.confirm.message)) return
  }

  try {
    await screen.runMethod(method)
  } catch {
    // Ошибки уже в store.error / store.errors — UI отреагирует реактивно.
  }
}

function actionVariant(action: ScreenAction): 'primary' | 'danger' | 'ghost' | 'secondary' {
  if (action.destructive) return 'danger'
  if (action.primary) return 'primary'
  return 'secondary'
}
</script>

<template>
  <section class="admin-page admin-screen-page">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">{{ screen.name || resolvedSlug }}</h1>
        <p v-if="screen.description" class="admin-screen-page__description">
          {{ screen.description }}
        </p>
      </div>
      <div v-if="screen.commandBar.length > 0" class="admin-page__actions">
        <UidButton
          v-for="action in screen.commandBar"
          :key="action.name"
          :variant="actionVariant(action)"
          :icon="action.icon ?? undefined"
          :loading="screen.running"
          :disabled="screen.running || screen.loading"
          @click="onRunAction(action)"
        >
          {{ action.label }}
        </UidButton>
      </div>
    </header>

    <UidAlert
      v-if="screen.hasError"
      variant="danger"
      class="admin-screen-page__alert"
      role="alert"
    >
      {{ screen.error?.message ?? 'Не удалось выполнить действие' }}
    </UidAlert>

    <UidAlert
      v-else-if="screen.lastMessage"
      variant="success"
      class="admin-screen-page__alert"
      role="status"
    >
      {{ screen.lastMessage }}
    </UidAlert>

    <div v-if="screen.loading" class="admin-screen-page__loading">
      <UidSkeleton v-for="i in 4" :key="i" height="40px" />
    </div>

    <UidCard v-else-if="isReady" padding="md" class="admin-screen-page__body">
      <LayoutRenderer
        v-for="(node, idx) in layoutNodes"
        :key="idx"
        :node="node"
      />
    </UidCard>
  </section>
</template>

<style>
.admin-screen-page__description {
  margin: 4px 0 0;
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-secondary);
}
.admin-screen-page__alert {
  margin-bottom: var(--uid-space-md);
}
.admin-screen-page__loading {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-screen-page__body {
  margin-bottom: var(--uid-space-2xl);
}
</style>
