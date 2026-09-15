import { describe, expect, it } from 'vitest'
import {
  blockElement,
  findRange,
  highlightBlock,
  replaceBlock,
  SELECTED_CLASS,
  stageDocument,
} from './frame'

function page(): Document {
  const doc = document.implementation.createHTMLDocument('site')
  doc.body.innerHTML =
    '<header>Site</header>' +
    '<!--wx:a--><section data-wx-block="hero">One</section><!--/wx:a-->' +
    '<!--wx:b--><div class="b-section"><!--wx:c--><p>Nested</p><!--/wx:c--></div><!--/wx:b-->' +
    '<footer>End</footer>'

  return doc
}

describe('the markers in the preview', () => {
  it('finds a block by its pair of comments, nested ones too', () => {
    const doc = page()

    expect(findRange(doc, 'a')?.end.data).toBe('/wx:a')
    expect(blockElement(doc, 'c')?.textContent).toBe('Nested')
    expect(findRange(doc, 'zzz')).toBeNull()
  })

  it('swaps what sits between the markers and keeps the rest of the page', () => {
    const doc = page()

    expect(
      replaceBlock(doc, 'a', '<!--wx:a--><section data-wx-block="hero">Two</section><!--/wx:a-->'),
    ).toBe(true)

    expect(doc.body.innerHTML).toContain(
      '<header>Site</header><!--wx:a--><section data-wx-block="hero">Two</section><!--/wx:a--><!--wx:b-->',
    )
    expect(doc.body.innerHTML).toContain('<footer>End</footer>')
    expect(replaceBlock(doc, 'gone', '<p></p>')).toBe(false)
  })

  it('highlights one block at a time', () => {
    const doc = page()

    highlightBlock(doc, 'a')
    expect(doc.querySelector('section')?.classList.contains(SELECTED_CLASS)).toBe(true)

    highlightBlock(doc, 'c')
    expect(doc.querySelectorAll(`.${SELECTED_CLASS}`).length).toBe(1)
    expect(doc.querySelector('p')?.classList.contains(SELECTED_CLASS)).toBe(true)

    highlightBlock(doc, null)
    expect(doc.querySelectorAll(`.${SELECTED_CLASS}`).length).toBe(0)
    expect(doc.querySelectorAll('#wx-preview-style').length).toBe(1)
  })

  it('builds a stage document with the runtime only when there is a script', () => {
    const bare = stageDocument({ html: '<p>x</p>', styles: 'p{color:red}' })

    expect(bare).toContain('<style>p{color:red}</style>')
    expect(bare).not.toContain('<script')

    const scripted = stageDocument({
      html: '<p>x</p>',
      styles: '',
      script: 'webx.block("hero", async (el) => {})',
      runtime: '/blocks/runtime.js?v=1',
      base: 'https://site.test/',
    })

    expect(scripted).toContain('<base href="https://site.test/">')
    expect(scripted).toContain('<script src="/blocks/runtime.js?v=1"></script>')
  })
})
