<script setup lang="ts">
/**
 * ResourceViewPage — read-only зеркало form-страницы.
 *
 * Архитектура по docs/design_handoff_laravel_admin (ResourceView):
 *   - Header: title + actions (Edit / Удалить)
 *   - Body: 2-col grid (1fr Infolist + 320px side card с метриками)
 *   - InfolistRenderer бежит по manifest.fields (тот же layout, но
 *     leaf-узлы резолвятся через infolist-registry, не field-registry)
 */
import { computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  UidAlert,
  UidButton,
  UidCard,
  UidSkeleton,
} from '@dskripchenko/ui'
import { useResourceFormStore } from '../../stores/resourceForm'
import { useManifestStore } from '../../stores/manifest'
import InfolistRenderer from '../infolist/InfolistRenderer.vue'
import type { InfolistNode } from '../infolist/InfolistRenderer.vue'
import { provideRecord } from '../infolist/recordContext'

interface Props {
  slug: string
  id: string | number
  /** Имя router-route для перехода в edit. */
  editRouteName?: string
  /** Имя router-route для возврата на index. */
  indexRouteName?: string
}

const props = withDefaults(defineProps<Props>(), {
  editRouteName: undefined,
  indexRouteName: 'admin.home',
})

const form = useResourceFormStore()
const manifest = useManifestStore()
const router = useRouter()

// provideRecord даёт infolist-entry'ям читать record[name] через useRecord.
provideRecord(form.state)

const resourceMeta = computed(() => manifest.getResource(props.slug))
const layoutNodes = computed<InfolistNode[]>(
  () => (resourceMeta.value?.fields ?? []) as unknown as InfolistNode[],
)
const titleLabel = computed(
  () => `${resourceMeta.value?.label ?? props.slug}: запись #${props.id}`,
)

onMounted(async () => {
  if (manifest.manifest === null) {
    await manifest.load().catch(() => undefined)
  }
  await form.load(props.slug, props.id, 'view').catch(() => undefined)
})

watch(
  () => [props.slug, props.id] as const,
  async ([s, id]) => {
    await form.load(s, id, 'view').catch(() => undefined)
  },
)

function onEdit(): void {
  if (!props.editRouteName) {
    router
      .push(`/r/${props.slug}/${props.id}/edit`)
      .catch(() => undefined)
    return
  }
  router
    .push({ name: props.editRouteName, params: { id: props.id } })
    .catch(() => undefined)
}

async function onDelete(): Promise<void> {
  if (!confirm('Удалить запись?')) return
  await form.destroy().catch(() => undefined)
  if (!form.hasError) {
    router.push({ name: props.indexRouteName }).catch(() => undefined)
  }
}
</script>

<template>
  <section class="admin-page admin-resource-view">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">{{ titleLabel }}</h1>
      </div>
      <div class="admin-page__actions">
        <UidButton variant="ghost" @click="onEdit">Редактировать</UidButton>
        <UidButton
          variant="danger"
          :loading="form.deleting"
          :disabled="form.deleting"
          @click="onDelete"
        >
          Удалить
        </UidButton>
      </div>
    </header>

    <UidAlert
      v-if="form.hasError"
      variant="danger"
      class="admin-resource-view__alert"
      role="alert"
    >
      {{ form.error?.message ?? 'Не удалось загрузить запись' }}
    </UidAlert>

    <div v-if="form.loading" class="admin-resource-view__loading">
      <UidSkeleton v-for="i in 8" :key="i" height="24px" />
    </div>

    <UidCard v-else padding="md" class="admin-resource-view__body">
      <InfolistRenderer
        v-for="(node, idx) in layoutNodes"
        :key="idx"
        :node="node"
      />
    </UidCard>
  </section>
</template>

<style>
.admin-resource-view__alert {
  margin-bottom: var(--uid-space-md);
}
.admin-resource-view__loading {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
</style>
