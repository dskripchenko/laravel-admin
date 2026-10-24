/**
 * Viewing the panel as a role (admin.auth.role_preview): a Super Admin picks a
 * role, and the menu, the screens and the backend checks answer as if it were
 * their only one, until they come back. Both ways reload the page — the
 * manifest, the menu and the permissions all change at once.
 */
import { getAdminClient } from '../stores/registry'

export interface PreviewRole {
  slug: string
  name: string
}

interface PreviewRolesPayload {
  roles: PreviewRole[]
  current: PreviewRole | null
}

/** A full reload; replaceable in tests. */
export const rolePreviewNavigation = {
  reload: (): void => window.location.reload(),
}

/** The roles the user may view the panel as, and the one viewed now. */
export async function loadPreviewRoles(): Promise<PreviewRolesPayload> {
  const payload = await getAdminClient().get<PreviewRolesPayload>('/auth/previewRoles')
  return { roles: payload?.roles ?? [], current: payload?.current ?? null }
}

export async function startRolePreview(slug: string): Promise<void> {
  await getAdminClient().post('/auth/startRolePreview', { role: slug })
  rolePreviewNavigation.reload()
}

export async function stopRolePreview(): Promise<void> {
  await getAdminClient().post('/auth/stopRolePreview')
  rolePreviewNavigation.reload()
}
