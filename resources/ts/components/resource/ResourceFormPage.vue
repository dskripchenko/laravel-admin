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
import RowsLayout from '../layouts/RowsLayout.vue'
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
const route = useRoute()

/**
 * Defaults для prepareCreate берём из ?query — это позволяет внешним вызывающим
 * (например tree-view "Создать подгруппу") передавать pre-fill значения через
 * URL (`?parent_id=23` → state.parent_id=23). Безопасно — все значения проходят
 * через стандартную валидацию поля при save.
 *
 * Числовые query-строки коэрсим к числу: PHP-бэкенд автоматически делает int
 * из numeric-keys в assoc-массивах (Select::options и т.п.), а UidSelect
 * сравнивает option.value === modelValue строго. Без коэрсии '23' !== 23
 * и Select не подсветит pre-fill'нутую опцию.
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

// provideFormState ОБЯЗАН вызываться в setup() — связываем со store.state.
// Сам form-context форвардит setField → store.setField (с auto-clear errors).
// mode статичен для инстанса страницы: id в route → update, иначе create
// (страница пересоздаётся при смене роута). FieldRenderer по нему скрывает
// поля с visibility[mode]=false (Field::onCreate(false)/onUpdate(false)).
const ctx = provideFormState(
  form.state,
  form.errors,
  props.id !== null && props.id !== undefined ? 'update' : 'create',
)

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

/**
 * Запись не найдена (404 на read) в edit/view-режиме: показываем чистый
 * not-found вместо формы с плейсхолдерами (иначе пользователь мог бы
 * заполнить пустую форму удалённой записи и «сохранить»).
 */
const recordNotFound = computed<boolean>(
  () => !form.isCreate
    && form.hasError
    && form.error instanceof ApiError
    && form.error.status === 404,
)

const layoutNodes = computed<LayoutNode[]>(
  () => resourceMeta.value?.fields ?? [],
)

/**
 * Backend Field::default() сериализуется в node.defaultValue, но state
 * create-формы стартует пустым — required-select с дефолтом валился бы
 * на «field is required». Сидируем дефолты один раз, когда манифест готов.
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
 * если host не передал indexRouteName явно. Manifest может задать
 * `parent_slug` (см. Resource::parentSlug) — тогда back ведёт на index
 * другого ресурса (например TemplateResource → groups для tree-view).
 */
const resolvedIndexRouteName = computed<string>(() => {
  if (props.indexRouteName) return props.indexRouteName
  const parent = manifest.getResource(props.slug)?.parent_slug
  if (parent) return `admin.resource.${parent}.index`
  return `admin.resource.${props.slug}.index`
})

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
          v-if="form.isEdit && !recordNotFound"
          variant="danger"
          :disabled="form.saving || form.deleting"
          :loading="form.deleting"
          data-testid="form-delete"
          @click="onDelete"
        >
          Удалить
        </UidButton>
        <UidButton
          v-if="!recordNotFound"
          variant="primary"
          :disabled="form.saving || form.loading"
          :loading="form.saving"
          data-testid="form-save"
          @click="onSave"
        >
          {{ form.isCreate ? 'Создать' : 'Сохранить' }}
        </UidButton>
      </div>
    </header>

    <!-- Not-found: запись удалена/не существует — без формы и кнопки сохранить -->
    <UidCard v-if="recordNotFound" padding="lg" class="admin-resource-form__notfound">
      <p class="admin-resource-form__notfound-title">Запись не найдена</p>
      <p class="admin-resource-form__notfound-hint">
        Возможно, она была удалена. Вернитесь к списку.
      </p>
      <UidButton variant="primary" size="sm" @click="onCancel">← К списку</UidButton>
    </UidCard>

    <template v-else>
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

      <!-- Body: layout из manifest, обёрнут в Rows-layout чтобы поддержать
           field.span (12-grid layout). -->
      <UidCard v-else padding="md" class="admin-resource-form__body">
        <RowsLayout :items="layoutNodes" />
      </UidCard>
    </template>

    <!-- Sticky save-bar — показывается при unsaved-changes -->
    <div v-if="form.isDirty && !form.loading && !recordNotFound" class="admin-resource-form__savebar">
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
