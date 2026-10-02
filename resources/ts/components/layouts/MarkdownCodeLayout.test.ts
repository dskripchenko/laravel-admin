import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent } from 'vue'
import MarkdownLayout from './MarkdownLayout.vue'
import CodeLayout from './CodeLayout.vue'
import LayoutRenderer from '../render/LayoutRenderer.vue'
import { registerBuiltinComponents } from '../render/builtin'

const DOC = [
  '# Guide',
  '',
  'Intro with a [link](other.md).',
  '',
  '## Install',
  '',
  '```bash',
  'composer require x',
  '```',
  '',
  '### Details',
  '',
  '| a | b |',
  '|---|---|',
  '| 1 | 2 |',
].join('\n')

describe('MarkdownLayout', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders text, highlighted code and a table of contents', () => {
    const wrapper = mount(MarkdownLayout, { props: { markdown: DOC, toc: true, linkBase: '/admin/docs/' } })

    expect(wrapper.find('h1#guide').exists()).toBe(true)
    expect(wrapper.find('h2#install').exists()).toBe(true)
    expect(wrapper.find('table').exists()).toBe(true)
    expect(wrapper.find('a[href="/admin/docs/other"]').exists()).toBe(true)

    const code = wrapper.findComponent({ name: 'UidCode' })
    expect(code.exists()).toBe(true)
    expect(code.props('code')).toBe('composer require x')
    expect(code.props('language')).toBe('bash')

    const toc = wrapper.find('.admin-markdown-layout__toc')
    expect(toc.exists()).toBe(true)
    expect(toc.findAll('a').map((a) => a.attributes('href'))).toEqual(['#install', '#details'])
  })

  it('limits the table of contents to tocDepth and omits it by default', () => {
    const shallow = mount(MarkdownLayout, { props: { markdown: DOC, toc: true, tocDepth: 2 } })
    expect(shallow.findAll('.admin-markdown-layout__toc a')).toHaveLength(1)

    const none = mount(MarkdownLayout, { props: { markdown: DOC } })
    expect(none.find('.admin-markdown-layout__toc').exists()).toBe(false)
  })

  it('navigates in-panel links through the router', async () => {
    const router = createRouter({
      history: createMemoryHistory('/admin'),
      routes: [{ path: '/:p(.*)*', component: defineComponent({ template: '<div />' }) }],
    })
    await router.push('/')
    await router.isReady()
    const wrapper = mount(MarkdownLayout, {
      props: { markdown: '[Next](/admin/docs/next)' },
      global: { plugins: [router] },
      attachTo: document.body,
    })
    await wrapper.find('a').trigger('click', { button: 0 })
    await new Promise((r) => setTimeout(r, 0))
    expect(router.currentRoute.value.fullPath).toBe('/docs/next')
    wrapper.unmount()
  })

  it('is registered as the markdown layout', () => {
    registerBuiltinComponents()
    const wrapper = mount(LayoutRenderer, {
      props: { node: { kind: 'layout', type: 'markdown', markdown: '**bold**', items: [], props: {}, children: [] } },
    })
    expect(wrapper.find('strong').text()).toBe('bold')
  })
})

describe('CodeLayout', () => {
  it('renders a captioned, copyable block', () => {
    const wrapper = mount(CodeLayout, {
      props: { code: '<?php echo 1;', language: 'php', title: 'index.php', lineNumbers: true },
    })
    expect(wrapper.find('figcaption').text()).toBe('index.php')
    const code = wrapper.findComponent({ name: 'UidCode' })
    expect(code.props('code')).toBe('<?php echo 1;')
    expect(code.props('language')).toBe('php')
    expect(code.props('lineNumbers')).toBe(true)
    expect(code.props('copy')).toBe(true)
  })

  it('is registered as the code layout', () => {
    registerBuiltinComponents()
    const wrapper = mount(LayoutRenderer, {
      props: { node: { kind: 'layout', type: 'code', code: 'SELECT 1', language: 'sql', items: [] } },
    })
    expect(wrapper.findComponent({ name: 'UidCode' }).props('code')).toBe('SELECT 1')
  })
})
