/**
 * Media values as the players need them.
 *
 * A value comes from the screen's state, a record's attribute, a table cell
 * or a ```video block, in whatever shape the host stores it: a URL, a storage
 * path, an uploaded file (`{url, mime, name, …}` — what a FileUpload puts in
 * the state), a media attachment (`{url, thumb_url, mime}`), or a list of
 * those. These helpers turn it into the kit's UidVideo sources and UidAudio
 * tracks.
 *
 * A storage path is resolved through the disk the backend described with
 * `MediaUrl::base()`: `{prefix, suffix, encode}` around the path.
 */
import type { UidAudioTrack, UidMediaSource, UidMediaTrack } from '@dskripchenko/ui'

/** The disk's address around a path; see the backend's MediaUrl::base(). */
export interface MediaDisk {
  prefix: string
  suffix?: string
  /** `component` — the path is one query value; `path` — the slashes stay. */
  encode?: 'component' | 'path'
}

export type MediaKind = 'video' | 'audio' | 'image' | null

const HLS_TYPE = 'application/x-mpegURL'

/** Already an address the browser loads: a scheme, `//host` or a root-relative path. */
export function isAbsoluteUrl(value: string): boolean {
  return value.startsWith('/') || /^[a-z][a-z0-9+.-]*:/i.test(value)
}

/** A URL as it is; a storage path through the disk, when there is one. */
export function resolveMediaUrl(value: string, disk?: MediaDisk | null): string {
  const v = value.trim()
  if (v === '' || !disk || isAbsoluteUrl(v)) return v
  const path = v.replace(/^\/+/, '')
  const encoded = disk.encode === 'component'
    ? encodeURIComponent(path)
    : path.split('/').map((s) => encodeURIComponent(s)).join('/')
  return `${disk.prefix}${encoded}${disk.suffix ?? ''}`
}

/** The address carried by a value: a string, or an object's url/src/path. */
export function mediaUrlOf(value: unknown, disk?: MediaDisk | null): string | null {
  if (typeof value === 'string') {
    const url = resolveMediaUrl(value, disk)
    return url === '' ? null : url
  }
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const o = value as Record<string, unknown>
    // An uploaded file carries its served URL; the path is the fallback.
    for (const key of ['url', 'src', 'download_url', 'path']) {
      const v = o[key]
      if (typeof v === 'string' && v.trim() !== '') return resolveMediaUrl(v, disk)
    }
  }
  return null
}

/** The MIME type a value states: `mime`, `mime_type` or `type`. */
export function mediaMimeOf(value: unknown): string | undefined {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return undefined
  const o = value as Record<string, unknown>
  for (const key of ['mime', 'mime_type', 'type']) {
    const v = o[key]
    if (typeof v === 'string' && v.includes('/')) return v
  }
  return undefined
}

const VIDEO_EXT = /\.(mp4|m4v|mov|webm|ogv|mkv)$/i
const AUDIO_EXT = /\.(mp3|m4a|m4b|aac|oga|ogg|opus|wav|flac|weba)$/i
const IMAGE_EXT = /\.(jpe?g|png|gif|webp|avif|svg)$/i

function pathOf(url: string): string {
  return url.replace(/[?#].*$/, '')
}

export function isHlsUrl(url: string): boolean {
  return /\.m3u8$/i.test(pathOf(url))
}

/**
 * Whether a file is a video, an audio or an image: by its MIME type, else by
 * the extension. An HLS playlist counts as video unless its MIME type says
 * audio.
 */
export function mediaKind(mime: string | null | undefined, url?: string | null): MediaKind {
  const m = (mime ?? '').toLowerCase()
  if (m.startsWith('video/')) return 'video'
  if (m.startsWith('audio/')) return 'audio'
  if (m.startsWith('image/')) return 'image'
  if (m.includes('mpegurl')) return 'video'
  const path = url ? pathOf(url) : ''
  if (VIDEO_EXT.test(path) || /\.m3u8$/i.test(path)) return 'video'
  if (AUDIO_EXT.test(path)) return 'audio'
  if (IMAGE_EXT.test(path)) return 'image'
  return null
}

/**
 * The type the kit is told up front: one the host stated (`type`), or HLS —
 * the adapter needs to know. A detected MIME type (`mime` of an upload) is not
 * passed on: `video/quicktime` or `audio/x-m4a` would make the kit refuse a
 * file the browser plays well.
 */
function sourceType(url: string, item: unknown): string | undefined {
  const o = item && typeof item === 'object' && !Array.isArray(item) ? item as Record<string, unknown> : {}
  if (typeof o.type === 'string' && o.type.includes('/')) return o.type
  if (isHlsUrl(url) || (mediaMimeOf(item) ?? '').toLowerCase().includes('mpegurl')) return HLS_TYPE
  return undefined
}

function asList(value: unknown): unknown[] {
  if (value === null || value === undefined || value === '') return []
  return Array.isArray(value) ? value : [value]
}

/** Video sources from a value: one, or a list (labelled ones make the quality menu). */
export function toVideoSources(value: unknown, disk?: MediaDisk | null): UidMediaSource[] {
  const out: UidMediaSource[] = []
  for (const item of asList(value)) {
    const src = mediaUrlOf(item, disk)
    if (!src) continue
    const o = item && typeof item === 'object' ? item as Record<string, unknown> : {}
    const type = sourceType(src, item)
    const label = typeof o.label === 'string' && o.label !== '' ? o.label : undefined
    out.push({ src, ...(type ? { type } : {}), ...(label ? { label } : {}) })
  }
  return out
}

/** The video a value describes in full: `{sources|src|url, poster, tracks, thumbnails, title}` or just sources. */
export interface VideoConfig {
  sources: UidMediaSource[]
  poster?: string
  tracks: UidMediaTrack[]
  thumbnails?: string
  title?: string
}

export function toVideoConfig(value: unknown, disk?: MediaDisk | null): VideoConfig {
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const o = value as Record<string, unknown>
    if ('sources' in o || 'poster' in o || 'tracks' in o || 'thumbnails' in o) {
      const sources = toVideoSources(o.sources ?? o, disk)
      const poster = mediaUrlOf(o.poster, disk) ?? undefined
      const thumbnails = mediaUrlOf(o.thumbnails, disk) ?? undefined
      const tracks = toTracks(o.tracks, disk)
      const title = typeof o.title === 'string' ? o.title : undefined
      return { sources, poster, tracks, thumbnails, title }
    }
  }
  return { sources: toVideoSources(value, disk), tracks: [] }
}

/** WebVTT tracks, their addresses resolved. */
export function toTracks(value: unknown, disk?: MediaDisk | null): UidMediaTrack[] {
  const out: UidMediaTrack[] = []
  for (const item of asList(value)) {
    if (!item || typeof item !== 'object') continue
    const o = item as Record<string, unknown>
    const src = mediaUrlOf(o.src, disk)
    if (!src) continue
    const kind = o.kind === 'captions' || o.kind === 'chapters' ? o.kind : 'subtitles'
    out.push({
      src,
      kind,
      ...(typeof o.srclang === 'string' ? { srclang: o.srclang } : {}),
      ...(typeof o.label === 'string' ? { label: o.label } : {}),
      ...(o.default === true ? { default: true } : {}),
    })
  }
  return out
}

function finiteNumber(v: unknown): number | undefined {
  const n = typeof v === 'number' ? v : typeof v === 'string' && v.trim() !== '' ? Number(v) : NaN
  return Number.isFinite(n) ? n : undefined
}

/** Audio tracks from a value: one, or a playlist. */
export function toAudioTracks(value: unknown, disk?: MediaDisk | null): UidAudioTrack[] {
  const out: UidAudioTrack[] = []
  for (const item of asList(value)) {
    const src = mediaUrlOf(item, disk)
    if (!src) continue
    const o = item && typeof item === 'object' ? item as Record<string, unknown> : {}
    const type = sourceType(src, item)
    // An uploaded file has a name and no title: the name stands in.
    const title = typeof o.title === 'string' ? o.title : typeof o.name === 'string' ? o.name : undefined
    const peaks = Array.isArray(o.peaks) ? o.peaks.filter((p): p is number => typeof p === 'number') : undefined
    const duration = finiteNumber(o.duration)
    out.push({
      src,
      ...(type ? { type } : {}),
      ...(title ? { title } : {}),
      ...(typeof o.artist === 'string' ? { artist: o.artist } : {}),
      ...(duration !== undefined ? { duration } : {}),
      ...(peaks && peaks.length > 0 ? { peaks } : {}),
    })
  }
  return out
}

/** A CSS length: a number is pixels, a string goes as it is. */
export function cssSize(value: number | string | null | undefined): string | undefined {
  if (value === null || value === undefined || value === '') return undefined
  return typeof value === 'number' ? `${value}px` : value
}

/** The resume key: the given one, or the media's address when `true`. */
export function resumeKeyOf(resume: string | boolean | null | undefined, url: string | undefined): string | undefined {
  if (typeof resume === 'string' && resume !== '') return resume
  if (resume === true && url) return `admin-media:${url}`
  return undefined
}
