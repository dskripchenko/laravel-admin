<script setup lang="ts">
/**
 * ResourceFormPage — unified create/edit page для Resource'а.
 *
 * Архитектура по docs/design_handoff_laravel_admin/screens-shell.jsx
 * (Resource Form):
 *   - Header: back-breadcrumb + title + status-badge + actions
 *     (Preview / Удалить / Сохранить-primary)
 *   - Body: layout из manifest.fields (LayoutRenderer + provideFormState)
 *   - Sticky save-bar внизу с unsaved-changes hint
 *
 * Mode определяется наличием `id`-prop'а: id=null → create, иначе → edit.
 *
 * Form-state предоставляется через provideFormState из useResourceFormStore.state.
 * FieldRenderer'ы внутри форм автоматически подхватывают через useFormState.
 */
import { computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  UidAlert,
  UidBadge,
  UidButton,
  UidCard,
  UidSkeleton,
} from '@dskripchenko/ui'
import { useResourceFormStore } from '../../stores/resourceForm'
import { useManifestStore } from '../../stores/manifest'
import { provideFormState } from '../render/formState'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'

interface Props {
  /** Slug ресурса (articles/users/etc). */
  slug: string
  /** ID записи. null/undefined → create-mode; число/строка → edit. */
  id?: string | number | null
  /**
   * Override имени router-route для back-redirect после save/delete.
   * По умолчанию выводится из slug: `admin.resource.{slug}.index`.
   */
  indexRouteName?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  id: null,
  indexRouteName: null,
})

const form = useResourceFormStore()
const manifest = useManifestStore()
const router = useRouter()

// provideFormState ОБЯЗАН вызываться в setup() — связываем со store.state.
// Сам form-context форвардит setField → store.setField (с auto-clear errors).
const ctx = provideFormState(form.state, form.errors)

// Watcher: при изменениях через ctx.setField (обёртка которой) — синхронизируем
// в store, чтобы isDirty работало. Поскольку state.value === ctx.state (тот же
// reactive object — provideFormState не делает копию), мутации видны напрямую
// в store. setField'у в ctx достаточно.
watch(
  () => form.errors,
  (next) => {
    // При обновлении store.errors — выкидываем в form-context.
    ctx.setErrors({ ...next })
  },
)

const resourceMeta = computed(() => manifest.getResource(props.slug))

const layoutNodes = computed<LayoutNode[]>(
  () => resourceMeta.value?.fields ?? [],
)

const titleLabel = computed(() => {
  if (form.isCreate) return `Создать: ${resourceMeta.value?.label ?? props.slug}`
  return `${resourceMeta.value?.label ?? props.slug}: запись #${props.id}`
})

const statusValue = computed<string | null>(() => {
  const v = form.state.status
  return typeof v === 'string' ? v : null
})

const statusBadgeVariant = computed<'success' | 'warning' | 'danger' | 'default'>(() => {
  switch (statusValue.value) {
    case 'published': return 'success'
    case 'review':
    case 'draft': return 'warning'
    case 'archived': return 'danger'
    default: return 'default'
  }
})

onMounted(async () => {
  if (manifest.manifest === null) {
    await manifest.load().catch(() => undefined)
  }
  if (props.id !== null && props.id !== undefined) {
    await form.load(props.slug, props.id, 'edit').catch(() => undefined)
  } else {
    form.prepareCreate(props.slug)
  }
})

watch(
  () => [props.slug, props.id] as const,
  async ([nextSlug, nextId]) => {
    if (nextId !== null && nextId !== undefined) {
      await form.load(nextSlug, nextId, 'edit').catch(() => undefined)
    } else {
      form.prepareCreate(nextSlug)
    }
  },
)

async function onSave(): Promise<void> {
  try {
    const newId = await form.save()
    if (form.isCreate) {
      // create → редирект на edit с новым id (host route'ит).
      void router.push({
        name: `admin.resource.${props.slug}.edit`,
        params: { id: newId },
      }).catch(() => undefined)
    }
  } catch {
    // ValidationError уже превратился в form.errors через store; остальные
    // ошибки попали в form.error и отрисуются как UidAlert ниже.
  }
}

/**
 * Auto-derive index route name из slug (`admin.resource.{slug}.index`),
 * если host не передал indexRouteName явно. Это нужно чтобы back/cancel
 * вели на список ресурса, а не на главную админки.
 */
const resolvedIndexRouteName = computed<string>(
  () => props.indexRouteName ?? `admin.resource.${props.slug}.index`,
)

async function onDelete(): Promise<void> {
  if (!confirm('Удалить запись?')) return
  await form.destroy().catch(() => undefined)
  if (!form.hasError) {
    void router.push({ name: resolvedIndexRouteName.value }).catch(() => undefined)
  }
}

function onCancel(): void {
  if (form.isDirty && !confirm('Несохранённые изменения будут потеряны. Продолжить?')) {
    return
  }
  void router.push({ name: resolvedIndexRouteName.value }).catch(() => undefined)
}
</script>

<template>
  <section class="admin-page admin-resource-form">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <a class="admin-resource-form__back" @click="onCancel">← Назад</a>
        <h1 class="admin-page__title">{{ titleLabel }}</h1>
        <UidBadge v-if="statusValue" :variant="statusBadgeVariant">
          {{ statusValue }}
        </UidBadge>
      </div>
      <div class="admin-page__actions">
        <UidButton
          variant="ghost"
          :disabled="form.saving || form.deleting"
          @click="onCancel"
        >
          Отмена
        </UidButton>
        <UidButton
          v-if="form.isEdit"
          variant="danger"
          :disabled="form.saving || form.deleting"
          :loading="form.deleting"
          @click="onDelete"
        >
          Удалить
        </UidButton>
        <UidButton
          variant="primary"
          :disabled="form.saving || form.loading"
          :loading="form.saving"
          @click="onSave"
        >
          {{ form.isCreate ? 'Создать' : 'Сохранить' }}
        </UidButton>
      </div>
    </header>

    <UidAlert
      v-if="form.hasError"
      variant="danger"
      class="admin-resource-form__alert"
      role="alert"
    >
      {{ form.error?.message ?? 'Не удалось сохранить запись' }}
    </UidAlert>

    <!-- Loading state — UidSkeleton imitates form-rows -->
    <div v-if="form.loading" class="admin-resource-form__loading">
      <UidSkeleton v-for="i in 6" :key="i" height="40px" />
    </div>

    <!-- Body: layout из manifest -->
    <UidCard v-else padding="md" class="admin-resource-form__body">
      <LayoutRenderer
        v-for="(node, idx) in layoutNodes"
        :key="idx"
        :node="node"
      />
    </UidCard>

    <!-- Sticky save-bar — показывается при unsaved-changes -->
    <div v-if="form.isDirty && !form.loading" class="admin-resource-form__savebar">
      <span class="admin-resource-form__savebar-hint">
        Есть несохранённые изменения
      </span>
      <UidButton variant="ghost" size="sm" @click="onCancel">Отмена</UidButton>
      <UidButton
        variant="primary"
        size="sm"
        :loading="form.saving"
        :disabled="form.saving"
        @click="onSave"
      >
        Сохранить
      </UidButton>
    </div>
  </section>
</template>

<style>
.admin-resource-form__back {
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-secondary);
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  margin-bottom: 4px;
}
.admin-resource-form__back:hover { color: var(--uid-text-primary); }

.admin-resource-form__alert {
  margin-bottom: var(--uid-space-md);
}
.admin-resource-form__loading {
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm);
}
.admin-resource-form__body { margin-bottom: var(--uid-space-2xl); }

.admin-resource-form__savebar {
  position: sticky;
  bottom: 0;
  display: flex;
  align-items: center;
  gap: var(--uid-space-sm);
  padding: var(--uid-space-sm) var(--uid-space-md);
  background: var(--uid-surface-raised);
  border-top: 1px solid var(--uid-border-default);
  border-radius: var(--uid-radius-md);
  box-shadow: var(--uid-shadow-md);
  margin-top: var(--uid-space-md);
}
.admin-resource-form__savebar-hint {
  flex: 1;
  font-size: 13px;
  color: var(--uid-warning);
}
</style>
