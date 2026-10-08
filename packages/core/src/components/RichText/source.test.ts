import { describe, expect, it } from 'vitest'
import { formatHtml, lostMarkup } from './source'

describe('formatHtml', () => {
  it('puts each block on its own line and indents what holds blocks', () => {
    expect(formatHtml('<h2>Title</h2><ul><li><p>One</p></li></ul><p>End</p>')).toBe(
      ['<h2>Title</h2>', '<ul>', '  <li>', '    <p>One</p>', '  </li>', '</ul>', '<p>End</p>'].join(
        '\n',
      ),
    )
  })

  it('leaves inline markup and images inside a paragraph on its line', () => {
    const html = '<p>A <strong>bold</strong> <a href="/x">link</a><img src="a.png"></p>'
    expect(formatHtml(html)).toBe(html)
  })

  it('does not touch what is inside a pre', () => {
    const html = '<pre><code><div>\n  x</div></code></pre>'
    expect(formatHtml(html)).toBe(html)
  })

  it('gives a void block a line of its own', () => {
    expect(formatHtml('<p>a</p><hr><p>b</p>')).toBe('<p>a</p>\n<hr>\n<p>b</p>')
  })
})

describe('lostMarkup', () => {
  it('names the tags and attributes that did not survive', () => {
    expect(lostMarkup('<div><p style="color: red">x</p></div>', '<p>x</p>')).toEqual([
      '<div>',
      'style on <p>',
    ])
  })

  it('does not count a tag spelt another way as lost', () => {
    expect(lostMarkup('<p><b>a</b> <i>b</i></p>', '<p><strong>a</strong> <em>b</em></p>')).toEqual(
      [],
    )
  })

  it('does not report what the editor added', () => {
    expect(lostMarkup('text', '<p>text</p>')).toEqual([])
  })

  it('counts, so one kept tag does not vouch for another that went', () => {
    expect(lostMarkup('<p>a</p><p>b</p>', '<p>ab</p>')).toEqual(['<p>'])
  })

  it('names a dropped tag once rather than with each of its attributes', () => {
    expect(lostMarkup('<span class="x" id="y">a</span>', 'a')).toEqual(['<span>'])
  })
})
