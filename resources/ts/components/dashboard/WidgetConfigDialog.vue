<script setup lang="ts">
/**
 * WidgetConfigDialog — one dialog serving two purposes:
 *
 *   mode='add'       — pick a type from the registry and set it up
 *   mode='configure' — edit an existing widget, whose type is fixed
 *
 * The fields:
 *   - the title, always
 *   - the size, 1..12, always
 *   - the type's own configuration, from its registered description
 *     (describeWidget() / registerWidget()'s third argument, see
 *     widgetConfig.ts): field descriptors drawn by WidgetConfigForm, or the
 *     description's own form component. A type without a description has
 *     nothing to configure besides the title and the size.
 *
 * @add  emits a finished WidgetLayoutItem, which the parent puts into the store.
 * @save emits a patch for an existing widget.
 */
import { computed, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import {
  UidButton,
  UidIcon,
  UidInput,
} from '@dskripchenko/ui'
import { getWidgetDescription, listAddableWidgets } from './registry'
import WidgetConfigForm from './WidgetConfigForm.vue'
import {
  buildWidgetConfig,
  canViewResource,
  initialValues,
  missingRequired,
  widgetText,
  type WidgetConfigContext,
  type WidgetConfigValues,
  type WidgetTypeDescription,
} from './widgetConfig'
import type { WidgetLayoutItem } from '../../stores/dashboard'
import { useManifestStore } from '../../stores/manifest'
import { useAuthStore } from '../../stores/auth'
import { trSafe as tr } from '../../stores/i18n'

interface Props {
  open: boolean
  /** 'add' picks a type; 'configure' edits an existing widget. */
  mode?: 'add' | 'configure'
  /** The initial state of the configure mode: slug, type, size and config. */
  item?: WidgetLayoutItem | null
  /** The widget's title when the dialog opened; in configure mode it comes from the manifest. */
  initialTitle?: string
  /** The dashboard's hidden widgets, offered for restoring. */
  restorable?: Array<{ slug: string; title: string }>
}
const props = withDefaults(defineProps<Props>(), {
  mode: 'add',
  item: null,
  initialTitle: '',
  restorable: () => [],
})

const emit = defineEmits<{
  close: []
  add: [item: WidgetLayoutItem]
  save: [patch: Partial<WidgetLayoutItem>]
  restore: [slug: string]
}>()

function onRestore(slug: string): void {
  emit('restore', slug)
  emit('close')
}

/**
 * What the forms may ask of the panel. Without an active Pinia — a unit test
 * mounting the dialog alone — there are no resources and no permissions.
 */
function makeContext(): WidgetConfigContext {
  try {
    const manifest = useManifestStore()
    const auth = useAuthStore()
    const hasPermission = (key: string): boolean => auth.hasPermission(key)
    return {
      get resources() {
        return manifest.resources.filter((r) => canViewResource(r, hasPermission))
      },
      hasPermission,
    }
  } catch {
    return { resources: [], hasPermission: () => false }
  }
}
const context = makeContext()

const types = computed<string[]>(() => listAddableWidgets())
const selectedType = ref<string>('')
const title = ref<string>('')
const size = ref<string>('6')
/** The type's config form values. */
const values = ref<WidgetConfigValues>({})

const description = computed<WidgetTypeDescription | null>(() =>
  selectedType.value ? getWidgetDescription(selectedType.value) : null,
)

function typeLabel(type: string): string {
  const d = getWidgetDescription(type)
  return d?.label ? widgetText(d.label) : type
}

/** Picks a type in the add mode: its default size and a fresh form. */
function selectType(type: string): void {
  selectedType.value = type
  const d = getWidgetDescription(type)
  size.value = String(d?.defaultSize ?? 6)
  values.value = initialValues(d, null)
}

watch(
  () => props.open,
  (open) => {
    if (!open) return
    if (props.mode === 'configure' && props.item) {
      const cfg = (props.item.config ?? {}) as Record<string, unknown>
      selectedType.value = props.item.type ?? ''
      title.value = (cfg.title as string | undefined) ?? props.initialTitle
      size.value = String(props.item.size ?? 6)
      // Pre-fill the type's own fields from the saved config.
      values.value = initialValues(description.value, cfg)
    } else {
      // In the add mode the state starts clean.
      selectedType.value = ''
      title.value = ''
      size.value = '6'
      values.value = {}
    }
  },
)

const missing = computed<string[]>(() => missingRequired(description.value, values.value))

const canSubmit = computed<boolean>(
  () => selectedType.value !== '' && title.value.trim() !== '' && missing.value.length === 0,
)

function onSubmit(): void {
  if (!canSubmit.value) return
  const sizeNum = Math.max(1, Math.min(12, Number(size.value) || 6))
  const config = buildWidgetConfig(description.value, values.value, {
    type: selectedType.value,
    title: title.value,
  })

  if (props.mode === 'configure' && props.item) {
    emit('save', { size: sizeNum, type: selectedType.value, config })
  } else {
    const slug = `custom.${selectedType.value}.${Date.now()}`
    emit('add', {
      slug,
      type: selectedType.value,
      size: sizeNum,
      config,
    })
  }
  emit('close')
}

function close(): void {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="admin-dialog">
      <div
        v-if="open"
        class="admin-dialog-root"
        role="dialog"
        aria-modal="true"
        aria-labelledby="widget-config-title"
      >
        <div class="admin-dialog-root__backdrop" @click="close" />
        <div class="admin-dialog">
          <header class="admin-dialog__hd">
            <h2 id="widget-config-title" class="admin-dialog__title">
              {{ mode === 'add' ? tr('Добавить виджет') : tr('Настроить виджет') }}
            </h2>
            <button
              type="button"
              class="admin-dialog__close"
              :aria-label="tr('Закрыть')"
              @click="close"
            >
              <UidIcon :icon="X" :size="16" />
            </button>
          </header>

          <div class="admin-dialog__body">
            <!-- Восстановление скрытых виджетов (BL-18) — только в add-mode. -->
            <div v-if="mode === 'add'" class="admin-dialog__field">
              <label class="admin-dialog__label">{{ tr('Скрытые виджеты') }}</label>
              <p v-if="restorable.length === 0" class="admin-dialog__empty">
                {{ tr('Нечего добавлять — все виджеты дашборда уже показаны.') }}
              </p>
              <div v-else class="admin-dialog__restore-list">
                <button
                  v-for="r in restorable"
                  :key="r.slug"
                  type="button"
                  class="admin-dialog__type-card"
                  :data-testid="`restore-widget-${r.slug}`"
                  @click="onRestore(r.slug)"
                >
                  <span class="admin-dialog__type-name">{{ r.title }}</span>
                  <span class="admin-dialog__type-help">{{ tr('Вернуть на дашборд') }}</span>
                </button>
              </div>
            </div>

            <!-- Type selector — только в add-mode. -->
            <div v-if="mode === 'add'" class="admin-dialog__field">
              <label class="admin-dialog__label">
                {{ tr('Тип виджета') }} <span class="admin-dialog__required">*</span>
              </label>
              <p v-if="types.length === 0" class="admin-dialog__empty">
                {{ tr('Нет виджетов для добавления — все доступные типы уже на дашборде') }}
                {{ tr('или ни один не зарегистрирован.') }}
              </p>
              <div v-else class="admin-dialog__type-grid">
                <button
                  v-for="t in types"
                  :key="t"
                  type="button"
                  :class="[
                    'admin-dialog__type-card',
                    { 'admin-dialog__type-card--active': selectedType === t },
                  ]"
                  :data-testid="`widget-type-${t}`"
                  @click="selectType(t)"
                >
                  <span class="admin-dialog__type-name">
                    <UidIcon
                      v-if="getWidgetDescription(t)?.icon"
                      :icon="getWidgetDescription(t)!.icon!"
                      :size="14"
                      class="admin-dialog__type-icon"
                    />
                    {{ typeLabel(t) }}
                  </span>
                  <span class="admin-dialog__type-help">{{ t }}</span>
                </button>
              </div>
            </div>
            <div v-else class="admin-dialog__field">
              <label class="admin-dialog__label">{{ tr('Тип') }}</label>
              <div class="admin-dialog__readonly">{{ selectedType || 'unknown' }}</div>
            </div>

            <div class="admin-dialog__field">
              <label class="admin-dialog__label">
                {{ tr('Заголовок') }} <span class="admin-dialog__required">*</span>
              </label>
              <UidInput v-model="title" :placeholder="tr('Название виджета')" />
            </div>

            <div class="admin-dialog__field">
              <label class="admin-dialog__label">{{ tr('Ширина (1..12)') }}</label>
              <UidInput v-model="size" type="number" />
            </div>

            <!-- The type's own config: its form component, else its field descriptors. -->
            <component
              :is="description.form"
              v-if="description?.form"
              v-model="values"
              :type="selectedType"
              :context="context"
            />
            <WidgetConfigForm v-else-if="description?.fields?.length" v-model="values" :description="description" :context="context" />
          </div>

          <footer class="admin-dialog__ft">
            <span v-if="!canSubmit" class="admin-dialog__hint">
              {{ tr('Заполните') }} <span class="admin-dialog__required">*</span>{{ tr('-поля') }}
            </span>
            <UidButton variant="ghost" @click="close">{{ tr('Отмена') }}</UidButton>
            <UidButton
              variant="primary"
              :disabled="!canSubmit"
              :title="!canSubmit ? tr('Заполните обязательные поля') : undefined"
              @click="onSubmit"
            >
              {{ mode === 'add' ? tr('Добавить') : tr('Сохранить') }}
            </UidButton>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style>
/*
 * The dialog's frame. These rules used to live in AddWidgetDialog, which
 * nothing imported, so the bundle shipped the dialog without them.
 */
.admin-dialog-root {
  position: fixed;
  inset: 0;
  z-index: var(--uid-z-modal, 500);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--uid-space-md);
}
.admin-dialog-root__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
}
.admin-dialog {
  position: relative;
  width: min(560px, 100%);
  max-height: calc(100vh - 32px);
  display: flex;
  flex-direction: column;
  background: var(--uid-surface-raised);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-lg);
  box-shadow: var(--uid-shadow-lg);
  overflow: hidden;
}
.admin-dialog__hd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--uid-space-md);
  border-bottom: 1px solid var(--uid-border-subtle);
}
.admin-dialog__title {
  margin: 0;
  font-size: 16px;
  font-weight: var(--uid-font-weight-semibold);
}
.admin-dialog__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 0;
  background: transparent;
  border-radius: var(--uid-radius-sm);
  cursor: pointer;
  color: var(--uid-text-secondary);
}
.admin-dialog__close:hover {
  background: var(--uid-color-surface-hover, var(--uid-border-subtle));
  color: var(--uid-text-primary);
}
.admin-dialog__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--uid-space-md);
  display: flex;
  flex-direction: column;
  gap: var(--uid-space-md);
}
.admin-dialog__field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.admin-dialog__label {
  font-size: 12px;
  font-weight: var(--uid-font-weight-medium);
  color: var(--uid-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.admin-dialog__type-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--uid-space-xs);
}
.admin-dialog__type-card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--uid-space-sm);
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
}
.admin-dialog__type-card:hover {
  border-color: var(--uid-accent);
}
.admin-dialog__type-card--active {
  border-color: var(--uid-accent);
  background: color-mix(in srgb, var(--uid-accent) 8%, transparent);
}
.admin-dialog__type-name {
  font-size: 13px;
  font-weight: var(--uid-font-weight-semibold);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  color: var(--uid-text-primary);
}
.admin-dialog__type-help {
  font-size: 11px;
  color: var(--uid-text-tertiary);
}
.admin-dialog__textarea {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--uid-border-subtle);
  background: var(--uid-surface-base);
  border-radius: var(--uid-radius-md);
  color: var(--uid-text-primary);
  font: inherit;
  font-size: 13px;
  resize: vertical;
}
.admin-dialog__textarea:focus {
  outline: 2px solid color-mix(in srgb, var(--uid-accent) 18%, transparent);
  border-color: var(--uid-accent);
}
.admin-dialog__ft {
  display: flex;
  justify-content: flex-end;
  gap: var(--uid-space-xs);
  padding: var(--uid-space-md);
  border-top: 1px solid var(--uid-border-subtle);
}
.admin-dialog-enter-active,
.admin-dialog-leave-active { transition: opacity 200ms ease-out; }
.admin-dialog-enter-active .admin-dialog,
.admin-dialog-leave-active .admin-dialog {
  transition: transform 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.admin-dialog-enter-from,
.admin-dialog-leave-to { opacity: 0; }
.admin-dialog-enter-from .admin-dialog,
.admin-dialog-leave-to .admin-dialog { transform: translateY(8px); }
.admin-dialog__restore-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.admin-dialog__empty {
  margin: 0;
  padding: 16px 12px;
  border: 1px dashed var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  color: var(--uid-text-tertiary);
  font-size: 13px;
  text-align: center;
}
.admin-dialog__readonly {
  padding: 8px 10px;
  background: var(--uid-surface-base);
  border: 1px solid var(--uid-border-subtle);
  border-radius: var(--uid-radius-md);
  font-family: var(--uid-font-family-mono, ui-monospace, monospace);
  font-size: 13px;
  color: var(--uid-text-secondary);
}
.admin-dialog__required {
  color: var(--uid-color-danger, #dc2626);
  font-weight: var(--uid-font-weight-semibold);
  margin-inline-start: 2px;
}
.admin-dialog__type-icon {
  vertical-align: -2px;
  margin-inline-end: 4px;
}
.admin-dialog__hint {
  flex: 1;
  font-size: var(--uid-font-size-xs, 11px);
  color: var(--uid-text-tertiary, #9ca3af);
}
</style>
