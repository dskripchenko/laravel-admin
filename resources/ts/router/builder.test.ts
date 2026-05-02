import { describe, it, expect } from 'vitest'
import { buildRoutesFromManifest, type RouteComponentResolver } from './builder'
import type { AdminManifest } from '../stores/manifest'

import { defineComponent } from 'vue'

const stub = defineComponent({ name: 'Stub', render: () => null })
const components: RouteComponentResolver = {
  resourceIndex: stub,
  resourceCreate: stub,
  resourceEdit: stub,
  resourceView: stub,
  screen: stub,
  settings: stub,
  dashboard: stub,
}

const baseManifest: AdminManifest = {
  version: 'v1',
  locale: 'ru',
  resources: [],
  screens: [],
  settings: [],
  dashboards: [],
  plugins: [],
  permissions: [],
}

describe('buildRoutesFromManifest', () => {
  it('returns [] for null manifest', () => {
    expect(buildRoutesFromManifest(null, components)).toEqual([])
  })

  it('builds 3 routes per resource', () => {
    const manifest: AdminManifest = {
      ...baseManifest,
      resources: [
        {
          slug: 'users',
          label: 'Пользователи',
          permissions: {
            view: 'admin.users.view',
            create: 'admin.users.create',
            update: 'admin.users.update',
          },
          fields: [],
          columns: [],
          filters: [],
          actions: [],
          searchable: [],
          with: [],
          features: {},
        },
      ],
    }
    const routes = buildRoutesFromManifest(manifest, components)
    expect(routes).toHaveLength(4)
    expect(routes.map((r) => r.path)).toEqual([
      '/r/users',
      '/r/users/create',
      '/r/users/:id/edit',
      '/r/users/:id',
    ])
    expect(routes[0].name).toBe('admin.resource.users.index')
    expect(routes[2].name).toBe('admin.resource.users.edit')
    expect(routes[3].name).toBe('admin.resource.users.view')
    expect(routes[0].meta?.requiresAuth).toBe(true)
    expect(routes[0].meta?.permissions).toEqual(['admin.users.view'])
    expect(routes[1].meta?.permissions).toEqual(['admin.users.create'])
    expect(routes[2].meta?.permissions).toEqual(['admin.users.update'])
    // resourceEdit/resourceView передают slug+id через function-mode props.
    expect(typeof routes[2].props).toBe('function')
    expect(typeof routes[3].props).toBe('function')
    // resourceIndex/resourceCreate — slug запекается через object-mode.
    expect(routes[0].props).toEqual({ slug: 'users' })
    expect(routes[1].props).toEqual({ slug: 'users' })
  })

  it('omits permissions array when ability missing', () => {
    const manifest: AdminManifest = {
      ...baseManifest,
      resources: [
        {
          slug: 'posts',
          label: 'Posts',
          permissions: {},
          fields: [], columns: [], filters: [], actions: [],
          searchable: [], with: [], features: {},
        },
      ],
    }
    const routes = buildRoutesFromManifest(manifest, components)
    expect(routes[0].meta?.permissions).toEqual([])
  })

  it('builds screen route', () => {
    const manifest: AdminManifest = {
      ...baseManifest,
      screens: [
        { slug: 'reports', name: 'Отчёты', description: null, permission: 'admin.reports.view' },
      ],
    }
    const routes = buildRoutesFromManifest(manifest, components)
    expect(routes).toHaveLength(1)
    expect(routes[0].path).toBe('/screens/reports')
    expect(routes[0].name).toBe('admin.screen.reports')
    expect(routes[0].meta?.permissions).toEqual(['admin.reports.view'])
    expect(routes[0].meta?.title).toBe('Отчёты')
  })

  it('handles screen permission as array', () => {
    const manifest: AdminManifest = {
      ...baseManifest,
      screens: [
        { slug: 'x', name: 'X', description: null, permission: ['p1', 'p2'] },
      ],
    }
    const routes = buildRoutesFromManifest(manifest, components)
    expect(routes[0].meta?.permissions).toEqual(['p1', 'p2'])
  })

  it('builds settings route', () => {
    const manifest: AdminManifest = {
      ...baseManifest,
      settings: [
        {
          kind: 'settings',
          slug: 'general',
          label: 'Общие',
          permissions: { view: 'admin.settings.general.view' },
          fields: [],
        },
      ],
    }
    const routes = buildRoutesFromManifest(manifest, components)
    expect(routes[0].path).toBe('/settings/general')
    expect(routes[0].name).toBe('admin.settings.general')
    expect(routes[0].meta?.permissions).toEqual(['admin.settings.general.view'])
  })

  it('builds dashboard route from raw entry', () => {
    const manifest: AdminManifest = {
      ...baseManifest,
      dashboards: [
        { slug: 'main', label: 'Главный', permission: 'admin.dashboard.view' },
      ],
    }
    const routes = buildRoutesFromManifest(manifest, components)
    expect(routes[0].path).toBe('/dashboard/main')
    expect(routes[0].name).toBe('admin.dashboard.main')
    expect(routes[0].meta?.permissions).toEqual(['admin.dashboard.view'])
  })

  it('skips invalid dashboards', () => {
    const manifest: AdminManifest = {
      ...baseManifest,
      dashboards: [{}, { slug: '' }, { slug: 'x' }],
    }
    expect(buildRoutesFromManifest(manifest, components)).toHaveLength(1)
  })
})
