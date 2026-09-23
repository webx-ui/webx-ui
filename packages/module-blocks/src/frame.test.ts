import { describe, expect, it } from 'vitest'
import {
  bindFrame,
  blockElement,
  fillStage,
  findRange,
  freezeFrame,
  highlightBlock,
  keyAt,
  replaceBlock,
  SELECTED_CLASS,
  siteShell,
  stageDocument,
  thumbDocument,
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

  it('lets a click on the site around the blocks reach it, without letting it navigate', () => {
    const doc = page()
    const heard: string[] = []
    doc.querySelector('header')!.addEventListener('click', () => heard.push('header'))
    const binding = bindFrame(doc, { select: () => {} })

    const event = new MouseEvent('click', { bubbles: true, cancelable: true })
    doc.querySelector('header')!.dispatchEvent(event)

    expect(heard).toEqual(['header'])
    expect(event.defaultPrevented).toBe(false)
    binding.release()
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

describe('the stage a block type is drawn on', () => {
  function stage(): Document {
    const doc = document.implementation.createHTMLDocument('site')
    doc.head.innerHTML = '<style id="wx-stage-styles"></style>'
    doc.body.innerHTML =
      '<header>Site</header><main><!--wx:sample--><!--/wx:sample--></main><footer>End</footer>'

    return doc
  }

  it('puts the block between the header and the footer, and swaps it on the next change', () => {
    const doc = stage()

    expect(
      fillStage(doc, {
        html: '<!--wx:sample--><p>One</p><!--/wx:sample-->',
        styles: 'p{color:red}',
      }),
    ).toBe(true)
    expect(doc.querySelector('main')?.textContent).toBe('One')
    expect(doc.getElementById('wx-stage-styles')?.textContent).toBe('p{color:red}')

    expect(
      fillStage(doc, { html: '<!--wx:sample--><p>Two</p><!--/wx:sample-->', styles: '' }),
    ).toBe(true)
    expect(doc.querySelector('main')?.innerHTML).toBe('<!--wx:sample--><p>Two</p><!--/wx:sample-->')
    expect(doc.querySelector('header')?.textContent).toBe('Site')
  })

  it('keeps a place for the next change when the markup came without its markers', () => {
    const doc = stage()

    fillStage(doc, { html: '<p>Bare</p>', styles: '' })

    expect(fillStage(doc, { html: '<p>Again</p>', styles: '' })).toBe(true)
    expect(doc.querySelector('main')?.textContent).toBe('Again')
  })

  it('stops the links of the site, and lets go of them when released', () => {
    const doc = stage()
    const link = doc.createElement('a')
    link.href = '/elsewhere'
    doc.body.appendChild(link)

    const binding = freezeFrame(doc)
    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    link.dispatchEvent(click)
    expect(click.defaultPrevented).toBe(true)

    binding.release()
    const after = new MouseEvent('click', { bubbles: true, cancelable: true })
    link.dispatchEvent(after)
    expect(after.defaultPrevented).toBe(false)
  })

  it("lets a click reach the site's own handlers, so its popup can be closed", () => {
    const doc = stage()
    const close = doc.createElement('button')
    const closeLink = doc.createElement('a')
    closeLink.href = '#'
    closeLink.textContent = '×'
    doc.body.append(close, closeLink)
    const heard: string[] = []
    close.addEventListener('click', () => heard.push('button'))
    closeLink.addEventListener('click', () => heard.push('link'))

    const binding = freezeFrame(doc)
    close.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    closeLink.firstChild!.dispatchEvent(click)

    expect(heard).toEqual(['button', 'link'])
    expect(click.defaultPrevented).toBe(true)
    binding.release()
  })

  it('still swallows a submit', () => {
    const doc = stage()
    const form = doc.createElement('form')
    doc.body.appendChild(form)
    const binding = freezeFrame(doc)
    const heard: string[] = []
    form.addEventListener('submit', () => heard.push('submit'))

    const submit = new Event('submit', { bubbles: true, cancelable: true })
    form.dispatchEvent(submit)

    expect(submit.defaultPrevented).toBe(true)
    expect(heard).toEqual([])
    binding.release()
  })
})

describe('the site around a thumbnail', () => {
  const stagePage =
    '<!doctype html><html lang="en" class="theme"><head>' +
    '<link rel="stylesheet" href="/build/app.css"><link rel="preload" as="font" href="/f.woff2">' +
    '<link rel="icon" href="/favicon.ico"><style>:root{--green:#3a6}</style>' +
    '<script src="/blocks-runtime.js"></script><style id="wx-stage-styles"></style>' +
    '<script type="module" src="/build/app.js"></script></head>' +
    '<body class="site"><header>Menu</header><main class="site-main"><div class="wrap">' +
    '<!--wx:sample--><!--/wx:sample--></div></main><footer>Footer</footer>' +
    '<div class="popup">Join the list</div><script>open()</script></body></html>'

  it('keeps the look and the wrappers, and leaves the page and its scripts behind', () => {
    const shell = siteShell(stagePage, '/_preview/block-stage')!
    const doc = thumbDocument(shell, {
      html: '<section class="b-hero">Hi</section>',
      styles: '.b-hero{}',
    })

    expect(doc).toContain(`href="${location.origin}/build/app.css"`)
    expect(doc).toContain('as="font"')
    expect(doc).toContain('<style>:root{--green:#3a6}</style>')
    expect(doc).toContain('<style>.b-hero{}</style></head>')
    expect(doc).toContain('<html lang="en" class="theme">')
    expect(doc).toContain(
      '<body class="site"><main class="site-main"><div class="wrap"><section class="b-hero">Hi</section></div></main></body>',
    )
    for (const gone of [
      '<script',
      'favicon',
      'Menu',
      'Footer',
      'Join the list',
      'wx-stage-styles',
    ]) {
      expect(doc).not.toContain(gone)
    }
  })

  it('gives up on a page without a place for the block', () => {
    expect(siteShell('<html><body><p>No slot</p></body></html>', '/stage')).toBeNull()
  })
})
