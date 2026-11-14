/**
 * importFlow — the default import of a file into a resource, over
 * Dskripchenko\LaravelAdmin\Import\ImportController (see
 * docs/ru/api/exports-imports.md, «Import»):
 *
 *   1. POST /import/upload  (multipart: file, resource)  → { disk, path }
 *   2. POST /import/preview (resource, path)              → { headers, sample, total, format, auto_mapping }
 *   3. POST /import/start   (resource, path, mapping)     → { process }
 *   4. GET  /import/status?id={process.id}                → { process }, polled
 *      until process.status is `completed` or `failed`
 *
 * The mapping is the backend's own auto_mapping; a host that wants the user
 * to correct it intercepts @import and opens a wizard of its own.
 */

export type ImportProcessStatus = 'pending' | 'running' | 'completed' | 'failed'

/** ImportController::serialize() — the payload's `process`. */
export interface ImportProcess {
  id: number
  resource_slug: string
  status: ImportProcessStatus
  processed_count: number
  created_count: number
  updated_count: number
  error_count: number
  errors: Array<{ row: number; error: string }> | null
  started_at: string | null
  completed_at: string | null
}

export interface ImportUploadPayload {
  disk: string
  path: string
}

export interface ImportPreviewPayload {
  headers: string[]
  sample: Array<Record<string, unknown>>
  total: number | null
  format: string
  auto_mapping: Record<string, string>
}

/** The part of the admin client the flow needs; the payload is already unwrapped. */
export interface ImportClient {
  get<T>(url: string): Promise<T>
  post<T>(url: string, data?: unknown): Promise<T>
}

export interface ImportFlowOptions {
  /** The pause between two status requests, ms. */
  intervalMs?: number
  /** How long to keep polling before giving up, ms. */
  timeoutMs?: number
  /** The wait itself — replaced in tests. */
  sleep?: (ms: number) => Promise<void>
}

/** The import ended as `failed`, or did not end in time. */
export class ImportFlowError extends Error {
  constructor(
    message: string,
    public readonly process: ImportProcess | null,
  ) {
    super(message)
    this.name = 'ImportFlowError'
  }
}

const TERMINAL: ReadonlySet<ImportProcessStatus> = new Set(['completed', 'failed'])

export function isImportFinished(process: ImportProcess): boolean {
  return TERMINAL.has(process.status)
}

/** The rows written — created and updated together. */
export function importedCount(process: ImportProcess): number {
  return (process.created_count ?? 0) + (process.updated_count ?? 0)
}

/**
 * Runs the four steps and resolves with the finished process — `completed`,
 * possibly with row errors in error_count. A `failed` process, or one still
 * running when timeoutMs is up, rejects with ImportFlowError.
 */
export async function runImport(
  client: ImportClient,
  resource: string,
  file: File,
  options: ImportFlowOptions = {},
): Promise<ImportProcess> {
  const intervalMs = options.intervalMs ?? 800
  const timeoutMs = options.timeoutMs ?? 90_000
  const sleep = options.sleep ?? ((ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms)))

  const form = new FormData()
  form.append('file', file)
  form.append('resource', resource)
  const uploaded = await client.post<ImportUploadPayload>('/import/upload', form)

  const preview = await client.post<ImportPreviewPayload>('/import/preview', {
    resource,
    path: uploaded.path,
  })

  const started = await client.post<{ process: ImportProcess }>('/import/start', {
    resource,
    path: uploaded.path,
    mapping: preview.auto_mapping ?? {},
  })

  let process = started.process
  const deadline = Date.now() + timeoutMs
  while (!isImportFinished(process)) {
    if (Date.now() >= deadline) {
      throw new ImportFlowError('The import did not finish in time', process)
    }
    await sleep(intervalMs)
    const status = await client.get<{ process: ImportProcess }>(
      `/import/status?id=${encodeURIComponent(String(process.id))}`,
    )
    process = status.process
  }

  if (process.status === 'failed') {
    const reason = process.errors?.find((e) => e.row === 0)?.error ?? 'The import failed'
    throw new ImportFlowError(reason, process)
  }

  return process
}
