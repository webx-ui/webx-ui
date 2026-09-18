/**
 * The preview is a page of the site in an iframe, and the panel's hold on it is a pair of
 * comments around every block — `<!--wx:key-->…<!--/wx:key-->` — printed by the renderer in
 * preview mode. Finding a block is walking the comments; replacing one is swapping what sits
 * between the pair; highlighting one is a class on the first element after the opener.
 *
 * The panel and the site are one application on one domain, so the frame's document is
 * reachable and nothing has to be injected into the site's markup beyond one stylesheet.
 */

export const SELECTED_CLASS = 'wx-preview-selected'
export const HOVER_CLASS = 'wx-preview-hover'

const STYLE_ID = 'wx-preview-style'

export interface MarkerRange {
  start: Comment
  end: Comment
}

/** The pair of markers around a block, or null when the page does not hold it. */
export function findRange(root: Node, key: string): MarkerRange | null {
  const doc = root.ownerDocument ?? (root as Document)
  const walker = doc.createTreeWalker(root, 128 /* NodeFilter.SHOW_COMMENT */)
  let start: Comment | null = null

  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    const text = (node as Comment).data.trim()

    if (start === null && text === `wx:${key}`) {
      start = node as Comment
    } else if (start !== null && text === `/wx:${key}`) {
      return { start, end: node as Comment }
    }
  }

  return null
}

/**
 * Swap the block's markup — markers included, since the new HTML carries its own — and let
 * the runtime mount whatever script the new markup needs. False when the key is not on the
 * page, which means the page has to be reloaded rather than patched.
 */
export function replaceBlock(doc: Document, key: string, html: string): boolean {
  const range = findRange(doc, key)

  if (!range) return false

  const parent = range.start.parentNode

  if (!parent) return false

  const template = doc.createElement('template')
  template.innerHTML = html

  // What follows the block stays where it is; the new markup lands in front of it.
  const anchor = range.end.nextSibling
  let node: Node | null = range.start

  while (node) {
    const next: Node | null = node.nextSibling
    const done = node === range.end
    parent.removeChild(node)
    if (done) break
    node = next
  }

  const fragment = template.content
  const inserted = Array.from(fragment.childNodes)
  parent.insertBefore(fragment, anchor)

  const runtime = (doc.defaultView as (Window & { webx?: { mount?: (root: Node) => void } }) | null)
    ?.webx

  if (runtime?.mount) {
    for (const child of inserted) {
      if (child.nodeType === 1) runtime.mount(child)
    }
  }

  return true
}

/** The first element a block prints — where a highlight goes. */
export function blockElement(root: Node, key: string): Element | null {
  const range = findRange(root, key)

  if (!range) return null

  for (let node = range.start.nextSibling; node && node !== range.end; node = node.nextSibling) {
    if (node.nodeType === 1) return node as Element
  }

  return null
}

/** Mark one block as the selected one, and nothing else. Null clears the selection. */
export function highlightBlock(doc: Document, key: string | null): Element | null {
  ensureStyle(doc)

  for (const element of Array.from(doc.querySelectorAll(`.${SELECTED_CLASS}`))) {
    element.classList.remove(SELECTED_CLASS)
  }

  if (key === null) return null

  const element = blockElement(doc, key)
  element?.classList.add(SELECTED_CLASS)

  return element
}

/** Mark one block as the one under the pointer. Null clears it. */
export function hoverBlock(doc: Document, key: string | null): void {
  ensureStyle(doc)

  for (const element of Array.from(doc.querySelectorAll(`.${HOVER_CLASS}`))) {
    element.classList.remove(HOVER_CLASS)
  }

  if (key === null) return

  blockElement(doc, key)?.classList.add(HOVER_CLASS)
}

/**
 * Which block an element inside the page belongs to — the innermost one, since a block nested
 * in a container is what somebody pointing at it means.
 *
 * The markers are comments among an element's siblings rather than around it, so this walks
 * back through the previous siblings counting closers: a `/wx:` met on the way out belongs to
 * a block that has already ended, and its opener is not ours. Nothing found at this level
 * means the search carries on in the parent.
 */
export function keyAt(node: Node | null): string | null {
  let element: Element | null =
    node?.nodeType === 1 ? (node as Element) : (node?.parentElement ?? null)

  while (element) {
    let depth = 0

    for (
      let sibling: Node | null = element.previousSibling;
      sibling;
      sibling = sibling.previousSibling
    ) {
      if (sibling.nodeType !== 8) continue

      const text = (sibling as Comment).data.trim()

      if (text.startsWith('/wx:')) {
        depth += 1
      } else if (text.startsWith('wx:')) {
        if (depth === 0) return text.slice(3)

        depth -= 1
      }
    }

    element = element.parentElement
  }

  return null
}

/** What was bound to the page, so that a reload can let go of the old document. */
export interface FrameBinding {
  release(): void
}

/**
 * Make the preview a picture of the page rather than the page itself.
 *
 * Every pointer event is taken at the document, in the capture phase, and goes no further:
 * stopped there it never reaches a link, a form or a block's own script, so nothing in the
 * preview navigates, submits or opens anything. What a click does instead is choose the block
 * it landed in, which is the one thing the editor wants from it.
 *
 * Bound per document: an iframe that reloads gets a new one, and the old binding is released
 * with it.
 */
export function bindFrame(
  doc: Document,
  handlers: { select: (key: string | null) => void },
): FrameBinding {
  ensureStyle(doc)

  let hovered: string | null = null

  function swallow(event: Event): void {
    event.preventDefault()
    event.stopPropagation()
  }

  function onClick(event: MouseEvent): void {
    swallow(event)
    handlers.select(keyAt(event.target as Node | null))
  }

  function onMove(event: MouseEvent): void {
    const key = keyAt(event.target as Node | null)

    if (key === hovered) return

    hovered = key
    hoverBlock(doc, key)
  }

  function onLeave(): void {
    hovered = null
    hoverBlock(doc, null)
  }

  doc.addEventListener('click', onClick, true)
  doc.addEventListener('auxclick', swallow, true)
  doc.addEventListener('submit', swallow, true)
  doc.addEventListener('dragstart', swallow, true)
  doc.addEventListener('mousemove', onMove, true)
  // Not in the capture phase: `mouseleave` does not bubble, so a capturing listener here
  // would be told about every element the pointer leaves on its way across the page.
  doc.addEventListener('mouseleave', onLeave)

  return {
    release() {
      doc.removeEventListener('click', onClick, true)
      doc.removeEventListener('auxclick', swallow, true)
      doc.removeEventListener('submit', swallow, true)
      doc.removeEventListener('dragstart', swallow, true)
      doc.removeEventListener('mousemove', onMove, true)
      doc.removeEventListener('mouseleave', onLeave)
    },
  }
}

/** The one thing the panel adds to the site's markup: how a block looks under the editor. */
function ensureStyle(doc: Document): void {
  if (doc.getElementById(STYLE_ID)) return

  const style = doc.createElement('style')
  style.id = STYLE_ID
  // Inside the outline rather than around it: a block flush with the edge of the page would
  // otherwise have half of its mark cut off by the window. The hover is the thinner of the
  // two, so that pointing at the selected block does not look like selecting another one.
  style.textContent =
    `.${SELECTED_CLASS}{outline:2px solid #427edd;outline-offset:-2px;}` +
    `.${HOVER_CLASS}:not(.${SELECTED_CLASS}){outline:1px solid #427edd;outline-offset:-1px;cursor:pointer;}`
  ;(doc.head ?? doc.documentElement).appendChild(style)
}

export interface StageInput {
  html: string
  styles: string
  /** The script wrapped for the runtime, or nothing. */
  script?: string | null
  /** Where the runtime is served; skipped when there is no script to run. */
  runtime?: string | null
  /** Resolves relative addresses the block prints — images, links — against the site. */
  base?: string | null
  /** Styles the site's layout would give the page: fonts, the ground colour. */
  frame?: string | null
}

/**
 * A whole document for the editor's stage: the block on its own, with its styles, its script
 * and the runtime, and nothing of the panel. `srcdoc` takes it.
 */
export function stageDocument(input: StageInput): string {
  const base = input.base ? `<base href="${escapeAttribute(input.base)}">` : ''
  const frame = input.frame ? `<style>${input.frame}</style>` : ''
  const runtime =
    input.script && input.runtime
      ? `<script src="${escapeAttribute(input.runtime)}"></script><script>${input.script}</script>`
      : ''

  return (
    `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${base}` +
    `<style>html,body{margin:0}body{container-type:inline-size}</style>${frame}<style>${input.styles}</style></head>` +
    `<body>${input.html}${runtime}</body></html>`
  )
}

function escapeAttribute(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')
}
