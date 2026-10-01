import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatWidget from './StatWidget.vue'
import MarkdownWidget from './MarkdownWidget.vue'

describe('StatWidget', () => {
  it('renders every stat of the widget, each with its own label, tone and trend', () => {
    const wrapper = mount(StatWidget, {
      props: {
        title: 'Shop',
        stats: [
          { label: 'Orders', value: 12, color: 'success', change: { delta: 5, direction: 'up' } },
          { label: 'Refunds', value: 3, color: 'danger', change: { delta: 2, direction: 'down' } },
          { label: 'Visitors', value: 840, icon: 'users' },
        ],
      },
    })
    const stats = wrapper.findAll('.uid-stat')
    expect(stats).toHaveLength(3)
    expect(wrapper.find('.admin-stats-widget__title').text()).toBe('Shop')
    expect(stats.map((s) => s.find('.uid-stat__title').text())).toEqual(['Orders', 'Refunds', 'Visitors'])
    expect(stats[0]!.classes()).toContain('uid-stat--success')
    expect(stats[1]!.classes()).toContain('uid-stat--danger')
    expect(stats[0]!.text()).toContain('5')
  })

  it('keeps the single-stat look for one stat', () => {
    const wrapper = mount(StatWidget, {
      props: { title: 'Revenue', stats: [{ label: 'Sum', value: 100 }] },
    })
    expect(wrapper.find('.admin-stats-widget').exists()).toBe(false)
    expect(wrapper.findAll('.uid-stat')).toHaveLength(1)
    expect(wrapper.find('.uid-stat__title').text()).toBe('Revenue')
  })

  it('falls back to the scalar props without stats', () => {
    const wrapper = mount(StatWidget, { props: { title: 'Users', value: 7 } })
    expect(wrapper.findAll('.uid-stat')).toHaveLength(1)
    expect(wrapper.text()).toContain('7')
  })
})

describe('MarkdownWidget', () => {
  it('renders the markdown, escaping raw HTML', () => {
    const wrapper = mount(MarkdownWidget, {
      props: { content: '# Notes\n\n- one\n- **two**\n\n<script>alert(1)</script>' },
    })
    const body = wrapper.find('.admin-markdown')
    expect(body.find('h1').text()).toBe('Notes')
    expect(body.findAll('li')).toHaveLength(2)
    expect(body.find('strong').text()).toBe('two')
    expect(body.find('script').exists()).toBe(false)
    expect(body.text()).toContain('<script>')
  })
})
