<script setup lang="ts">
/**
 * FieldGalleryPage — каталог field-компонентов.
 *
 * Эталон: docs/design_handoff_laravel_admin/screens-secondary.jsx (FieldGallery).
 *
 * 3-col grid из demo-cards. Каждая card:
 *   - Group label (например "Текстовые")
 *   - Тип поля (json type-key)
 *   - Демо-компонент с sample-данными
 *   - Краткое описание
 *
 * Используется как:
 *   1. Тестовая площадка для devs (визуально все Uid* fields на одной странице)
 *   2. Docs / referencer для host'ов
 *
 * Все секции рендерятся через FieldRenderer + provideFormState с локальной
 * state-mock'ой.
 */
import { reactive } from 'vue'
import { UidCard } from '@dskripchenko/ui'
import FieldRenderer, { type FieldNode } from '../render/FieldRenderer.vue'
import { provideFormState } from '../render/formState'

interface Demo {
  type: string
  group: string
  title: string
  description: string
  /** Demo node для FieldRenderer'а. */
  node: FieldNode
  /** Initial state для form-context. */
  initial: Record<string, unknown>
}

const DEMOS: Demo[] = [
  // Текстовые
  {
    type: 'text',
    group: 'Текстовые',
    title: 'Input',
    description: 'Простой текстовый input. type=email/url/password/tel доступны через inputType.',
    node: { type: 'text', name: 'demo_text', label: 'Заголовок статьи', placeholder: 'Например: Введение в Laravel' },
    initial: { demo_text: 'Hello World' },
  },
  {
    type: 'textarea',
    group: 'Текстовые',
    title: 'Textarea',
    description: 'Многострочный input с настраиваемым числом rows.',
    node: { type: 'textarea', name: 'demo_textarea', label: 'Описание', rows: 4 },
    initial: { demo_textarea: 'Multi-line text...' },
  },
  {
    type: 'number',
    group: 'Текстовые',
    title: 'NumberInput',
    description: 'Числовой input. Empty/NaN автоматически конвертируются в null.',
    node: { type: 'number', name: 'demo_number', label: 'Цена', min: 0, max: 1000 },
    initial: { demo_number: 42 },
  },
  // Выбор
  {
    type: 'select',
    group: 'Выбор',
    title: 'Select',
    description: 'Single-select с options. Поддерживает searchable + clearable.',
    node: {
      type: 'select',
      name: 'demo_select',
      label: 'Категория',
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
    group: 'Выбор',
    title: 'Checkbox',
    description: 'Boolean toggle. inlineLabel рядом с боксом.',
    node: { type: 'checkbox', name: 'demo_checkbox', label: 'Опубликовать', inlineLabel: 'Сделать доступным всем' },
    initial: { demo_checkbox: true },
  },
  // Дата/время
  {
    type: 'date',
    group: 'Дата/время',
    title: 'DatePicker',
    description: 'date / datetime-local / time через inputType.',
    node: { type: 'date', name: 'demo_date', label: 'Дата публикации', inputType: 'date' },
    initial: { demo_date: '2026-05-01' },
  },
]

// Группируем для рендера.
const groupedDemos = DEMOS.reduce<Record<string, Demo[]>>((acc, d) => {
  if (!acc[d.group]) acc[d.group] = []
  acc[d.group].push(d)
  return acc
}, {})

// provideFormState на корневом уровне — все demo'и шарят один form-context
// (имена не пересекаются благодаря demo_ префиксу).
const allInitial = reactive<Record<string, unknown>>(
  DEMOS.reduce<Record<string, unknown>>((acc, d) => Object.assign(acc, d.initial), {}),
)
provideFormState(allInitial)
</script>

<template>
  <section class="admin-page admin-field-gallery">
    <header class="admin-page__hd">
      <div class="admin-page__title-wrap">
        <h1 class="admin-page__title">Field Gallery</h1>
        <div class="admin-page__count">
          Каталог встроенных field-компонентов library + примеры использования
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
