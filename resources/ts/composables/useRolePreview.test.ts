import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import MockAdapter from 'axios-mock-adapter'
import { setAdminClient, clearAdminClient } from '../stores/registry'
import { createAdminClient } from '../api/client'
import { loadPreviewRoles, rolePreviewNavigation, startRolePreview, stopRolePreview } from './useRolePreview'

describe('useRolePreview', () => {
  let mock: MockAdapter
  let reload: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    const client = createAdminClient({ baseURL: 'http://api.test' })
    setAdminClient(client)
    mock = new MockAdapter(client.raw)
    reload = vi.spyOn(rolePreviewNavigation, 'reload').mockImplementation(() => {})
  })

  afterEach(() => {
    mock.reset()
    clearAdminClient()
    reload.mockRestore()
  })

  it('loads the roles and the current one', async () => {
    mock.onGet('/auth/previewRoles').reply(200, {
      success: true,
      payload: { roles: [{ slug: 'underwriter', name: 'Андеррайтер' }], current: null },
    })
    expect(await loadPreviewRoles()).toEqual({ roles: [{ slug: 'underwriter', name: 'Андеррайтер' }], current: null })
  })

  it('starts with the slug and reloads the page', async () => {
    mock.onPost('/auth/startRolePreview').reply((config) => {
      expect(JSON.parse(config.data)).toEqual({ role: 'underwriter' })
      return [200, { success: true, payload: { rolePreview: { slug: 'underwriter', name: 'Андеррайтер' } } }]
    })
    await startRolePreview('underwriter')
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('stops and reloads; a refusal does not reload', async () => {
    mock.onPost('/auth/stopRolePreview').replyOnce(200, { success: true, payload: { rolePreview: null } })
    await stopRolePreview()
    expect(reload).toHaveBeenCalledTimes(1)

    mock.onPost('/auth/stopRolePreview').replyOnce(400, { success: false, payload: { errorKey: 'no_active_role_preview' } })
    await expect(stopRolePreview()).rejects.toBeTruthy()
    expect(reload).toHaveBeenCalledTimes(1)
  })
})
