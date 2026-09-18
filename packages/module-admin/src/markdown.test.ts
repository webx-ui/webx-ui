import { describe, expect, it } from 'vitest'
import { renderMarkdown } from './markdown'

describe('the little of Markdown a page of help needs', () => {
  it('starts headings at h2, because the dialog already has the h1', () => {
    expect(renderMarkdown('# One\n\n## Two\n\n### Three')).toBe(
      '<h2>One</h2><h3>Two</h3><h4>Three</h4>',
    )
  })

  it('runs a paragraph to the blank line, not to the end of the line', () => {
    expect(renderMarkdown('One line\nand its rest.\n\nAnother.')).toBe(
      '<p>One line and its rest.</p><p>Another.</p>',
    )
  })

  it('takes both kinds of list', () => {
    expect(renderMarkdown('- a\n- b')).toBe('<ul><li>a</li><li>b</li></ul>')
    expect(renderMarkdown('1. a\n2. b')).toBe('<ol><li>a</li><li>b</li></ol>')
  })

  /* A page written at 96 columns wraps, and a wrapped item is still that item. */
  it('folds a line that continues an item back into it', () => {
    expect(renderMarkdown('- one that runs\n  onto the next line\n- two')).toBe(
      '<ul><li>one that runs onto the next line</li><li>two</li></ul>',
    )
  })

  it('keeps a fenced block whole, newlines and all', () => {
    expect(renderMarkdown('```json\n{\n  "id": "title"\n}\n```')).toBe(
      '<pre><code>{\n  &quot;id&quot;: &quot;title&quot;\n}</code></pre>',
    )
  })

  it('marks up a line without reading what is inside a code span', () => {
    expect(renderMarkdown('Write `a *b* c` and **that**.')).toBe(
      '<p>Write <code>a *b* c</code> and <strong>that</strong>.</p>',
    )
  })

  /*
   * The whole reason this output can be handed to `v-html`: everything is escaped before a
   * tag is emitted, so the only tags in the result are the ones built here.
   */
  it('emits no tag it did not write itself', () => {
    const html = renderMarkdown('An <img src=x onerror=alert(1)> and `<script>`.\n\n<b>bold?</b>')

    expect(html).not.toContain('<img')
    expect(html).not.toContain('<script')
    expect(html).not.toContain('<b>')
    expect(html).toContain('&lt;img')
  })

  it('links out, and only to the web', () => {
    expect(renderMarkdown('See [the guide](https://example.test/a).')).toContain(
      '<a href="https://example.test/a" target="_blank" rel="noopener">the guide</a>',
    )
    expect(renderMarkdown('Not [this](javascript:alert(1)).')).not.toContain('<a ')
  })
})
