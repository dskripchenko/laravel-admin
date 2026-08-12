<script setup lang="ts">
/**
 * FieldGalleryPage — the catalogue of field components.
 *
 * It follows docs/design_handoff_laravel_admin/screens-secondary.jsx
 * (FieldGallery).
 *
 * A three-column grid of demo cards, each showing:
 *   - the group's label ("Text", for instance)
 *   - the field's type, its JSON type key
 *   - a demo component with sample data
 *   - a short description
 *
 * It serves two purposes:
 *   1. a playground for developers, with every Uid* field on one page
 *   2. documentation and a reference for the hosts
 *
 * Every section is rendered through FieldRenderer and provideFormState over a
 * local mock state.
 */
import { reactive, computed } from 'vue'
import { trSafe as tr } from '../../stores/i18n'
import { UidCard } from '@dskripchenko/ui'
import FieldRenderer, { type FieldNode } from '../render/FieldRenderer.vue'
import { provideFormState } from '../render/formState'

interface Demo {
  type: string
  group: string
  title: string
  description: string
  /** The demo node for FieldRenderer. */
  node: FieldNode
  /** The initial state of the form context. */
  initial: Record<string, unknown>
}

/**
 * The list is built by a FUNCTION rather than a module constant: a `tr()` at
 * module level would run once, and before the dictionary arrives — the gallery
 * would stay in the source language for good.
 */
function demos(): Demo[] {
  return [
  // Text
  {
    type: 'text',
    group: tr('Текстовые'),
    title: 'Input',
    description: tr('Простой текстовый ввод. Варианты email/url/password/tel задаются через inputType.'),
    node: { type: 'text', name: 'demo_text', label: tr('Заголовок статьи'), placeholder: tr('Например: Введение в Laravel') },
    initial: { demo_text: 'Hello World' },
  },
  {
    type: 'textarea',
    group: tr('Текстовые'),
    title: 'Textarea',
    description: tr('Многострочный ввод с настраиваемым числом строк.'),
    node: { type: 'textarea', name: 'demo_textarea', label: tr('Описание'), rows: 4 },
    initial: { demo_textarea: 'Multi-line text...' },
  },
  {
    type: 'number',
    group: tr('Текстовые'),
    title: 'NumberInput',
    description: tr('Числовой ввод. Пусто и NaN превращаются в null.'),
    node: { type: 'number', name: 'demo_number', label: tr('Цена'), min: 0, max: 1000 },
    initial: { demo_number: 42 },
  },
  // Selection
  {
    type: 'select',
    group: tr('Выбор'),
    title: 'Select',
    description: tr('Выбор одного значения. Поддерживает поиск и очистку.'),
    node: {
      type: 'select',
      name: 'demo_select',
      label: tr('Категория'),
      options: [
        { value: 'frontend', label: 'Frontend' },
        { value: 'backend', label: 'Backend' },
        { value: 'devops', label: 'DevOps' },
      ],
    },
    initial: { demo_select: 'backend' },
  },
  {
    type: 'checkbox',
    group: tr('Выбор'),
    title: 'Checkbox',
    description: tr('Переключатель. Подпись ставится рядом с флажком.'),
    node: { type: 'checkbox', name: 'demo_checkbox', label: tr('Опубликовать'), inlineLabel: tr('Сделать доступным всем') },
    initial: { demo_checkbox: true },
  },
  // Date and time
  {
    type: 'date',
    group: tr('Дата/время'),
    title: 'DatePicker',
    description: tr('Дата, дата со временем или время — через inputType.'),
    node: { type: 'date', name: 'demo_date', label: tr('Дата публикации'), inputType: 'date' },
    initial: { demo_date: '2026-05-01' },
  },
  ]
}

// Grouped for rendering.
const groupedDemos = computed<Record<string, Demo[]>>(() => demos().reduce<Record<string, Demo[]>>((acc, d) => {
  if (!acc[d.group]) acc[d.group] = []
  acc[d.group].push(d)

  return acc
}, {}))

// provideFormState sits at the root level, so every demo shares one form
// context; the names do not collide thanks to the demo_ prefix.
const allInitial = reactive<Record<string, unknown>>(
  demos().reduce<Record<string, unknown>>((acc, d) => Object.assign(acc, d.initial), {}),
)
provideFormState(allInitial)
</script>

<template>
  <section class="admin-page admin-field-gallery">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">Field Gallery</h1>
        <div class="admin-page__count">
          {{ tr('Каталог встроенных полей с примерами использования') }}
        </div>
      </div>
    </header>

    <section v-for="(items, group) in groupedDemos" :key="group" class="admin-gallery__group">
      <h2 class="admin-gallery__group-title">{{ group }}</h2>
      <div class="admin-gallery__grid">
        <UidCard
          v-for="demo in items"
          :key="demo.type + demo.title"
          padding="md"
          class="admin-gallery__card"
        >
          <header class="admin-gallery__card-hd">
            <h3 class="admin-gallery__card-title">{{ demo.title }}</h3>
            <code class="admin-gallery__card-type">type: {{ demo.type }}</code>
          </header>
          <p class="admin-gallery__card-desc">{{ demo.description }}</p>
          <div class="admin-gallery__card-demo">
            <FieldRenderer :node="demo.node" />
          </div>
        </UidCard>
      </div>
    </section>
  </section>
</template>

<style>
.admin-gallery__group {
  margin-bottom: var(--uid-space-2xl);
}
.admin-gallery__group-title {
  margin: 0 0 var(--uid-space-md);
  font-size: var(--uid-font-size-lg);
  font-weight: var(--uid-font-weight-semibold);
  color: var(--uid-text-primary);
}
.admin-gallery__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--uid-space-md);
}
@media (max-width: 960px) {
  .admin-gallery__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
  .admin-gallery__grid { grid-template-columns: 1fr; }
}
.admin-gallery__card {
  display: flex;
  flex-direction: column;
}
.admin-gallery__card-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--uid-space-sm);
  margin-bottom: var(--uid-space-xs);
}
.admin-gallery__card-title {
  margin: 0;
  font-size: var(--uid-font-size-sm);
  font-weight: var(--uid-font-weight-semibold);
}
.admin-gallery__card-type {
  font-family: var(--uid-font-family-mono);
  font-size: 11px;
  color: var(--uid-text-tertiary);
  background: var(--uid-surface-base);
  padding: 1px 6px;
  border-radius: var(--uid-radius-sm);
  border: 1px solid var(--uid-border-subtle);
}
.admin-gallery__card-desc {
  margin: 0 0 var(--uid-space-md);
  font-size: var(--uid-font-size-xs);
  color: var(--uid-text-tertiary);
  line-height: var(--uid-line-height-normal);
}
.admin-gallery__card-demo {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}
</style>
