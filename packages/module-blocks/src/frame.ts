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

/** The one thing the panel adds to the site's markup: how a selected block looks. */
function ensureStyle(doc: Document): void {
  if (doc.getElementById(STYLE_ID)) return

  const style = doc.createElement('style')
  style.id = STYLE_ID
  style.textContent = `.${SELECTED_CLASS}{outline:2px solid #427edd;outline-offset:-2px;}`
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
