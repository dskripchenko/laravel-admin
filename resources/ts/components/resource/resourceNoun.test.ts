import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useI18nStore } from '../../stores/i18n'
import {
  createTitle,
  createdToast,
  deleteConfirmText,
  deletedToast,
  editTitle,
  emptyDescription,
  nounCase,
  recordFallbackTitle,
} from './resourceNoun'

/**
 * The plural label glued into a sentence read "Создать: Авторы" and "Create:
 * Authors". These phrases name one record by singular_label, and word
 * themselves without it when the resource has none.
 */
const EN: Record<string, string> = {
  'запись': 'record',
  'Создать: :singular': 'Create :singular',
  'Новая запись: :label': 'New record: :label',
  'Редактирование: :title': 'Edit :singular: :title',
  'Редактирование: :singular #:id': 'Edit :singular #:id',
  'Удалить «:title»?': 'Delete :singular “:title”?',
  'Удалить эту запись?': 'Delete this :singular?',
  'Новая запись создана.': ':Singular created.',
  'Запись успешно удалена.': ':Singular deleted.',
  'Пока нет ни одной записи — создайте первую.': 'No :plural yet. Create the first :singular.',
  'В этом разделе пока нет записей.': 'No :plural yet.',
}

describe('resourceNoun', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('Russian source, with a singular', () => {
    const meta = { label: 'Авторы', singular_label: 'автор' }

    it('words the titles around the nominative', () => {
      expect(createTitle(meta, 'authors')).toBe('Создать: автор')
      expect(editTitle(meta, 'authors', 5, 'Иван Петров')).toBe('Редактирование: Иван Петров')
      expect(editTitle(meta, 'authors', 5, null)).toBe('Редактирование: автор #5')
      expect(recordFallbackTitle(meta, 'authors', 5)).toBe('Автор #5')
    })

    it('keeps the confirmations and toasts free of a case it cannot inflect', () => {
      expect(deleteConfirmText(meta, 'authors', 'Иван Петров')).toBe('Удалить «Иван Петров»?')
      expect(deleteConfirmText(meta, 'authors')).toBe('Удалить эту запись?')
      expect(createdToast(meta, 'authors')).toBe('Новая запись создана.')
      expect(deletedToast(meta, 'authors')).toBe('Запись успешно удалена.')
    })
  })

  describe('Russian source, without a singular', () => {
    const meta = { label: 'Авторы', singular_label: null }

    it('needs no singular', () => {
      expect(createTitle(meta, 'authors')).toBe('Новая запись: Авторы')
      expect(editTitle(meta, 'authors', 5, null)).toBe('Авторы: запись #5')
      expect(emptyDescription(meta, 'authors', true)).toBe('Пока нет ни одной записи — создайте первую.')
    })
  })

  describe('English translation', () => {
    beforeEach(() => {
      useI18nStore().setMessages(EN)
    })

    it('uses the singular where Russian does not', () => {
      const meta = { label: 'Authors', singular_label: 'author' }
      expect(createTitle(meta, 'authors')).toBe('Create author')
      expect(editTitle(meta, 'authors', 5, 'Ivan Petrov')).toBe('Edit author: Ivan Petrov')
      expect(editTitle(meta, 'authors', 5, null)).toBe('Edit author #5')
      expect(deleteConfirmText(meta, 'authors', 'Ivan Petrov')).toBe('Delete author “Ivan Petrov”?')
      expect(deleteConfirmText(meta, 'authors')).toBe('Delete this author?')
      expect(createdToast(meta, 'authors')).toBe('Author created.')
      expect(deletedToast(meta, 'authors')).toBe('Author deleted.')
      expect(emptyDescription(meta, 'authors', true)).toBe('No authors yet. Create the first author.')
      expect(emptyDescription(meta, 'authors', false)).toBe('No authors yet.')
    })

    it('falls back to "record" without a singular', () => {
      const meta = { label: 'Authors' }
      expect(createTitle(meta, 'authors')).toBe('New record: Authors')
      expect(deleteConfirmText(meta, 'authors')).toBe('Delete this record?')
      expect(createdToast(meta, 'authors')).toBe('Record created.')
    })
  })

  it('lowers a caption but keeps an acronym', () => {
    expect(nounCase('API Keys')).toBe('API keys')
    expect(nounCase('Blog Posts')).toBe('blog posts')
  })
})
