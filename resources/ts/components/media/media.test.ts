/**
 * The panel's side of the kit's players: how values (URLs, storage paths, uploads, state
 * keys, block sources) become UidVideo / UidAudio props, and where the players are drawn —
 * the layouts, the entries, table cells, the file field and ```video / ```audio blocks.
 * The players themselves are the kit's; here they stand in as stubs, except in the blocks.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createApp, defineComponent, h, nextTick } from 'vue'
import { UidAudio, UidModal, UidVideo } from '@dskripchenko/ui'
import {
  isAbsoluteUrl,
  mediaKind,
  mediaUrlOf,
  resolveMediaUrl,
  resumeKeyOf,
  toAudioTracks,
  toVideoConfig,
  toVideoSources,
  type MediaDisk,
} from './mediaSource'
import { MediaBlockError, parseMediaBlock } from './mediaBlock'
import VideoLayout from '../layouts/VideoLayout.vue'
import AudioLayout from '../layouts/AudioLayout.vue'
import VideoEntry from '../infolist/VideoEntry.vue'
import AudioEntry from '../infolist/AudioEntry.vue'
import FileEntry from '../infolist/FileEntry.vue'
import AdminTableCell from '../resource/AdminTableCell.vue'
import FileField from '../fields/FileField.vue'
import { provideFormState, type FormStateContext } from '../render/formState'
import { provideRecord } from '../infolist/recordContext'
import { clearRegistry, getLayout } from '../render/registry'
import { registerBuiltinComponents } from '../render/builtin'
import { clearInfolistRegistry, getInfolistEntry } from '../infolist/registry'
import { registerBuiltinInfolistEntries } from '../infolist/builtin'
import {
  audioBlockProps,
  audioRenderer,
  builtinMarkdownRenderers,
  provideBuiltinMarkdownRenderers,
  videoBlockProps,
  videoRenderer,
} from '../markdown/blockRenderers'

const SERVE: MediaDisk = { prefix: '/api/admin/uploads/serve?disk=local&path=', suffix: '', encode: 'component' }
const CDN: MediaDisk = { prefix: 'https://cdn.test/storage/', suffix: '', encode: 'path' }

/** The kit's players as stubs that keep their props. */
const VideoStub = defineComponent({
  name: 'UidVideo',
  inheritAttrs: false,
  props: ['src', 'sources', 'poster', 'tracks', 'thumbnails', 'title', 'autoplay', 'muted', 'loop', 'controls',
    'preload', 'crossorigin', 'exclusive', 'hotkeys', 'rates', 'resumeKey', 'aspectRatio', 'autoHide', 'hlsOptions'],
  setup(_, { expose }) {
    expose({ play: () => Promise.resolve() })
    return () => h('div', { class: 'video-stub' })
  },
})
const AudioStub = defineComponent({
  name: 'UidAudio',
  inheritAttrs: false,
  props: ['src', 'type', 'title', 'artist', 'tracks', 'index', 'variant', 'waveform', 'peaks', 'loop', 'autoAdvance',
    'preload', 'crossorigin', 'exclusive', 'hotkeys', 'rates', 'resumeKey', 'showVolume', 'showRate', 'showPlaylist',
    'hlsOptions'],
  setup: () => () => h('div', { class: 'audio-stub' }),
})
const stubs = { UidVideo: VideoStub, UidAudio: AudioStub }

describe('media values', () => {
  it('resolves storage paths through a disk and leaves addresses alone', () => {
    expect(isAbsoluteUrl('https://a.test/x.mp4')).toBe(true)
    expect(isAbsoluteUrl('/media/x.mp4')).toBe(true)
    expect(isAbsoluteUrl('blob:abc')).toBe(true)
    expect(isAbsoluteUrl('videos/x.mp4')).toBe(false)
    expect(resolveMediaUrl('calls/a b.ogg', SERVE)).toBe('/api/admin/uploads/serve?disk=local&path=calls%2Fa%20b.ogg')
    expect(resolveMediaUrl('calls/a b.ogg', CDN)).toBe('https://cdn.test/storage/calls/a%20b.ogg')
    expect(resolveMediaUrl('/media/x.mp4', CDN)).toBe('/media/x.mp4')
    expect(resolveMediaUrl('videos/x.mp4')).toBe('videos/x.mp4')
  })

  it('reads the address of an upload, an attachment or a string', () => {
    expect(mediaUrlOf({ disk: 'local', path: 'uploads/a.mp3', url: '/serve?a', mime: 'audio/mpeg' })).toBe('/serve?a')
    expect(mediaUrlOf({ path: 'uploads/a.mp3' }, CDN)).toBe('https://cdn.test/storage/uploads/a.mp3')
    expect(mediaUrlOf('  ')).toBeNull()
    expect(mediaUrlOf(42)).toBeNull()
  })

  it('tells video, audio and images by the MIME type, else by the extension', () => {
    expect(mediaKind('audio/ogg')).toBe('audio')
    expect(mediaKind('video/quicktime')).toBe('video')
    expect(mediaKind('application/vnd.apple.mpegurl')).toBe('video')
    expect(mediaKind(null, '/a/b.M4A?x=1')).toBe('audio')
    expect(mediaKind('', '/live/master.m3u8')).toBe('video')
    expect(mediaKind(null, '/a.webp')).toBe('image')
    expect(mediaKind('application/pdf', '/a.pdf')).toBeNull()
  })

  it('gives the kit only a stated type or HLS, never a detected one', () => {
    expect(toVideoSources([
      { src: '/a-720.mp4', label: '720p' },
      { url: '/clip.mov', mime: 'video/quicktime' },
      '/live/master.m3u8',
      { src: '/stream', type: 'application/x-mpegURL' },
    ])).toEqual([
      { src: '/a-720.mp4', label: '720p' },
      { src: '/clip.mov' },
      { src: '/live/master.m3u8', type: 'application/x-mpegURL' },
      { src: '/stream', type: 'application/x-mpegURL' },
    ])
  })

  it('reads a whole video description from a value', () => {
    expect(toVideoConfig({
      src: 'clips/a.mp4',
      poster: 'clips/a.jpg',
      tracks: [{ src: 'clips/a.vtt', srclang: 'en', label: 'English', default: true }, { src: 'clips/ch.vtt', kind: 'chapters' }],
      thumbnails: 'clips/thumbs.vtt',
      title: 'A',
    }, CDN)).toEqual({
      sources: [{ src: 'https://cdn.test/storage/clips/a.mp4' }],
      poster: 'https://cdn.test/storage/clips/a.jpg',
      tracks: [
        { src: 'https://cdn.test/storage/clips/a.vtt', kind: 'subtitles', srclang: 'en', label: 'English', default: true },
        { src: 'https://cdn.test/storage/clips/ch.vtt', kind: 'chapters' },
      ],
      thumbnails: 'https://cdn.test/storage/clips/thumbs.vtt',
      title: 'A',
    })
  })

  it('turns uploads and playlists into audio tracks', () => {
    expect(toAudioTracks([
      { url: '/serve?a', name: 'call.ogg', mime: 'audio/ogg' },
      { src: '/b.mp3', title: 'B', artist: 'X', duration: '61.5', peaks: [0.1, 'x', 0.3] },
      null,
    ])).toEqual([
      { src: '/serve?a', title: 'call.ogg' },
      { src: '/b.mp3', title: 'B', artist: 'X', duration: 61.5, peaks: [0.1, 0.3] },
    ])
  })

  it('keys the resume position by the address when asked to', () => {
    expect(resumeKeyOf('intro', '/a.mp4')).toBe('intro')
    expect(resumeKeyOf(true, '/a.mp4')).toBe('admin-media:/a.mp4')
    expect(resumeKeyOf(null, '/a.mp4')).toBeUndefined()
  })
})

describe('```video / ```audio block sources', () => {
  it('takes a bare address, or one per line', () => {
    expect(parseMediaBlock('https://a.test/x.mp4')).toEqual({ src: 'https://a.test/x.mp4' })
    expect(parseMediaBlock('/a.mp3\n/b.mp3\n')).toEqual([{ src: '/a.mp3' }, { src: '/b.mp3' }])
  })

  it('takes JSON', () => {
    expect(parseMediaBlock('{"src": "/a.mp4", "loop": true}')).toEqual({ src: '/a.mp4', loop: true })
    expect(() => parseMediaBlock('{"src": ')).toThrow(MediaBlockError)
  })

  it('takes the YAML subset: pairs, lists of maps, flow lists, quoting, comments', () => {
    expect(parseMediaBlock([
      '# the intro',
      'src: /media/intro.mp4',
      'title: "Intro: part 1"',
      "artist: 'O''Neil'",
      'autoplay: false',
      'aspectRatio: 4:3',
      'peaks: [0.1, 0.5, 1]',
      'tracks:',
      '  - src: /media/intro.en.vtt   # English',
      '    srclang: en',
      '    default: true',
      '  - src: /media/intro.chapters.vtt',
      '    kind: chapters',
    ].join('\n'))).toEqual({
      src: '/media/intro.mp4',
      title: 'Intro: part 1',
      artist: "O'Neil",
      autoplay: false,
      aspectRatio: '4:3',
      peaks: [0.1, 0.5, 1],
      tracks: [
        { src: '/media/intro.en.vtt', srclang: 'en', default: true },
        { src: '/media/intro.chapters.vtt', kind: 'chapters' },
      ],
    })
    expect(parseMediaBlock('- src: /a.mp3\n  title: A\n- /b.mp3')).toEqual([{ src: '/a.mp3', title: 'A' }, '/b.mp3'])
  })

  it('reports what it cannot read with the line', () => {
    expect(() => parseMediaBlock('')).toThrow('empty')
    expect(() => parseMediaBlock('src: /a.mp4\n  oops')).toThrow(/line 2/)
    expect(() => parseMediaBlock('src /a.mp4 here')).toThrow(/line 1/)
  })

  it('builds the players\' props', () => {
    expect(videoBlockProps('src: /live/master.m3u8\naspectRatio: 4:3\nmaxWidth: 480\nautoplay: true')).toMatchObject({
      sources: [{ src: '/live/master.m3u8', type: 'application/x-mpegURL' }],
      aspectRatio: '4 / 3',
      maxWidth: '480px',
      autoplay: true,
      muted: true,
    })
    expect(videoBlockProps('sources:\n  - src: /a-720.mp4\n    label: 720p\n  - src: /a-360.mp4\n    label: 360p').sources)
      .toEqual([{ src: '/a-720.mp4', label: '720p' }, { src: '/a-360.mp4', label: '360p' }])
    expect(audioBlockProps('src: /a.mp3\ntitle: A\nwaveform: true\nmini: true')).toMatchObject({
      src: '/a.mp3', title: 'A', waveform: true, variant: 'mini',
    })
    expect(audioBlockProps('/a.mp3\n/b.mp3').tracks).toEqual([{ src: '/a.mp3' }, { src: '/b.mp3' }])
    expect(() => videoBlockProps('title: no source')).toThrow(MediaBlockError)
  })
})

describe('block renderers', () => {
  let host: HTMLElement
  beforeEach(() => {
    // jsdom has media elements without playback.
    vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(() => undefined)
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => undefined)
    host = document.createElement('div')
    document.body.appendChild(host)
  })
  afterEach(() => {
    host.remove()
    vi.restoreAllMocks()
  })

  it('are registered app-wide with the others', () => {
    expect(builtinMarkdownRenderers.video).toBe(videoRenderer)
    expect(builtinMarkdownRenderers.audio).toBe(audioRenderer)
  })

  it('report a broken source instead of mounting', async () => {
    expect(await videoRenderer('poster: /a.jpg', { language: 'video', index: 0 })).toEqual({
      error: 'no video source: give `src` or `sources`',
    })
  })

  it('mount the kit\'s players in the panel\'s context, update them in place and clean up', async () => {
    const app = createApp({ render: () => null })
    provideBuiltinMarkdownRenderers(app)
    const ctx = { code: '', language: 'video', editable: false, setCode: () => undefined }

    const video = await videoRenderer('src: /a.mp4\nmaxWidth: 480', { language: 'video', index: 0 })
    const handle = video.mount!(host, ctx) as { update: (c: string) => void; destroy: () => void }
    await nextTick()
    expect(host.classList.contains('admin-md-media--video')).toBe(true)
    expect(host.style.maxWidth).toBe('480px')
    expect(host.querySelector('video')).not.toBeNull()

    // The kit's strings follow the panel's language, though the block is outside the shell.
    expect(host.querySelector('.uid-video')!.getAttribute('aria-label')).toBe('Воспроизвести')

    handle.update('src: /b.mp4')
    await nextTick()
    expect(host.style.maxWidth).toBe('')
    // A half-typed source keeps the player.
    handle.update('src: ')
    await nextTick()
    expect(host.querySelector('video')).not.toBeNull()
    handle.destroy()
    expect(host.innerHTML).toBe('')

    const audio = await audioRenderer('/a.mp3\n/b.mp3', { language: 'audio', index: 1 })
    const audioHandle = audio.mount!(host, { ...ctx, language: 'audio' }) as { destroy: () => void }
    await nextTick()
    expect(host.querySelector('audio')).not.toBeNull()
    audioHandle.destroy()
  })
})

describe('registration', () => {
  it('registers the layouts and the entries', () => {
    clearRegistry()
    registerBuiltinComponents()
    clearInfolistRegistry()
    registerBuiltinInfolistEntries()
    expect(getLayout('video')).toBe(VideoLayout)
    expect(getLayout('audio')).toBe(AudioLayout)
    expect(getInfolistEntry('video')).toBe(VideoEntry)
    expect(getInfolistEntry('audio')).toBe(AudioEntry)
    expect(getInfolistEntry('file')).toBe(FileEntry)
  })
})

let lastForm: FormStateContext | null = null

/** Mounts a component under a form state and a record; the form is left in `lastForm`. */
function withState(component: unknown, props: Record<string, unknown>, state: Record<string, unknown> = {}, record: Record<string, unknown> | null = null) {
  const Wrap = defineComponent({
    setup() {
      lastForm = provideFormState(state)
      if (record) provideRecord(record)
      return () => h(component as never, props)
    },
  })
  return mount(Wrap, { global: { stubs } })
}

describe('Layout::video', () => {
  it('passes the resolved sources and options to the player', () => {
    const w = withState(VideoLayout, {
      sources: [{ src: '/a.mp4' }],
      tracks: [{ src: '/a.vtt', kind: 'subtitles', srclang: 'en' }],
      poster: '/a.jpg',
      title: 'Intro',
      autoplay: true,
      resumeKey: true,
      aspectRatio: '4 / 3',
      maxWidth: '720px',
      hlsOptions: { preferNative: false },
    })
    const p = w.findComponent(VideoStub).props()
    expect(p.sources).toEqual([{ src: '/a.mp4' }])
    expect(p.poster).toBe('/a.jpg')
    expect(p.title).toBe('Intro')
    expect(p.muted).toBe(true)
    expect(p.resumeKey).toBe('admin-media:/a.mp4')
    expect(p.hlsOptions).toEqual({ preferNative: false })
    expect(w.find('figcaption').text()).toBe('Intro')
    expect(w.find('figure').attributes('style')).toContain('max-width: 720px')
  })

  it('reads the video from the state through the disk, and follows it', async () => {
    const state = { clip: 'clips/a.mp4' }
    const w = withState(VideoLayout, { source: 'clip', disk: CDN, poster: '/p.jpg' }, state)
    expect(w.findComponent(VideoStub).props('sources')).toEqual([{ src: 'https://cdn.test/storage/clips/a.mp4' }])
    expect(w.findComponent(VideoStub).props('poster')).toBe('/p.jpg')
    lastForm!.setField('clip', null)
    await nextTick()
    expect(w.findComponent(VideoStub).exists()).toBe(false)
    expect(w.text()).toContain('Нет видео')
  })
})

describe('Layout::audio', () => {
  it('plays one track with its title, waveform and peaks', () => {
    const w = withState(AudioLayout, {
      tracks: [{ src: '/a.mp3', title: 'From track' }],
      title: 'Episode',
      waveform: true,
      peaks: [0.2, 0.4],
      variant: 'mini',
      showRate: false,
    })
    const p = w.findComponent(AudioStub).props()
    expect(p.src).toBe('/a.mp3')
    expect(p.title).toBe('Episode')
    expect(p.peaks).toEqual([0.2, 0.4])
    expect(p.variant).toBe('mini')
    expect(p.showRate).toBe(false)
    expect(p.tracks).toBeUndefined()
  })

  it('plays a playlist from the state', () => {
    const w = withState(AudioLayout, { source: 'episodes', disk: SERVE }, {
      episodes: ['a.mp3', { url: '/b.mp3', name: 'b.mp3' }],
    })
    expect(w.findComponent(AudioStub).props('tracks')).toEqual([
      { src: '/api/admin/uploads/serve?disk=local&path=a.mp3' },
      { src: '/b.mp3', title: 'b.mp3' },
    ])
  })

  it('says so when there is nothing to play', () => {
    expect(withState(AudioLayout, { source: 'none' }).text()).toContain('Нет аудио')
  })
})

describe('entries', () => {
  it('video: the record\'s file, the poster from another attribute', () => {
    const w = withState(VideoEntry, { name: 'clip', label: 'Clip', disk: CDN, posterFrom: 'cover', resume: true }, {}, {
      clip: 'clips/a.mp4', cover: 'covers/a.jpg',
    })
    const p = w.findComponent(VideoStub).props()
    expect(p.sources).toEqual([{ src: 'https://cdn.test/storage/clips/a.mp4' }])
    expect(p.poster).toBe('https://cdn.test/storage/covers/a.jpg')
    expect(p.title).toBe('Clip')
    expect(p.resumeKey).toBe('admin-media:https://cdn.test/storage/clips/a.mp4')
    expect(withState(VideoEntry, { name: 'clip' }, {}, {}).text()).toBe('—')
  })

  it('audio: the mini player, the full one, a playlist', () => {
    const mini = withState(AudioEntry, { name: 'call' }, {}, { call: { url: '/serve?x', name: 'call.ogg' } })
    expect(mini.findComponent(AudioStub).props()).toMatchObject({ src: '/serve?x', variant: 'mini', title: undefined })
    expect(mini.find('.admin-infolist-media--mini').exists()).toBe(true)

    const full = withState(AudioEntry, { name: 'call', variant: 'default', peaksFrom: 'peaks' }, {}, {
      call: { url: '/serve?x', name: 'call.ogg' }, peaks: [0.5],
    })
    expect(full.findComponent(AudioStub).props()).toMatchObject({ title: 'call.ogg', peaks: [0.5] })

    const list = withState(AudioEntry, { name: 'calls' }, {}, { calls: ['/a.mp3', '/b.mp3'] })
    expect(list.findComponent(AudioStub).props('tracks')).toHaveLength(2)
  })

  it('file: the link, and a player for audio and video', () => {
    const w = withState(FileEntry, { name: 'f' }, {}, {
      f: [
        { url: '/serve?a', name: 'talk.mp3', mime: 'audio/mpeg' },
        { url: '/serve?b', name: 'clip.mp4', mime: 'video/mp4' },
        { url: '/serve?c', name: 'doc.pdf', mime: 'application/pdf' },
      ],
    })
    expect(w.findAll('a').map((a) => a.text())).toEqual(['talk.mp3', 'clip.mp4', 'doc.pdf'])
    expect(w.findAllComponents(AudioStub)).toHaveLength(1)
    expect(w.findAllComponents(VideoStub)).toHaveLength(1)
  })
})

describe('table cells', () => {
  it('audio: a mini player that does not open the row', async () => {
    let rowClicks = 0
    const w = mount({
      render: () => h('div', { onClick: () => rowClicks++ }, [
        h(AdminTableCell, { value: 'calls/1.ogg', preset: 'audio', meta: { disk: SERVE, waveform: true } }),
      ]),
    }, { global: { stubs } })
    const p = w.findComponent(AudioStub).props()
    expect(p).toMatchObject({ src: '/api/admin/uploads/serve?disk=local&path=calls%2F1.ogg', variant: 'mini', waveform: true })
    await w.find('.admin-cell-media').trigger('click')
    expect(rowClicks).toBe(0)
  })

  it('audio: an empty value is a dash', () => {
    const w = mount(AdminTableCell, { props: { value: null, preset: 'audio' }, global: { stubs } })
    expect(w.text()).toBe('—')
  })

  it('video: the poster with a play button, the player in a modal on click', async () => {
    let rowClicks = 0
    const w = mount({
      render: () => h('div', { onClick: () => rowClicks++ }, [
        h(AdminTableCell, {
          value: '/clips/a.mp4',
          preset: 'video',
          meta: { poster: 'cover', width: 80 },
          row: { cover: '/covers/a.jpg' },
        }),
      ]),
    }, { global: { stubs }, attachTo: document.body })
    const thumb = w.find('button.admin-cell-video__thumb')
    expect(thumb.attributes('style')).toContain('width: 80px')
    expect(thumb.attributes('style')).toContain('height: 45px')
    expect(w.find('img.admin-cell-video__img').attributes('src')).toBe('/covers/a.jpg')
    expect(w.findComponent(VideoStub).exists()).toBe(false)
    await thumb.trigger('click')
    await flushPromises()
    expect(rowClicks).toBe(0)
    expect(w.findComponent(UidModal).props('modelValue')).toBe(true)
    expect(w.findComponent(VideoStub).props('sources')).toEqual([{ src: '/clips/a.mp4' }])
    w.unmount()
  })
})

describe('the file field', () => {
  it('shows a player under an uploaded audio or video file', async () => {
    const state: Record<string, unknown> = {
      rec: { disk: 'local', path: 'uploads/a.ogg', url: '/serve?a', name: 'a.ogg', size: 10, mime: 'audio/ogg' },
    }
    const w = withState(FileField, { name: 'rec', label: 'Recording' }, state)
    expect(w.findComponent(AudioStub).props('src')).toBe('/serve?a')
    expect(w.text()).toContain('a.ogg')

    const v = withState(FileField, { name: 'clip' }, {
      clip: { disk: 'local', path: 'uploads/a.mp4', url: '/serve?v', name: 'a.mp4', size: 10, mime: 'video/mp4' },
    })
    expect(v.findComponent(VideoStub).props('sources')).toEqual([{ src: '/serve?v' }])

    const doc = withState(FileField, { name: 'doc' }, {
      doc: { disk: 'local', path: 'uploads/a.pdf', url: '/serve?d', name: 'a.pdf', size: 10, mime: 'application/pdf' },
    })
    expect(doc.findComponent(AudioStub).exists()).toBe(false)
    expect(doc.findComponent(VideoStub).exists()).toBe(false)
  })
})

// The real players are only checked to accept what the panel passes, not re-tested.
describe('the kit\'s players', () => {
  it('are the ones the panel imports', () => {
    expect(UidVideo).toBeTruthy()
    expect(UidAudio).toBeTruthy()
  })
})
