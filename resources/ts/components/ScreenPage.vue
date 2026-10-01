<script setup lang="ts">
/**
 * ScreenPage — the renderer of an arbitrary screen.
 *
 * It takes the slug from route.params or from its props, loads the state
 * snapshot through useScreenStore, and provideFormState gives the layout's
 * field components a reactive view of that state. The command bar is drawn as
 * UidButtons, and clicking one that carries an `attributes.method` dispatches
 * `runMethod` into the store.
 *
 * What it supports:
 *   - every action type through useActionRunner: button, modal (its form
 *     values merged into the state), async, link and dropdown
 *   - confirm, when set: a confirmation dialog before the action
 *   - destructive: variant=danger
 *   - primary: variant=primary
 *   - icon: resolved through the icon registry
 *   - alerts: a UidAlert above the body, from lastMessage or store.error;
 *     the response's `alerts` become toasts (see the screen store)
 *   - the fields' validation errors, through FormState, cleared on setField
 *   - the screen context (render/screenContext): an action carrying
 *     `attributes.opens` opens the Modal/Drawer layout with that id instead of
 *     calling a method; the layouts dispatch their own actions through it
 */
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { UidAlert, UidCard, UidSkeleton } from '@dskripchenko/ui'
import { useScreenStore } from '../stores/screen'
import { normalizeAction, normalizeActions, useActionRunner } from '../composables/useActionRunner'
import AdminActionButton from './actions/AdminActionButton.vue'
import AdminActionDialogs from './actions/AdminActionDialogs.vue'
import { toastError } from '../stores/toast'
import { provideFormState } from './render/formState'
import { provideRecord } from './infolist/recordContext'
import { provideScreenContext } from './render/screenContext'
import { provideListenerEndpoint } from './render/listenerContext'
import LayoutRenderer, { type LayoutNode } from './render/LayoutRenderer.vue'
import { trSafe as tr } from '../stores/i18n'

interface Props {
  /** The screen's slug; when null it comes from route.params.slug. */
  slug?: string | null
}

const props = withDefaults(defineProps<Props>(), { slug: null })

const route = useRoute()
const screen = useScreenStore()

const resolvedSlug = computed<string>(() => {
  if (props.slug) return props.slug
  return String(route.params.slug ?? route.meta?.slug ?? '')
})

// provideFormState MUST be called inside setup, so it is bound to store.state
// here. Two things are provided: FormState for the editable fields, and Record
// for the infolists.
const ctx = provideFormState(screen.state, screen.errors)
provideRecord(screen.state)
// The Listener layouts ask the screen's own `listener` action.
provideListenerEndpoint({ url: () => `/${resolvedSlug.value}/listener` })

const commandBar = computed(() => normalizeActions(screen.commandBar))

const runner = useActionRunner({
  execute(action, payload) {
    const method = action.attributes.method
    if (typeof method !== 'string' || method === '') {
      return Promise.reject(new Error(`Action \`${action.name}\` has no method`))
    }
    // A modal's values join the screen's state: the command method still
    // receives one payload.
    return screen.runMethod(method, payload ? { ...screen.state, ...payload } : undefined)
  },
  // A failed runMethod is already the page's alert; anything else — an action
  // with no method, say — gets a toast.
  onError: (_action, err) => {
    if (!screen.hasError) toastError(err)
  },
  refresh: async () => {
    if (resolvedSlug.value) await screen.load(resolvedSlug.value).catch(() => undefined)
  },
})

// Every action on the screen — the command bar's and the layouts' (a modal's
// footer, a wizard's submit) — goes through the screen context: an overlay
// opens locally, anything else is handed to the action runner.
const screenCtx = provideScreenContext({
  running: computed(() => screen.running),
  async runMethod(method) {
    try {
      await screen.runMethod(method)
      return true
    } catch {
      // The errors are in store.error and store.errors already; the UI follows reactively.
      return false
    }
  },
  confirm: (c) => runner.confirm(c),
  run: async (action) => {
    const normalized = normalizeAction(action)
    return normalized ? runner.run(normalized) : false
  },
})

// When store.errors changes, after a ValidationError, it is synced into the form context.
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

// An in-panel address goes through the router: a full page load would throw
// away the screen's state and, on a slow stand, look like the panel restarting.
const messageLinkIsInternal = computed(
  () => screen.lastMessageLink?.url?.startsWith('/') ?? false,
)

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
      <div v-if="commandBar.length > 0" class="admin-page__actions">
        <AdminActionButton
          v-for="action in commandBar"
          :key="action.name"
          :action="action"
          :loading="screen.running"
          :disabled="screen.running || screen.loading"
          @run="screenCtx.dispatch"
        />
      </div>
    </header>

    <UidAlert
      v-if="screen.hasError"
      variant="danger"
      class="admin-screen-page__alert"
      role="alert"
    >
      {{ screen.error?.message ?? tr('Не удалось выполнить действие') }}
    </UidAlert>

    <UidAlert
      v-else-if="screen.lastMessage"
      variant="success"
      class="admin-screen-page__alert"
      role="status"
    >
      {{ screen.lastMessage }}
      <!-- A screen that starts background work has somewhere to send the
           person — the job's own page. Without the link the message names a
           place and leaves finding it to the reader. -->
      <router-link
        v-if="messageLinkIsInternal"
        class="admin-screen-page__alert-link"
        :to="screen.lastMessageLink!.url"
      >
        {{ screen.lastMessageLink!.label }}
      </router-link>
      <a
        v-else-if="screen.lastMessageLink"
        class="admin-screen-page__alert-link"
        :href="screen.lastMessageLink.url"
        target="_blank"
        rel="noopener"
      >
        {{ screen.lastMessageLink.label }}
      </a>
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

    <AdminActionDialogs :runner="runner" />
  </section>
</template>

<style>
.admin-screen-page__description {
  margin: 4px 0 0;
  font-size: var(--uid-font-size-sm);
  color: var(--uid-text-secondary);
}
.admin-screen-page__alert-link {
  margin-left: 6px;
  font-weight: 600;
  text-decoration: underline;
  color: inherit;
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
