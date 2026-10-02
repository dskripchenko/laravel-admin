import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BrandLogo from './BrandLogo.vue'

describe('BrandLogo', () => {
  it('draws the Rounded block mark on its tile', () => {
    const wrapper = mount(BrandLogo, { props: { size: 40 } })
    const svg = wrapper.find('svg.ladmin-logo')
    expect(svg.attributes('width')).toBe('40')
    expect(svg.attributes('aria-label')).toBe('LAdmin')
    expect(svg.classes()).toContain('ladmin-logo--tile')
    expect(wrapper.find('.ladmin-logo__tile').exists()).toBe(true)
    expect(wrapper.find('.ladmin-logo__corners').attributes('d')).toBe('M5.5 11V5.5H11M18.5 13v5.5H13')
    expect(wrapper.find('.ladmin-logo__block').exists()).toBe(true)
  })

  it('leaves the tile out of the glyph variant', () => {
    const wrapper = mount(BrandLogo, { props: { variant: 'glyph' } })
    expect(wrapper.find('.ladmin-logo__tile').exists()).toBe(false)
    expect(wrapper.find('.ladmin-logo__block').exists()).toBe(true)
  })

  it('treats the former "color" variant as the tile', () => {
    const wrapper = mount(BrandLogo, { props: { variant: 'color' } })
    expect(wrapper.find('svg').classes()).toContain('ladmin-logo--tile')
  })
})
