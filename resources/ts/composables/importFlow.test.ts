import { describe, expect, it, vi } from 'vitest'
import { ImportFlowError, importedCount, runImport, type ImportClient, type ImportProcess } from './importFlow'

function processOf(status: ImportProcess['status'], extra: Partial<ImportProcess> = {}): ImportProcess {
  return {
    id: 7,
    resource_slug: 'users',
    status,
    processed_count: 0,
    created_count: 0,
    updated_count: 0,
    error_count: 0,
    errors: [],
    started_at: null,
    completed_at: null,
    ...extra,
  }
}

/** A client answering as ImportController does, the payload already unwrapped. */
function fakeClient(statuses: ImportProcess[], started: ImportProcess = processOf('running')) {
  const post = vi.fn(async (url: string, data?: unknown) => {
    void data
    if (url === '/import/upload') return { disk: 'local', path: 'imports/abc/users.csv' }
    if (url === '/import/preview') {
      return { headers: ['Name', 'E-mail'], sample: [], total: 2, format: 'csv', auto_mapping: { Name: 'name' } }
    }
    if (url === '/import/start') return { process: started }
    throw new Error(`unexpected POST ${url}`)
  })
  const get = vi.fn(async (url: string) => {
    if (!url.startsWith('/import/status?')) throw new Error(`unexpected GET ${url}`)
    const next = statuses.shift()
    if (!next) throw new Error('no more statuses')
    return { process: next }
  })
  return { client: { get, post } as unknown as ImportClient, get, post }
}

const file = new File(['Name,E-mail\nAnn,ann@example.com\n'], 'users.csv', { type: 'text/csv' })
const noWait = { sleep: () => Promise.resolve() }

describe('importFlow', () => {
  it('uploads, previews, starts with path and auto_mapping, and polls status by id until completed', async () => {
    const { client, get, post } = fakeClient([
      processOf('running', { processed_count: 1 }),
      processOf('completed', { processed_count: 2, created_count: 1, updated_count: 1 }),
    ])

    const result = await runImport(client, 'users', file, noWait)

    expect(post).toHaveBeenCalledTimes(3)
    const [uploadUrl, form] = post.mock.calls[0]
    expect(uploadUrl).toBe('/import/upload')
    expect(form).toBeInstanceOf(FormData)
    expect((form as FormData).get('resource')).toBe('users')
    expect((form as FormData).get('file')).toBeInstanceOf(File)
    expect(post.mock.calls[1]).toEqual(['/import/preview', { resource: 'users', path: 'imports/abc/users.csv' }])
    expect(post.mock.calls[2]).toEqual([
      '/import/start',
      { resource: 'users', path: 'imports/abc/users.csv', mapping: { Name: 'name' } },
    ])
    expect(get.mock.calls.map((c) => c[0])).toEqual(['/import/status?id=7', '/import/status?id=7'])
    expect(result.status).toBe('completed')
    expect(importedCount(result)).toBe(2)
  })

  it('does not poll when start already returns a finished process', async () => {
    const { client, get } = fakeClient([], processOf('completed', { created_count: 3, error_count: 1 }))

    const result = await runImport(client, 'users', file, noWait)

    expect(get).not.toHaveBeenCalled()
    expect(importedCount(result)).toBe(3)
    expect(result.error_count).toBe(1)
  })

  it('rejects with the whole-import error of a failed process', async () => {
    const { client } = fakeClient([processOf('failed', { errors: [{ row: 0, error: 'Unreadable file' }] })])

    const error = await runImport(client, 'users', file, noWait).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ImportFlowError)
    expect((error as ImportFlowError).message).toBe('Unreadable file')
    expect((error as ImportFlowError).process?.status).toBe('failed')
  })

  it('gives up when the process does not finish in time', async () => {
    const { client } = fakeClient([processOf('running'), processOf('running'), processOf('running')])

    const error = await runImport(client, 'users', file, { ...noWait, timeoutMs: 0 }).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ImportFlowError)
    expect((error as ImportFlowError).process?.status).toBe('running')
  })
})
