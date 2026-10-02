import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import SlugField from './SlugField.vue'
import { slugify } from './support/slugify'
import fixtures from '../../__fixtures__/slug-cases.json'

describe('slugify (the port of Slug::generate)', () => {
  // The same cases are run against the PHP side in tests/Unit/SlugGenerateTest.php.
  for (const [source, separator, expected] of (fixtures as unknown as { cases: [string, string, string][] }).cases) {
    it(`${JSON.stringify(source)} with ${JSON.stringify(separator)} → ${JSON.stringify(expected)}`, () => {
      expect(slugify(source, separator)).toBe(expected)
    })
  }

  it('treats null and undefined as an empty source', () => {
    expect(slugify(null)).toBe('')
    expect(slugify(undefined)).toBe('')
  })
})

function mountSlug(initial: Record<string, unknown>, props: Record<string, unknown> = {}) {
  let ctx: FormStateContext | null = null
  const w = mount(
    defineComponent({
      setup() {
        ctx = provideFormState(initial)
        return () => h(SlugField, { name: 'slug', from: 'title', ...props })
      },
    }),
  )
  return { w, ctx: ctx! }
}

describe('SlugField', () => {
  it('fills the slug from the source as it changes', async () => {
    const { ctx } = mountSlug({ title: '', slug: '' })
    ctx.setField('title', 'Новая статья')
    await nextTick()
    expect(ctx.getField('slug')).toBe('novaya-statya')
    ctx.setField('title', 'Новая статья о Vue')
    await nextTick()
    expect(ctx.getField('slug')).toBe('novaya-statya-o-vue')
  })

  it('uses the separator from the backend', async () => {
    const { ctx } = mountSlug({ title: '', slug: '' }, { separator: '_' })
    ctx.setField('title', 'Hello World')
    await nextTick()
    expect(ctx.getField('slug')).toBe('hello_world')
  })

  it('stops following once the slug is edited by hand, and resumes when it is cleared', async () => {
    const { w, ctx } = mountSlug({ title: '', slug: '' })
    ctx.setField('title', 'First')
    await nextTick()
    await w.find('input').setValue('custom-slug')
    ctx.setField('title', 'Second')
    await nextTick()
    expect(ctx.getField('slug')).toBe('custom-slug')

    await w.find('input').setValue('')
    ctx.setField('title', 'Third')
    await nextTick()
    expect(ctx.getField('slug')).toBe('third')
  })

  it('keeps a saved slug that does not match its source', async () => {
    const { ctx } = mountSlug({ title: 'Old title', slug: 'hand-made' })
    ctx.setField('title', 'New title')
    await nextTick()
    expect(ctx.getField('slug')).toBe('hand-made')
  })

  it('follows a saved slug that still matches its source', async () => {
    const { ctx } = mountSlug({ title: 'Old title', slug: 'old-title' })
    ctx.setField('title', 'New title')
    await nextTick()
    expect(ctx.getField('slug')).toBe('new-title')
  })

  it('with follow=false fills only an empty slug', async () => {
    const saved = mountSlug({ title: 'Old title', slug: 'old-title' }, { follow: false })
    saved.ctx.setField('title', 'New title')
    await nextTick()
    expect(saved.ctx.getField('slug')).toBe('old-title')

    const fresh = mountSlug({ title: '', slug: '' }, { follow: false })
    fresh.ctx.setField('title', 'Fresh')
    await nextTick()
    fresh.ctx.setField('title', 'Fresh one')
    await nextTick()
    expect(fresh.ctx.getField('slug')).toBe('fresh-one')
  })

  it('takes the first filled locale of a translatable source', async () => {
    const { ctx } = mountSlug({ title: { ru: '', en: '' }, slug: '' })
    ctx.setField('title', { ru: 'Привет', en: 'Hello' })
    await nextTick()
    expect(ctx.getField('slug')).toBe('privet')
  })

  it('does not write into a read-only slug', async () => {
    const { ctx } = mountSlug({ title: '', slug: '' }, { readonly: true })
    ctx.setField('title', 'Anything')
    await nextTick()
    expect(ctx.getField('slug')).toBe('')
  })
})
