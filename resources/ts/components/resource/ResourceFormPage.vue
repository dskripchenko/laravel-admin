<script setup lang="ts">
/**
 * ResourceFormPage — one page serving both create and edit for a resource.
 *
 * The structure follows docs/design_handoff_laravel_admin/screens-shell.jsx
 * (Resource Form):
 *   - header: a back breadcrumb, the title, a status badge and the actions
 *     (Preview / Delete / Save as primary)
 *   - body: the layout from manifest.fields (LayoutRenderer + provideFormState)
 *   - a sticky save bar at the bottom with the unsaved-changes hint
 *
 * The mode follows the `id` prop: id=null means create, anything else edit.
 *
 * The form state is exposed through provideFormState from
 * useResourceFormStore.state; the field renderers inside pick it up through
 * useFormState on their own.
 */
import { computed, onMounted, watch } from 'vue'
import { tRaw } from '../../stores/i18n'
import { useRoute, useRouter } from 'vue-router'
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
import { ApiError } from '../../api/errors'
import { resolveStatusLabel } from './statusLabel'
import RowsLayout from '../layouts/RowsLayout.vue'
import type { LayoutNode } from '../render/LayoutRenderer.vue'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  /** The resource slug: articles, users and so on. */
  slug: string
  /** The record id. null/undefined means create mode, a number or string means edit. */
  id?: string | number | null
  /**
   * Overrides the router route name used to go back after a save or a delete.
   * By default it is derived from the slug: `admin.resource.{slug}.index`.
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
const route = useRoute()

/**
 * The defaults for prepareCreate come from the query string, which lets
 * outside callers (the tree view's "create a subgroup", for one) pre-fill
 * values through the URL: `?parent_id=23` becomes state.parent_id=23. This is
 * safe — every value still goes through the field's usual validation on save.
 *
 * Numeric query strings are coerced to numbers: the PHP backend turns numeric
 * keys of associative arrays into ints by itself (Select::options and the
 * like), while UidSelect compares option.value === modelValue strictly.
 * Without the coercion '23' !== 23 and Select would not highlight the
 * pre-filled option.
 */
function defaultsFromQuery(): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(route.query)) {
    if (v === null || v === undefined) continue
    if (Array.isArray(v)) {
      out[k] = v
      continue
    }
    if (/^-?\d+$/.test(v)) {
      out[k] = parseInt(v, 10)
    } else {
      out[k] = v
    }
  }
  return out
}

// provideFormState MUST be called inside setup(), so we bind it to
// store.state here. The form context itself forwards setField to
// store.setField, which clears the errors along the way. The mode is static
// per page instance: an id in the route means update, anything else create
// (the page is recreated when the route changes). FieldRenderer uses it to
// hide fields with visibility[mode]=false — Field::onCreate(false) /
// Field::onUpdate(false).
const ctx = provideFormState(
  form.state,
  form.errors,
  props.id !== null && props.id !== undefined ? 'update' : 'create',
)

// Changes made through ctx.setField are synced back into the store so that
// isDirty works. Since state.value === ctx.state — the same reactive object,
// provideFormState makes no copy — the mutations are visible in the store
// directly, and ctx's setField is enough.
watch(
  () => form.errors,
  (next) => {
    // When store.errors changes, push it into the form context.
    ctx.setErrors({ ...next })
  },
)

const resourceMeta = computed(() => manifest.getResource(props.slug))

/**
 * The record was not found (a 404 on read) in edit or view mode: show a plain
 * not-found instead of a form full of placeholders — otherwise one could fill
 * in the empty form of a deleted record and "save" it.
 */
const recordNotFound = computed<boolean>(
  () => !form.isCreate
    && form.hasError
    && form.error instanceof ApiError
    && form.error.status === 404,
)

/**
 * Create mode gets its own layout when the backend sent one.
 *
 * `create_fields` is absent whenever the two layouts are identical — most
 * resources — and then the ordinary one is used, exactly as before.
 */
const layoutNodes = computed<LayoutNode[]>(() => {
  const meta = resourceMeta.value

  if (form.isCreate && meta?.create_fields) return meta.create_fields

  return meta?.fields ?? []
})

/**
 * The backend's Field::default() is serialized into node.defaultValue, but the
 * create form's state starts empty — a required select with a default would
 * fail on "field is required". So the defaults are seeded once, as soon as the
 * manifest is ready.
 */
function collectDefaults(nodes: LayoutNode[], out: Record<string, unknown>): void {
  for (const node of nodes) {
    const n = node as LayoutNode & {
      kind?: string
      name?: string
      defaultValue?: unknown
      items?: LayoutNode[]
    }
    if (Array.isArray(n.items)) collectDefaults(n.items, out)
    if (n.kind !== 'field' || typeof n.name !== 'string') continue
    if (n.defaultValue === null || n.defaultValue === undefined) continue
    out[n.name] = n.defaultValue
  }
}

function seedDefaultsFromManifest(): void {
  const defaults: Record<string, unknown> = {}
  collectDefaults(layoutNodes.value, defaults)
  if (Object.keys(defaults).length > 0) form.seedDefaults(defaults)
}

const titleLabel = computed(() => {
  if (form.isCreate) return `${tr('Создать')}: ${resourceMeta.value?.label ?? props.slug}`
  return `${resourceMeta.value?.label ?? props.slug}: ${tRaw('запись #:id', { id: props.id ?? '' })}`
})

const statusValue = computed<string | null>(() => {
  const v = form.state.status
  return typeof v === 'string' ? v : null
})

/** The status label comes from the field's own labels; see statusLabel.ts. */
const statusLabel = computed<string | null>(
  () => resolveStatusLabel(layoutNodes.value, statusValue.value),
)

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
    form.prepareCreate(props.slug, defaultsFromQuery())
    seedDefaultsFromManifest()
  }
})

watch(
  () => [props.slug, props.id] as const,
  async ([nextSlug, nextId]) => {
    if (nextId !== null && nextId !== undefined) {
      await form.load(nextSlug, nextId, 'edit').catch(() => undefined)
    } else {
      form.prepareCreate(nextSlug, defaultsFromQuery())
      seedDefaultsFromManifest()
    }
  },
)

async function onSave(): Promise<void> {
  // The mode is read BEFORE saving: `save()` switches the store to 'edit'
  // itself, so a check afterwards is always false — and the redirect written
  // right here never fired. The record was created, the address still said
  // /create, and pressing "Save" again created a second one.
  const wasCreate = form.isCreate

  try {
    const newId = await form.save()
    if (wasCreate) {
      // After a create, go to edit with the new id; the host does the routing.
      void router.push({
        name: `admin.resource.${props.slug}.edit`,
        params: { id: newId },
      }).catch(() => undefined)
    }
  } catch {
    // A ValidationError has already become form.errors through the store;
    // every other error landed in form.error and is drawn as a UidAlert below.
  }
}

/**
 * Derives the index route name from the slug
 * (`admin.resource.{slug}.index`) when the host passed no explicit
 * indexRouteName. The manifest may set `parent_slug` (see
 * Resource::parentSlug) — then "back" leads to another resource's index, as
 * TemplateResource leads to groups for the tree view.
 */
const resolvedIndexRouteName = computed<string>(() => {
  if (props.indexRouteName) return props.indexRouteName
  const parent = manifest.getResource(props.slug)?.parent_slug
  if (parent) return `admin.resource.${parent}.index`
  return `admin.resource.${props.slug}.index`
})

async function onDelete(): Promise<void> {
  if (!confirm(tr('Удалить запись?'))) return
  await form.destroy().catch(() => undefined)
  if (!form.hasError) {
    void router.push({ name: resolvedIndexRouteName.value }).catch(() => undefined)
  }
}

function onCancel(): void {
  if (form.isDirty && !confirm(tr('Несохранённые изменения будут потеряны. Продолжить?'))) {
    return
  }
  void router.push({ name: resolvedIndexRouteName.value }).catch(() => undefined)
}
</script>

<template>
  <section class="admin-page admin-resource-form">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <a class="admin-resource-form__back" @click="onCancel">{{ tr('← Назад') }}</a>
        <h1 class="admin-page__title">{{ titleLabel }}</h1>
        <UidBadge v-if="statusLabel" :variant="statusBadgeVariant">
          {{ statusLabel }}
        </UidBadge>
      </div>
      <div class="admin-page__actions">
        <UidButton
          variant="ghost"
          :disabled="form.saving || form.deleting"
          @click="onCancel"
        >
          {{ tr('Отмена') }}
        </UidButton>
        <UidButton
          v-if="form.isEdit && !recordNotFound"
          variant="danger"
          :disabled="form.saving || form.deleting"
          :loading="form.deleting"
          data-testid="form-delete"
          @click="onDelete"
        >
          {{ tr('Удалить') }}
        </UidButton>
        <UidButton
          v-if="!recordNotFound"
          variant="primary"
          :disabled="form.saving || form.loading"
          :loading="form.saving"
          data-testid="form-save"
          @click="onSave"
        >
          {{ form.isCreate ? tr('Создать') : tr('Сохранить') }}
        </UidButton>
      </div>
    </header>

    <!-- Not-found: запись удалена/не существует — без формы и кнопки сохранить -->
    <UidCard v-if="recordNotFound" padding="lg" class="admin-resource-form__notfound">
      <p class="admin-resource-form__notfound-title">{{ tr('Запись не найдена') }}</p>
      <p class="admin-resource-form__notfound-hint">
        {{ tr('Возможно, она была удалена. Вернитесь к списку.') }}
      </p>
      <UidButton variant="primary" size="sm" @click="onCancel">{{ tr('← К списку') }}</UidButton>
    </UidCard>

    <template v-else>
      <UidAlert
        v-if="form.hasError"
        variant="danger"
        class="admin-resource-form__alert"
        role="alert"
      >
        {{ form.error?.message ?? tr('Не удалось сохранить запись') }}
      </UidAlert>

      <!-- Loading state — UidSkeleton imitates form-rows -->
      <div v-if="form.loading" class="admin-resource-form__loading">
        <UidSkeleton v-for="i in 6" :key="i" height="40px" />
      </div>

      <!-- Body: layout из manifest, обёрнут в Rows-layout чтобы поддержать
           field.span (12-grid layout). -->
      <UidCard v-else padding="md" class="admin-resource-form__body">
        <RowsLayout :items="layoutNodes" />
      </UidCard>
    </template>

    <!-- Sticky save-bar — показывается при unsaved-changes -->
    <div v-if="form.isDirty && !form.loading && !recordNotFound" class="admin-resource-form__savebar">
      <span class="admin-resource-form__savebar-hint">
        {{ tr('Есть несохранённые изменения') }}
      </span>
      <UidButton variant="ghost" size="sm" @click="onCancel">{{ tr('Отмена') }}</UidButton>
      <UidButton
        variant="primary"
        size="sm"
        :loading="form.saving"
        :disabled="form.saving"
        @click="onSave"
      >
        {{ tr('Сохранить') }}
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
.admin-resource-form__notfound {
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-sm, 8px);
  align-items: center;
}
.admin-resource-form__notfound-title {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
}
.admin-resource-form__notfound-hint {
  color: var(--uid-text-secondary, #6b7280);
  margin: 0 0 var(--uid-space-sm, 8px);
}

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
