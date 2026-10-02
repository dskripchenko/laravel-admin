import { describe, expect, it } from 'vitest'
import { renderMarkdown } from './markdown'
import { formatColor, parseColor, toHex } from './color'
import { cascaderLabels, findLabelPath, normalizeTree, optionLabel } from './tree'
import { readDateRange } from './dateRange'
import { singleSelectValue } from './selectValue'

describe('renderMarkdown', () => {
  it('renders headings, emphasis, code, lists and quotes', () => {
    const html = renderMarkdown('# Title\n\nSome **bold** and _it_ with `x<y`\n\n- one\n- two\n\n1. a\n2. b\n\n> quoted *text*')
    expect(html).toContain('<h1>Title</h1>')
    expect(html).toContain('<strong>bold</strong>')
    expect(html).toContain('<em>it</em>')
    expect(html).toContain('<code>x&lt;y</code>')
    expect(html).toContain('<ul><li>one</li><li>two</li></ul>')
    expect(html).toContain('<ol><li>a</li><li>b</li></ol>')
    expect(html).toContain('<blockquote><p>quoted <em>text</em></p></blockquote>')
  })

  it('renders fenced code verbatim', () => {
    const html = renderMarkdown('```php\n$a = **1**;\n```')
    expect(html).toBe('<pre><code class="language-php">$a = **1**;</code></pre>')
  })

  it('leaves snake_case words alone', () => {
    expect(renderMarkdown('use some_var_name here')).toBe('<p>use some_var_name here</p>')
  })

  it('escapes raw HTML and refuses script URLs', () => {
    const html = renderMarkdown('<img src=x onerror=alert(1)> [x](javascript:alert(1)) [ok](https://e.com/?a=1&b=2)')
    expect(html).not.toContain('<img')
    expect(html).toContain('&lt;img')
    expect(html).not.toContain('href="javascript')
    expect(html).toContain('<a href="https://e.com/?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">ok</a>')
  })

  it('returns an empty string for an empty source', () => {
    expect(renderMarkdown('')).toBe('')
    expect(renderMarkdown(null)).toBe('')
  })
})

describe('colour conversions', () => {
  it('parses hex, short hex, rgb and hsl', () => {
    expect(parseColor('#ff8000')).toEqual({ r: 255, g: 128, b: 0, a: 1 })
    expect(parseColor('#f80')).toEqual({ r: 255, g: 136, b: 0, a: 1 })
    expect(parseColor('rgba(10, 20, 30, 0.5)')).toEqual({ r: 10, g: 20, b: 30, a: 0.5 })
    expect(toHex('hsl(0, 100%, 50%)')).toBe('#ff0000')
    expect(parseColor('not a colour')).toBeNull()
  })

  it('formats into each storage format', () => {
    const c = { r: 255, g: 0, b: 0, a: 1 }
    expect(formatColor(c, 'hex')).toBe('#ff0000')
    expect(formatColor(c, 'rgb')).toBe('rgb(255, 0, 0)')
    expect(formatColor(c, 'hsl')).toBe('hsl(0, 100%, 50%)')
    expect(formatColor({ ...c, a: 0.5 }, 'rgb')).toBe('rgba(255, 0, 0, 0.5)')
    expect(formatColor({ ...c, a: 0.5 }, 'hex')).toBe('#ff000080')
  })
})

describe('option trees', () => {
  const tree = normalizeTree([
    { value: 1, label: 'Russia', children: [{ value: 2, label: 'Moscow' }] },
    { value: 'x', label: 'Other' },
    { label: 'no value — dropped' },
  ])

  it('normalizes and looks up label paths', () => {
    expect(tree).toHaveLength(2)
    expect(findLabelPath(tree, '2')).toEqual(['Russia', 'Moscow'])
    expect(findLabelPath(tree, 99)).toBeNull()
    expect(cascaderLabels(tree, [1, 2])).toEqual(['Russia', 'Moscow'])
    expect(cascaderLabels(tree, [1, 7])).toEqual(['Russia', '7'])
  })

  it('finds an option label in a list or a map', () => {
    expect(optionLabel([{ value: 'a', label: 'A' }], 'a')).toBe('A')
    expect(optionLabel({ a: 'A' }, 'a')).toBe('A')
    expect(optionLabel([], 'z')).toBe('z')
  })
})

describe('readDateRange', () => {
  it('reads {from,to}, {start,end} and pairs', () => {
    expect(readDateRange({ from: '2026-01-01', to: '2026-01-31T00:00:00Z' })).toEqual({ start: '2026-01-01', end: '2026-01-31' })
    expect(readDateRange({ start: '2026-02-01', end: null })).toEqual({ start: '2026-02-01', end: null })
    expect(readDateRange(['2026-03-01', '2026-03-02'])).toEqual({ start: '2026-03-01', end: '2026-03-02' })
    expect(readDateRange(null)).toEqual({ start: null, end: null })
  })
})

describe('singleSelectValue', () => {
  it('passes a scalar through and reads a list as its first value', () => {
    expect(singleSelectValue(3)).toBe(3)
    expect(singleSelectValue('a')).toBe('a')
    expect(singleSelectValue(null)).toBeNull()
    expect(singleSelectValue(undefined)).toBeNull()
    expect(singleSelectValue(['b', 'c'])).toBe('b')
    expect(singleSelectValue([])).toBeNull()
  })
})
