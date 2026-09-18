import { describe, expect, it } from 'vitest'
import {
  bindFrame,
  blockElement,
  findRange,
  highlightBlock,
  keyAt,
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

describe('which block a point in the page belongs to', () => {
  it('answers with the innermost one, and with nothing outside every block', () => {
    const doc = page()

    expect(keyAt(doc.querySelector('p'))).toBe('c')
    expect(keyAt(doc.querySelector('.b-section'))).toBe('b')
    expect(keyAt(doc.querySelector('section'))).toBe('a')
    expect(keyAt(doc.querySelector('footer'))).toBeNull()
    expect(keyAt(doc.querySelector('header'))).toBeNull()
  })

  /* A text node is what a click on a word actually lands on. */
  it('takes a node that is not an element', () => {
    const doc = page()

    expect(keyAt(doc.querySelector('p')!.firstChild)).toBe('c')
    expect(keyAt(null)).toBeNull()
  })

  it('does not let a click out of the preview, and reports the block it landed in', () => {
    const doc = page()
    const seen: (string | null)[] = []
    const binding = bindFrame(doc, { select: (key) => seen.push(key) })

    const event = new MouseEvent('click', { bubbles: true, cancelable: true })
    doc.querySelector('p')!.dispatchEvent(event)

    expect(seen).toEqual(['c'])
    expect(event.defaultPrevented).toBe(true)

    binding.release()
    doc.querySelector('p')!.dispatchEvent(new MouseEvent('click', { bubbles: true }))

    expect(seen).toEqual(['c'])
  })
})

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
