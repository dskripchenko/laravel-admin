import { describe, it, expect } from 'vitest'
import {
  createSlugger,
  renderMarkdown,
  resolveAgainst,
  splitMarkdown,
  type MarkdownHeading,
} from './markdown'

describe('renderMarkdown — documentation features', () => {
  it('gives headings unique anchor ids and reports them', () => {
    const headings: MarkdownHeading[] = []
    const html = renderMarkdown('## Getting started\n\n## Getting started\n\n### Поля `Input`', {
      slugger: createSlugger(),
      onHeading: (h) => headings.push(h),
    })
    expect(html).toContain('<h2 id="getting-started">Getting started</h2>')
    expect(html).toContain('<h2 id="getting-started-1">')
    expect(html).toContain('<h3 id="поля-input">')
    expect(headings.map((h) => [h.level, h.id, h.text])).toEqual([
      [2, 'getting-started', 'Getting started'],
      [2, 'getting-started-1', 'Getting started'],
      [3, 'поля-input', 'Поля Input'],
    ])
  })

  it('leaves headings without ids by default', () => {
    expect(renderMarkdown('# Title')).toBe('<h1>Title</h1>')
  })

  it('renders tables with alignment and inline markup', () => {
    const html = renderMarkdown('| Class | Use |\n|:---|---:|\n| `Rows` | **stack** |\n| A \\| B | x |')
    expect(html).toContain('<table>')
    expect(html).toContain('<th style="text-align:left">Class</th>')
    expect(html).toContain('<th style="text-align:right">Use</th>')
    expect(html).toContain('<td style="text-align:left"><code>Rows</code></td>')
    expect(html).toContain('<strong>stack</strong>')
    expect(html).toContain('A | B')
  })

  it('does not take a paragraph followed by a rule for a table', () => {
    const html = renderMarkdown('a | b\n---')
    expect(html).not.toContain('<table>')
  })

  it('renders callouts in both styles', () => {
    const bold = renderMarkdown('> **Note** Data resets every hour.')
    expect(bold).toContain('class="admin-markdown__callout admin-markdown__callout--note"')
    expect(bold).toContain('<strong>Note</strong>')

    const github = renderMarkdown('> [!WARNING]\n> Careful.')
    expect(github).toContain('admin-markdown__callout--warning')
    expect(github).toContain('admin-markdown__callout-title')
    expect(github).not.toContain('[!WARNING]')

    expect(renderMarkdown('> just a quote')).toBe('<blockquote><p>just a quote</p></blockquote>')
  })

  it('resolves relative links and images against their bases', () => {
    const html = renderMarkdown(
      '[Menu](concepts/menu.md#items) [Up](../intro.md) [Abs](/x) [Ext](https://e.com) [Top](#a) ![s](img/s.png)',
      {
        linkBase: '/admin/screens/docs/en/',
        stripMdExtension: true,
        imageBase: '/docs-assets/',
        internalLinksInPlace: true,
      },
    )
    expect(html).toContain('<a href="/admin/screens/docs/en/concepts/menu#items">Menu</a>')
    expect(html).toContain('<a href="/admin/screens/docs/intro">Up</a>')
    expect(html).toContain('<a href="/x">Abs</a>')
    expect(html).toContain('<a href="https://e.com" target="_blank" rel="noopener noreferrer">Ext</a>')
    expect(html).toContain('<a href="#a">Top</a>')
    expect(html).toContain('<img src="/docs-assets/img/s.png" alt="s" loading="lazy">')
  })

  it('resolves against an absolute base', () => {
    expect(resolveAgainst('b.md', 'https://site.test/docs/', true)).toBe('https://site.test/docs/b')
  })

  it('still escapes raw HTML and refuses script URLs', () => {
    const html = renderMarkdown('<script>x</script> [x](javascript:alert(1))', { linkBase: '/d/' })
    expect(html).toContain('&lt;script&gt;')
    expect(html).not.toContain('href="javascript')
  })
})

describe('splitMarkdown', () => {
  it('splits a document at its top-level fences', () => {
    const segments = splitMarkdown('# A\n\n```php\necho 1;\n```\n\ntext\n\n> ```\n> quoted\n> ```')
    expect(segments).toEqual([
      { kind: 'text', source: '# A\n' },
      { kind: 'code', code: 'echo 1;', language: 'php' },
      { kind: 'text', source: '\ntext\n\n> ```\n> quoted\n> ```' },
    ])
  })

  it('handles an unterminated fence and an empty source', () => {
    expect(splitMarkdown('```bash\nls')).toEqual([{ kind: 'code', code: 'ls', language: 'bash' }])
    expect(splitMarkdown('')).toEqual([])
  })
})

describe('renderMarkdown — lists', () => {
  it('nests a list indented under an item', () => {
    const html = renderMarkdown('- One\n  - One.a\n  - One.b\n- Two')
    expect(html).toBe('<ul><li>One<ul><li>One.a</li><li>One.b</li></ul></li><li>Two</li></ul>')
  })

  it('nests ordered in unordered, three levels deep', () => {
    const html = renderMarkdown('- A\n  1. A1\n     - A1x\n  2. A2\n- B')
    expect(html).toBe('<ul><li>A<ol><li>A1<ul><li>A1x</li></ul></li><li>A2</li></ol></li><li>B</li></ul>')
  })

  it('keeps one list across blank lines and a continuation paragraph', () => {
    const html = renderMarkdown('1. First\n\n   More about the first.\n\n2. Second')
    expect(html).toBe('<ol><li>First<p>More about the first.</p></li><li>Second</li></ol>')
  })

  it('joins a lazy continuation line to the item', () => {
    expect(renderMarkdown('- one\ntwo\n- three')).toBe('<ul><li>one\ntwo</li><li>three</li></ul>')
  })

  it('starts an ordered list at its first number', () => {
    expect(renderMarkdown('3. c\n4. d')).toBe('<ol start="3"><li>c</li><li>d</li></ol>')
  })

  it('ends the list at a heading or a paragraph after a blank line', () => {
    expect(renderMarkdown('- a\n\nText')).toBe('<ul><li>a</li></ul>\n<p>Text</p>')
    expect(renderMarkdown('- a\n# H')).toBe('<ul><li>a</li></ul>\n<h1>H</h1>')
  })

  it('keeps code nested in an item', () => {
    const html = renderMarkdown('- Run:\n\n  ```\n  php artisan\n  ```\n- Done')
    expect(html).toBe('<ul><li>Run:<pre><code>php artisan</code></pre></li><li>Done</li></ul>')
  })
})
