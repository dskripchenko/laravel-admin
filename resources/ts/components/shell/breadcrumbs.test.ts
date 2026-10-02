import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { defineComponent, h, type ComputedRef } from 'vue'
import { findMenuTrail, useBreadcrumbs, type Crumb } from './breadcrumbs'
import { useMenuStore, type MenuItem } from '../../stores/menu'
import { useResourceFormStore } from '../../stores/resourceForm'

const Stub = defineComponent({ render: () => h('div') })

const menu: MenuItem[] = [
  { key: 'home', label: 'Главная', routeName: 'admin.home' },
  {
    key: 'showcase',
    label: 'Витрина',
    group: 'Демо',
    children: [
      { key: 'articles', label: 'Статьи', routeName: 'admin.resource.articles.index' },
      { key: 'grids', label: 'Таблицы', url: '/screens/grids' },
    ],
  },
]

function mkRouter(): Router {
  const resource = (slug: string, label: string) => [
    { path: `/r/${slug}`, name: `admin.resource.${slug}.index`, component: Stub, meta: { kind: 'resource', slug, title: label } },
    { path: `/r/${slug}/create`, name: `admin.resource.${slug}.create`, component: Stub, meta: { kind: 'resource', slug, title: label } },
    { path: `/r/${slug}/:id/edit`, name: `admin.resource.${slug}.edit`, component: Stub, meta: { kind: 'resource', slug, title: label } },
    { path: `/r/${slug}/:id`, name: `admin.resource.${slug}.view`, component: Stub, meta: { kind: 'resource', slug, title: label } },
  ]
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'admin.home', component: Stub, meta: { kind: 'system', title: 'Главная' } },
      { path: '/profile', name: 'admin.profile', component: Stub, meta: { kind: 'system', title: 'Профиль' } },
      { path: '/screens/grids', name: 'admin.screen.grids', component: Stub, meta: { kind: 'screen', slug: 'grids', title: 'Таблицы' } },
      ...resource('articles', 'Статьи'),
      ...resource('tags', 'Теги'),
    ],
  })
}

async function crumbsAt(router: Router, path: string): Promise<Crumb[]> {
  let crumbs: ComputedRef<Crumb[]> | null = null
  const Probe = defineComponent({
    setup() {
      crumbs = useBreadcrumbs()
      return () => h('div')
    },
  })
  await router.push(path)
  await router.isReady()
  mount(Probe, { global: { plugins: [router] } })
  return crumbs!.value
}

describe('findMenuTrail', () => {
  it('finds a nested item with its parents', () => {
    const trail = findMenuTrail(menu, { name: 'admin.resource.articles.index', path: '/r/articles', params: {}, meta: {} })
    expect(trail.map((i) => i.key)).toEqual(['showcase', 'articles'])
  })

  it('takes a record page as part of its resource list', () => {
    const trail = findMenuTrail(menu, { name: 'admin.resource.articles.edit', path: '/r/articles/5/edit', params: { id: '5' }, meta: {} })
    expect(trail.map((i) => i.key)).toEqual(['showcase', 'articles'])
  })

  it('matches by url', () => {
    const trail = findMenuTrail(menu, { name: 'admin.screen.grids', path: '/screens/grids', params: {}, meta: {} })
    expect(trail.map((i) => i.key)).toEqual(['showcase', 'grids'])
  })

  it('is empty for a page the menu does not hold', () => {
    expect(findMenuTrail(menu, { name: 'admin.profile', path: '/profile', params: {}, meta: {} })).toEqual([])
  })
})

describe('useBreadcrumbs', () => {
  let router: Router

  beforeEach(() => {
    setActivePinia(createPinia())
    useMenuStore().setItems(menu)
    router = mkRouter()
  })

  it('gives the menu path, the last crumb without a link', async () => {
    const crumbs = await crumbsAt(router, '/r/articles')
    expect(crumbs.map((c) => c.label)).toEqual(['Демо', 'Витрина', 'Статьи'])
    expect(crumbs[0]!.to).toBeUndefined()
    expect(crumbs[2]!.to).toBeUndefined()
  })

  it('adds the record and the editing step, the record linking to its view', async () => {
    const form = useResourceFormStore()
    form.slug = 'articles'
    form.recordId = '5'
    form.initial = { title: 'Hello world' }
    const crumbs = await crumbsAt(router, '/r/articles/5/edit')
    expect(crumbs.map((c) => c.label)).toEqual(['Демо', 'Витрина', 'Статьи', 'Hello world', 'Редактирование'])
    expect(crumbs[2]!.to).toEqual({ name: 'admin.resource.articles.index' })
    expect(crumbs[3]!.to).toEqual({ name: 'admin.resource.articles.view', params: { id: '5' } })
  })

  it('prefers Resource::recordTitle(), and names an unknown record by its id', async () => {
    const form = useResourceFormStore()
    form.slug = 'articles'
    form.recordId = '5'
    form.initial = { title: 'Hello' }
    form.recordTitle = 'Article «Hello»'
    expect((await crumbsAt(router, '/r/articles/5')).at(-1)!.label).toBe('Article «Hello»')
    expect((await crumbsAt(router, '/r/articles/7')).at(-1)!.label).toBe('#7')
  })

  it('names the creating step', async () => {
    const crumbs = await crumbsAt(router, '/r/articles/create')
    expect(crumbs.map((c) => c.label)).toEqual(['Демо', 'Витрина', 'Статьи', 'Создание'])
  })

  it('names a resource the menu does not hold by its route title', async () => {
    const crumbs = await crumbsAt(router, '/r/tags/create')
    expect(crumbs[0]!.label).toBe('tags')
    expect(crumbs.at(-1)!.label).toBe('Создание')
  })

  it('does not name a menu page twice', async () => {
    const crumbs = await crumbsAt(router, '/screens/grids')
    expect(crumbs.map((c) => c.label)).toEqual(['Демо', 'Витрина', 'Таблицы'])
  })

  it('names a page outside the menu by its title', async () => {
    expect((await crumbsAt(router, '/profile')).map((c) => c.label)).toEqual(['Профиль'])
  })
})
