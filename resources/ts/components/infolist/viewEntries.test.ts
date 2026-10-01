import { describe, it, expect, beforeEach } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { UidCode, UidModal, UidRating } from '@dskripchenko/ui'
import InfolistRenderer, { type InfolistNode } from './InfolistRenderer.vue'
import { clearInfolistRegistry } from './registry'
import { registerBuiltinInfolistEntries } from './builtin'
import { provideRecord } from './recordContext'

const Wrap = defineComponent({
  props: { record: { type: Object, default: () => ({}) }, node: { type: Object, required: true } },
  setup(props) {
    provideRecord(props.record as Record<string, unknown>)
    return () => h(InfolistRenderer, { node: props.node as unknown as InfolistNode })
  },
})

const show = (record: Record<string, unknown>, node: Record<string, unknown>) =>
  mount(Wrap, { props: { record, node }, global: { stubs: { RouterLink: RouterLinkStub } } })

/** A FieldEntry node, the way Resource::infolist() serializes a field. */
const fieldNode = (field: Record<string, unknown>, label = 'Label') => ({
  kind: 'entry',
  type: 'field',
  name: field.name,
  label,
  attributes: { field },
})

describe('infolist entries', () => {
  beforeEach(() => {
    clearInfolistRegistry()
    registerBuiltinInfolistEntries()
  })

  it('color: a swatch and the value, converted to the requested format', () => {
    const w = show({ tint: '#ff0000' }, { type: 'color', name: 'tint', label: 'Tint', attributes: { format: 'rgb' } })
    expect(w.find('.admin-infolist-color__swatch').attributes('style')).toContain('rgb(255, 0, 0)')
    expect(w.find('.admin-infolist-color__value').text()).toBe('rgb(255, 0, 0)')

    const bare = show({ tint: '#00f' }, { type: 'color', name: 'tint', attributes: { showValue: false } })
    expect(bare.find('.admin-infolist-color__value').exists()).toBe(false)
  })

  it('image: thumbnails sized by the entry, zoom in a modal', async () => {
    const w = show(
      { photo: { url: '/a.png' } },
      { type: 'image', name: 'photo', label: 'Photo', attributes: { width: 64, height: 48, rounded: true, clickToZoom: true } },
    )
    const img = w.find('img')
    expect(img.attributes('src')).toBe('/a.png')
    expect(img.attributes('style')).toContain('width: 64px')
    expect(img.classes()).toContain('admin-infolist-image__img--rounded')
    await w.find('button.admin-infolist-image__item').trigger('click')
    await nextTick()
    expect(w.findComponent(UidModal).props('modelValue')).toBe(true)

    expect(show({}, { type: 'image', name: 'photo' }).text()).toBe('—')
  })

  it('map: coordinates from the columns and an OpenStreetMap link', () => {
    const w = show(
      { latitude: 55.7558, longitude: 37.6173 },
      { type: 'map', name: 'location', attributes: { latColumn: 'latitude', lngColumn: 'longitude', zoom: 12 } },
    )
    expect(w.text()).toContain('55.755800, 37.617300')
    const a = w.find('a')
    expect(a.attributes('href')).toBe('https://www.openstreetmap.org/?mlat=55.7558&mlon=37.6173#map=12/55.7558/37.6173')
    expect(a.attributes('target')).toBe('_blank')

    expect(show({ location: '10.5, 20.25' }, { type: 'map', name: 'location' }).text()).toContain('10.500000, 20.250000')
    expect(show({ location: { lat: 999, lng: 0 } }, { type: 'map', name: 'location' }).find('a').exists()).toBe(false)
  })

  it('relation: links the loaded relation to its view page', () => {
    const w = show(
      { author_id: 5, main_author: { id: 5, title: 'Jane' } },
      { type: 'relation', name: 'author_id', attributes: { relation: 'mainAuthor', displayColumn: 'title', linkTo: 'users' } },
    )
    const link = w.findComponent(RouterLinkStub)
    expect(link.props('to')).toBe('/r/users/5')
    expect(link.text()).toBe('Jane')
  })

  it('relation: plain text without linkTo, every item of a list', () => {
    const w = show({ tags: [{ id: 1, name: 'a' }, { id: 2, name: 'b' }] }, { type: 'relation', name: 'tags', attributes: { relation: 'tags' } })
    expect(w.findComponent(RouterLinkStub).exists()).toBe(false)
    expect(w.findAll('.admin-infolist-relation__item').map((i) => i.text())).toEqual(['a', 'b'])
    expect(show({}, { type: 'relation', name: 'x' }).text()).toBe('—')
  })

  it('field: markdown is rendered, raw HTML stays text', () => {
    const w = show({ body: '**bold** <b>x</b>' }, fieldNode({ type: 'markdown', name: 'body', attributes: {} }))
    expect(w.find('.admin-markdown').html()).toContain('<strong>bold</strong> &lt;b&gt;x&lt;/b&gt;')
  })

  it('field: code goes to UidCode with the language', () => {
    const w = show({ src: 'echo 1;' }, fieldNode({ type: 'code', name: 'src', attributes: { language: 'php' } }))
    expect(w.findComponent(UidCode).props()).toMatchObject({ code: 'echo 1;', language: 'php' })
  })

  it('field: rating is read-only stars out of count', () => {
    const w = show({ stars: 4 }, fieldNode({ type: 'rating', name: 'stars', attributes: { count: 10 } }))
    expect(w.findComponent(UidRating).props()).toMatchObject({ modelValue: 4, max: 10, readonly: true })
  })

  it('field: radio shows the option label', () => {
    const w = show({ kind: 'b' }, fieldNode({ type: 'radio', name: 'kind', attributes: { options: [{ value: 'a', label: 'A' }, { value: 'b', label: 'Bee' }] } }))
    expect(w.find('.admin-infolist-text').text()).toBe('Bee')
  })

  it('field: tree select and cascader show the label path', () => {
    const tree = [{ value: 1, label: 'Russia', children: [{ value: 2, label: 'Moscow' }] }]
    expect(show({ c: 2 }, fieldNode({ type: 'tree_select', name: 'c', attributes: { tree } })).text()).toContain('Russia / Moscow')
    expect(show({ c: [1, 2] }, fieldNode({ type: 'cascader', name: 'c', attributes: { tree, separator: ' → ' } })).text()).toContain('Russia → Moscow')
  })

  it('field: date range and morph switcher', () => {
    expect(show({ p: { from: '2026-03-01', to: null } }, fieldNode({ type: 'date_range', name: 'p', attributes: {} })).text()).toContain('01.03.2026 — …')
    const morphTypes = { post: { options: [{ value: 7, label: 'Hello' }] } }
    expect(show({ s: { type: 'post', id: 7 } }, fieldNode({ type: 'morph_switcher', name: 's', attributes: { morphTypes } })).text()).toContain('post: Hello')
    expect(show({ s: { type: 'user', id: 3 } }, fieldNode({ type: 'morph_switcher', name: 's', attributes: { morphTypes } })).text()).toContain('user #3')
  })

  it('field: group shows its children from the nested object, without hidden ones', () => {
    const w = show(
      { address: { city: 'Moscow', token: 'x' } },
      fieldNode({
        type: 'group',
        name: 'address',
        attributes: {
          fields: [
            { type: 'input', name: 'city', label: 'City', attributes: {} },
            { type: 'hidden', name: 'token', label: 'Token', attributes: {} },
          ],
        },
      }, 'Address'),
    )
    expect(w.text()).toContain('City')
    expect(w.text()).toContain('Moscow')
    expect(w.text()).not.toContain('Token')
  })

  it('field: an unknown type falls back to text', () => {
    expect(show({ x: 'plain' }, fieldNode({ type: 'nope', name: 'x' })).text()).toContain('plain')
  })

  it('hidden draws nothing', () => {
    const w = show({ token: 'secret' }, { type: 'hidden', name: 'token' })
    expect(w.text()).not.toContain('secret')
  })
})
