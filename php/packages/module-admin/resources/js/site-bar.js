/*
 * The panel on the site (WEBX_UI_SITE_BAR.md, §4–§5).
 *
 * The one script the server puts on a page for an administrator who is signed in to the panel,
 * and never on a visitor's. It draws a tab at the right edge and, on the first click, a drawer
 * with the panel itself in a frame: the panel is the real one, same bundle, session and words,
 * so nothing it can do has to be written twice — and a page whose bar stays shut loads nothing
 * more than this file.
 *
 * Everything it draws lives in the shadow root of one element at the end of `<body>`: the site's
 * styles do not reach in, its own do not leak out. It reads no cookie, writes none, and keeps
 * its state (open or shut, where the tab was dragged) in `localStorage` under `wx-bar` — which
 * only an administrator's browser ever gets, because only their pages carry the script.
 *
 * The blocks of the page (§8): the server prints them between markers for an administrator —
 * `<!--wx:{key}-->…<!--/wx:{key}-->`, the record's `<!--wx-entity:{type}:{id}-->` around them, a
 * region's `<!--wx-region:{name}-->` around the header's — and this script frames them on a layer
 * of its own over the page. Picking (the pencil in the panel) makes a click on a block choose it
 * rather than follow it; without it the site behaves as the site. The only changes it ever makes
 * to the page's markup are blocks' ranges — replaced, put in, taken out or moved by what the
 * server drew from the draft — and the styles and script of a block type the page did not have.
 *
 * No framework and no dependencies, like the blocks runtime: it runs on somebody else's page.
 */

// The panel's design tokens, rewritten for a shadow host. Filled in by the server, which serves
// this file (`WebxUi\Admin\SiteBar\Loader`); empty when the file is read straight from the disk.
const TOKENS = '' // tokens: written in when served

const KEY = 'wx-bar'
const DRAWER = 380
const NARROW = 640
// Where a dragged tab may stop: never so close to an edge of the window that it cannot be taken
// hold of again.
const MARGIN = 32
// The keys the renderer makes up for blocks of old content that were saved without one: they
// change with the order, so a block under one cannot be edited from here (§8).
const TEMPORARY = /^\d+-\d+$/
// Room kept between a block brought into view and the edge of the window.
const GAP = 16
// A set of block styles and scripts, by the hash its address ends in (`/blocks/{hash}.css`).
const BUNDLE = /\/([a-f0-9]{16})\.(?:css|js)(?:[?#]|$)/

const STYLE = `
:host {
  all: initial;
}

.tab {
  position: fixed;
  top: var(--wx-bar-top, 50%);
  right: 0;
  z-index: calc(var(--wx-z-index-overlay) + 1);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  width: 28px;
  height: 48px;
  margin: 0;
  padding: 0;
  transform: translateY(-50%);
  border: 1px solid var(--wx-border-default);
  border-right: none;
  border-radius: var(--wx-radius-sm) 0 0 var(--wx-radius-sm);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  cursor: pointer;
  touch-action: none;
  user-select: none;
  transition: right var(--wx-duration-normal) var(--wx-easing-standard);
}

/*
 * A drawn chevron rather than a "‹": a glyph is centred by its line box, and its ink sits at the
 * height of lowercase letters, a different height in every site's font — off centre in all of them.
 */
.tab svg {
  display: block;
  width: 16px;
  height: 16px;
  transition: transform var(--wx-duration-normal) var(--wx-easing-standard);
}

.is-open .tab svg {
  transform: scaleX(-1);
}

.tab:hover {
  background: var(--wx-bg-subtle);
}

.tab:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 2px;
}

.tab.is-dragging {
  cursor: grabbing;
  transition: none;
}

/* Open, it rides on the drawer's edge, so the same place on the screen closes what it opened. */
.is-open .tab {
  right: ${DRAWER}px;
}

.drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  z-index: var(--wx-z-index-overlay);
  box-sizing: border-box;
  width: ${DRAWER}px;
  max-width: 100%;
  border-left: 1px solid var(--wx-border-default);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-dialog);
  transform: translateX(100%);
  visibility: hidden;
  transition:
    transform var(--wx-duration-normal) var(--wx-easing-standard),
    visibility 0s linear var(--wx-duration-normal);
}

.is-open .drawer {
  transform: none;
  visibility: visible;
  transition: transform var(--wx-duration-normal) var(--wx-easing-standard);
}

.frame {
  display: block;
  width: 100%;
  height: 100%;
  border: 0;
  background: var(--wx-bg-surface);
}

/*
 * A phone's window: the drawer takes all of it, and the tab stays at the edge on top of it —
 * there is no page left beside the drawer to put it on. The window rather than a container,
 * because the window is all there is here: the bar is not inside anything of the site's.
 */
@media (max-width: ${NARROW - 1}px) {
  .drawer {
    width: 100%;
    border-left: none;
  }

  .is-open .tab {
    right: 0;
  }
}

/*
 * The frames over the blocks. A layer of its own under the drawer, never in the way of a click:
 * the page is what is clicked, and the layer only shows where.
 */
.layer {
  position: fixed;
  inset: 0;
  z-index: calc(var(--wx-z-index-overlay) - 1);
  overflow: hidden;
  pointer-events: none;
}

.box {
  position: absolute;
  box-sizing: border-box;
  border: 1px solid var(--wx-color-primary);
  border-radius: var(--wx-radius-xs);
}

.box.is-highlight {
  background: color-mix(in srgb, var(--wx-color-primary-soft) 45%, transparent);
}

.box.is-selected {
  border-width: 2px;
}

/* The block on the page is the draft's now, not the site's: the page is a mix, and it shows. */
.box.is-draft {
  border: 2px dashed var(--wx-color-warning);
}

.tag {
  position: absolute;
  bottom: 100%;
  left: -1px;
  max-width: min(320px, 90vw);
  overflow: hidden;
  padding: 2px 6px;
  border-radius: var(--wx-radius-xs) var(--wx-radius-xs) 0 0;
  background: var(--wx-color-primary);
  color: var(--wx-color-primary-contrast);
  font: var(--wx-font-weight-medium) var(--wx-font-size-xs) / 1.4 var(--wx-font-family-sans);
  white-space: nowrap;
  text-overflow: ellipsis;
}

/* A block at the very top of the window has no room above it: its name goes inside. */
.box.is-top .tag {
  top: 0;
  bottom: auto;
  border-radius: 0 0 var(--wx-radius-xs) 0;
}

.box.is-draft .tag {
  background: var(--wx-color-warning);
  color: var(--wx-color-warning-contrast);
}

/*
 * «Add below» on the lower edge of the block picked on the page. The one thing on the layer that
 * takes the pointer: it is the bar's own control, drawn where the new block will go.
 */
.add {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  width: 28px;
  height: 28px;
  margin: 0;
  padding: 0;
  transform: translate(-50%, -50%);
  border: 2px solid var(--wx-bg-surface);
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-color-primary-contrast);
  cursor: pointer;
  pointer-events: auto;
}

.add:hover {
  background: var(--wx-color-primary-hover);
}

.add:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 2px;
}

.add svg {
  display: block;
  width: 14px;
  height: 14px;
}

@media (prefers-reduced-motion: reduce) {
  .tab,
  .drawer,
  .is-open .drawer {
    transition: none;
  }
}
`

function readState() {
  try {
    const parsed = JSON.parse(localStorage.getItem(KEY) ?? '{}')

    return typeof parsed === 'object' && parsed !== null ? parsed : {}
  } catch {
    // Private windows, blocked site data, a value somebody else wrote: start shut.
    return {}
  }
}

function writeState(state) {
  try {
    localStorage.setItem(KEY, JSON.stringify(state))
  } catch {
    // Nothing here is worth an error on somebody's page.
  }
}

function readConfig(script) {
  try {
    return JSON.parse(script.getAttribute('data-wx-bar') ?? '{}')
  } catch {
    return {}
  }
}

function start() {
  // A module script has no `document.currentScript`; the attribute is what marks this one.
  const script = document.querySelector('script[data-wx-bar]')

  if (script === null || document.querySelector('[data-wx-bar-root]') !== null) {
    return
  }

  const config = readConfig(script)
  const words = config.words ?? {}
  const src = typeof config.src === 'string' ? config.src : '/cms/_bar'
  const state = { open: false, top: null, tab: null, ...readState() }

  const host = document.createElement('div')
  host.setAttribute('data-wx-bar-root', '')

  // The panel's own colours, not the site's: the bar is the panel. Without a choice the tokens
  // follow the machine, as the panel does.
  if (config.theme === 'light' || config.theme === 'dark') {
    host.setAttribute('data-theme', config.theme)
  }

  const root = host.attachShadow({ mode: 'open' })
  const style = document.createElement('style')
  style.textContent = TOKENS + STYLE

  const shell = document.createElement('div')
  const tab = document.createElement('button')
  tab.type = 'button'
  tab.className = 'tab'
  tab.setAttribute('aria-controls', 'wx-bar-drawer')
  tab.innerHTML =
    '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.25 3.5 5.75 8l4.5 4.5"/></svg>'

  const drawer = document.createElement('aside')
  drawer.className = 'drawer'
  drawer.id = 'wx-bar-drawer'
  drawer.setAttribute('aria-label', words.title ?? 'Site panel')

  const layer = document.createElement('div')
  layer.className = 'layer'

  shell.append(tab, drawer)
  root.append(style, layer, shell)
  document.body.append(host)

  let frame = null

  // The frame is made the first time the drawer opens, not before: the panel's bundle is the
  // heavy part, and an administrator reading their own site with the bar shut should not pay
  // for it on every page.
  function ensureFrame() {
    if (frame !== null) return

    frame = document.createElement('iframe')
    frame.className = 'frame'
    frame.title = words.title ?? 'Site panel'
    // The page's language travels with its address: a page of the site's own code has no row
    // in the registry to say it, and its SEO is per language.
    const lang = document.documentElement.lang
    // The tab the editor had in front on the last page: the frame is made again on every page.
    const tabName = typeof state.tab === 'string' && state.tab !== '' ? state.tab : null
    frame.src =
      `${src}?url=${encodeURIComponent(location.pathname)}` +
      (lang ? `&locale=${encodeURIComponent(lang)}` : '') +
      (tabName ? `&tab=${encodeURIComponent(tabName)}` : '')
    drawer.append(frame)
  }

  function render() {
    shell.classList.toggle('is-open', state.open)
    tab.setAttribute('aria-expanded', String(state.open))
    tab.setAttribute('aria-label', state.open ? (words.close ?? 'Close') : (words.open ?? 'Open'))

    if (typeof state.top === 'number') {
      host.style.setProperty('--wx-bar-top', `${Math.round(state.top * 100)}%`)
    }

    if (state.open) ensureFrame()

    layout()
  }

  // How much of the window's right edge the drawer takes, for the debug bar along the bottom
  // (WEBX_UI_DEBUGBAR.md, §11.3). A drawer as wide as the window says 0: whatever is under it
  // stays under it. The attribute is for a bar that starts after the drawer was already open.
  function layout() {
    const width = drawer.offsetWidth
    const right = state.open && width < window.innerWidth ? width : 0

    if (state.open) host.setAttribute('data-open', String(right))
    else host.removeAttribute('data-open')

    window.dispatchEvent(new CustomEvent('wx-bar:layout', { detail: { right } }))
  }

  window.addEventListener('resize', () => {
    if (state.open) layout()
  })

  function setOpen(open) {
    state.open = open
    writeState(state)
    render()
  }

  // Dragging the tab along the edge. A press that never moved is a click and is left to the
  // click handler, which is also what the keyboard reaches.
  let drag = null

  tab.addEventListener('pointerdown', (event) => {
    if (event.button !== 0) return

    drag = { y: event.clientY, moved: false, id: event.pointerId }
    // Held from the press on: the first step of a quick drag already leaves the 28px of the tab,
    // and the moves after it would go to whatever is under the pointer instead.
    tab.setPointerCapture?.(event.pointerId)
  })

  tab.addEventListener('pointermove', (event) => {
    if (drag === null || event.pointerId !== drag.id) return

    if (!drag.moved && Math.abs(event.clientY - drag.y) < 4) return

    if (!drag.moved) {
      drag.moved = true
      tab.classList.add('is-dragging')
    }

    const height = window.innerHeight
    const y = Math.min(Math.max(event.clientY, MARGIN), height - MARGIN)
    state.top = y / height
    host.style.setProperty('--wx-bar-top', `${y}px`)
  })

  function endDrag(event) {
    if (drag === null || event.pointerId !== drag.id) return

    if (drag.moved) {
      tab.classList.remove('is-dragging')
      writeState(state)
      render()
      // The click that ends a drag is not a click on the tab.
      tab.addEventListener('click', (click) => click.stopImmediatePropagation(), {
        capture: true,
        once: true,
      })
    }

    drag = null
  }

  tab.addEventListener('pointerup', endDrag)
  tab.addEventListener('pointercancel', endDrag)
  tab.addEventListener('click', () => setOpen(!state.open))

  /*
   * The blocks of the page. What the panel said about them — whose trees may be picked from, what
   * each block is called — and what is on screen: picking or not, the block chosen, the one under
   * the pointer, the one under the pointer in the panel's list, the ones replaced from the draft.
   *
   * A block is known by its tree and its key together — `page:12|k1`, `region:header|k1` — since
   * the header and the page are two trees, and nothing promised that their keys never meet.
   */
  const edit = {
    picking: false,
    sources: new Map(),
    blocks: [],
    selected: null,
    hover: null,
    highlight: null,
    drafts: new Set(),
    // The closing marker of each tree — where a block put at the end of it goes.
    ends: new Map(),
    // The sets of block styles and scripts the page has, by hash.
    bundles: new Set(),
    // The regions printed on the page, as the panel was last told them.
    regions: '',
  }

  const idOf = (scope, key) => `${scope}|${key}`

  // Every pair of block markers in the document, in order, with what it is inside: the tree it
  // belongs to — a region's, or the record's — and the block around it: a nested block is one
  // level deeper than its container.
  function scan() {
    const found = []
    const open = []
    const ends = new Map()
    let entity = null
    let region = null
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT)

    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
      const text = node.data.trim()
      const record = /^wx-entity:([^:]+):(.+)$/.exec(text)
      const place = /^wx-region:(.+)$/.exec(text)

      if (record) {
        entity = `${record[1]}:${record[2]}`
      } else if (text.startsWith('/wx-entity:')) {
        ends.set(text.slice(11), node)
        entity = null
      } else if (place) {
        region = `region:${place[1]}`
      } else if (text.startsWith('/wx-region:')) {
        ends.set(`region:${text.slice(11)}`, node)
        region = null
      } else if (text.startsWith('wx:')) {
        // A record's blocks printed inside a region are the region's to edit: the region is
        // what put them there.
        const scope = region ?? entity
        const key = text.slice(3)
        const block = {
          key,
          scope,
          id: idOf(scope, key),
          start: node,
          end: null,
          depth: open.length,
        }
        open.push(block)
        found.push(block)
      } else if (text.startsWith('/wx:')) {
        const key = text.slice(4)
        const at = open.findLastIndex((block) => block.key === key)

        if (at !== -1) {
          open[at].end = node
          open.length = at
        }
      }
    }

    edit.blocks = found.filter((block) => block.end !== null)
    edit.ends = ends

    // A shared block is on the pages that place it, not on all of them like the header: the panel
    // lists the regions this page prints, and a block put in or taken out can change which.
    if (regionsOnPage().join(',') !== edit.regions) tellRegions()
  }

  function regionsOnPage() {
    return [...edit.ends.keys()]
      .filter((scope) => scope.startsWith('region:'))
      .map((scope) => scope.slice(7))
  }

  function tellRegions() {
    const names = regionsOnPage()

    edit.regions = names.join(',')
    post({ type: 'regions', names })
  }

  // A block the panel may edit from here: one of the trees it named — the record's own, a region
  // it may edit — and not another record's printed beside them.
  function editable(block) {
    return block.scope !== null && edit.sources.has(block.scope)
  }

  function temporary(block) {
    return TEMPORARY.test(block.key) || !edit.sources.get(block.scope)?.labels.has(block.key)
  }

  function find(id) {
    return edit.blocks.find((block) => block.id === id && editable(block)) ?? null
  }

  function rangeOf(block) {
    const range = document.createRange()
    range.setStartAfter(block.start)
    range.setEndBefore(block.end)

    return range
  }

  // A block is whatever stands between its markers — several elements, text, nothing at all —
  // so its frame is the rectangle around all of it: the boxes of what stands there, not of
  // everything inside. A range's own rectangle counts the descendants too, and the answer of a
  // closed `<details>` keeps a box below its block — the frame of a list of questions went on
  // over the block after it.
  function rectOf(block) {
    let top = Infinity
    let left = Infinity
    let bottom = -Infinity
    let right = -Infinity

    for (let node = block.start.nextSibling; node && node !== block.end; node = node.nextSibling) {
      let rect = null

      if (node.nodeType === 1) {
        rect = node.getBoundingClientRect()
      } else if (node.nodeType === 3 && node.data.trim() !== '') {
        const range = document.createRange()
        range.selectNode(node)
        rect = range.getBoundingClientRect()
      }

      if (rect === null || (rect.width === 0 && rect.height === 0)) continue

      top = Math.min(top, rect.top)
      left = Math.min(left, rect.left)
      bottom = Math.max(bottom, rect.bottom)
      right = Math.max(right, rect.right)
    }

    if (top !== Infinity) return new DOMRect(left, top, right - left, bottom - top)

    // Nothing of its own has a box — a `display: contents` wrapper: the range, whatever is inside.
    const rect = rangeOf(block).getBoundingClientRect()

    return rect.width === 0 && rect.height === 0 ? null : rect
  }

  // The innermost editable block holding the node: a nested block takes the pointer from its
  // container.
  function blockAt(node) {
    let best = null

    for (const block of edit.blocks) {
      if (!editable(block) || node.contains(block.start)) continue
      if (!rangeOf(block).intersectsNode(node)) continue
      if (best === null || block.depth > best.depth) best = block
    }

    return best
  }

  // The block's type, and the region it is in when it is not the page's own: «Menu · Header».
  function labelOf(block) {
    const source = edit.sources.get(block.scope)
    let named = source?.labels.get(block.key) ?? null

    for (
      let node = block.start.nextSibling;
      !named && node && node !== block.end;
      node = node.nextSibling
    ) {
      if (node.nodeType === 1 && node.getAttribute('data-wx-block')) {
        named = node.getAttribute('data-wx-block')
      }
    }

    return [named ?? block.key, source?.title].filter(Boolean).join(' · ')
  }

  let queued = 0

  function draw() {
    queued = 0

    const wanted = new Map()
    const mark = (id, name) => {
      if (id === null) return
      wanted.set(id, [...(wanted.get(id) ?? []), name])
    }

    for (const id of edit.drafts) mark(id, 'is-draft')
    if (edit.picking) mark(edit.hover, 'is-hover')
    mark(edit.highlight, 'is-highlight')
    mark(edit.selected, 'is-selected')

    const boxes = []

    for (const [id, names] of wanted) {
      const block = find(id)
      const rect = block && rectOf(block)

      if (!rect) continue

      const box = document.createElement('div')
      box.className = ['box', ...names, rect.top < 24 ? 'is-top' : ''].join(' ').trim()
      box.style.cssText = `top:${rect.top}px;left:${rect.left}px;width:${rect.width}px;height:${rect.height}px`

      // Every frame says what it is around: the type, and why it is drawn when that is news — a
      // draft, or a block that is edited in the panel only.
      const tag = document.createElement('span')
      const words = [labelOf(block)]

      if (temporary(block)) words.push(config.words?.temporary ?? 'Open in panel to edit')
      if (names.includes('is-draft')) words.push(config.words?.draft ?? 'Draft')

      tag.className = 'tag'
      tag.textContent = words.join(' · ')
      box.append(tag)
      boxes.push(box)
    }

    const add = addButton()

    layer.replaceChildren(...boxes, ...(add ? [add] : []))
  }

  // «Add below» for the block picked on the page, on the middle of its lower edge — kept inside
  // the window, so a block taller than the screen still has it in reach.
  function addButton() {
    const block = edit.picking && edit.selected !== null ? find(edit.selected) : null
    const rect = block && !temporary(block) && rectOf(block)

    if (!rect) return null

    const button = document.createElement('button')
    const label = config.words?.add ?? 'Add below'
    button.type = 'button'
    button.className = 'add'
    button.title = label
    button.setAttribute('aria-label', label)
    button.innerHTML =
      '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 3v10M3 8h10"/></svg>'
    button.style.left = `${Math.min(Math.max(rect.left + rect.width / 2, GAP), window.innerWidth - GAP)}px`
    button.style.top = `${Math.min(Math.max(rect.bottom, GAP), window.innerHeight - GAP)}px`
    button.addEventListener('click', (event) => {
      event.preventDefault()
      event.stopPropagation()
      setOpen(true)
      post({ type: 'add', key: block.key, scope: block.scope })
    })

    return button
  }

  function redraw() {
    if (queued === 0) queued = requestAnimationFrame(draw)
  }

  // Into the window with the smallest move, and only when it is not in it whole: a block on
  // screen stays where the editor is looking.
  function reveal(id) {
    const block = find(id)
    const rect = block && rectOf(block)

    if (!rect) return

    const room = window.innerHeight
    let by = 0

    if (rect.top < GAP || rect.height > room - GAP * 2) {
      by = rect.top - GAP
    } else if (rect.bottom > room - GAP) {
      by = rect.bottom - room + GAP
    }

    if (by !== 0) window.scrollBy({ top: by, behavior: 'smooth' })
  }

  // The panel draws its frame on the page over this bridge too: `post` is the way back.
  function post(message) {
    frame?.contentWindow?.postMessage({ wxBar: 1, ...message }, location.origin)
  }

  function onPointerMove(event) {
    const target = event.target
    const block = target instanceof Node && target !== host ? blockAt(target) : null
    const id = block?.id ?? null

    if (id !== edit.hover) {
      edit.hover = id
      redraw()
    }
  }

  // While picking, a click on a block chooses it — and does nothing else: a link in it does not
  // lead away, a button in it does not press. A block of old content without keys says so and
  // is not chosen.
  function onClick(event) {
    const target = event.target

    if (!(target instanceof Node) || target === host) return

    const block = blockAt(target)

    if (block === null) return

    event.preventDefault()
    event.stopPropagation()

    if (temporary(block)) {
      edit.hover = block.id
      redraw()

      return
    }

    edit.selected = block.id
    setOpen(true)
    post({ type: 'select', key: block.key, scope: block.scope })
    redraw()
  }

  function setPicking(on, tell) {
    if (edit.picking === on) return

    edit.picking = on
    edit.hover = null

    const method = on ? 'addEventListener' : 'removeEventListener'
    document[method]('pointermove', onPointerMove, true)
    document[method]('click', onClick, true)

    if (on) {
      scan()
      // A phone's drawer covers the page; the page is what is picked from.
      if (window.innerWidth < NARROW) setOpen(false)
    }

    if (tell) post({ type: 'mode', on })

    redraw()
  }

  // The block the server drew from the draft, in place of the range it had. Its own markers
  // come with it, and its script starts again the way the preview starts it — `webx.mount` on
  // what came in (§10 of the blocks spec). A range split across parents by the browser's parser
  // cannot be replaced in place; the page is loaded again instead.
  function replace(id, html) {
    const block = find(id)

    if (block === null || block.start.parentNode !== block.end.parentNode) {
      location.reload()

      return
    }

    const parent = block.start.parentNode
    const anchor = block.end.nextSibling
    const template = document.createElement('template')
    template.innerHTML = html

    for (let node = block.start; node;) {
      const next = node.nextSibling
      const last = node === block.end

      node.remove()
      if (last) break
      node = next
    }

    const inserted = Array.from(template.content.childNodes)
    parent.insertBefore(template.content, anchor)

    for (const node of inserted) {
      if (node.nodeType === 1) window.webx?.mount?.(node)
    }

    edit.drafts.add(id)
    scan()
    redraw()
  }

  // The ranges of one block: its markers and everything between them, in order.
  function nodesOf(block) {
    const nodes = []

    for (let node = block.start; node; node = node.nextSibling) {
      nodes.push(node)
      if (node === block.end) break
    }

    return nodes
  }

  // Where a block that is not on the page goes: after the first of the blocks before it the page
  // has, before the first of the blocks after it, or — at the top level of its tree — at the end
  // of the tree's markers. Null when none of them is here: the page does not show that list.
  function anchorOf(scope, message) {
    const keys = (list) =>
      Array.isArray(list) ? list.filter((key) => typeof key === 'string') : []

    for (const key of keys(message.after)) {
      const block = find(idOf(scope, key))
      if (block) return { parent: block.end.parentNode, before: block.end.nextSibling }
    }

    for (const key of keys(message.before)) {
      const block = find(idOf(scope, key))
      if (block) return { parent: block.start.parentNode, before: block.start }
    }

    const end = message.top === true ? edit.ends.get(scope) : null

    return end ? { parent: end.parentNode, before: end } : null
  }

  function fragmentOf(html) {
    const template = document.createElement('template')
    template.innerHTML = html

    return template.content
  }

  // Its script starts the way the preview starts it — `webx.mount` on what came in (§10 of the
  // blocks spec); a script that arrives after it finds it on its own.
  function mount(nodes) {
    for (const node of nodes) {
      if (node.nodeType === 1) window.webx?.mount?.(node)
    }
  }

  // A block put on the page: in place of its range when the page has one, otherwise where its
  // neighbours say. Nowhere to put it is a page that does not show that part of the tree — it is
  // loaded again rather than left wrong.
  function insert(scope, message) {
    const id = idOf(scope, message.key)

    if (find(id)) {
      replace(id, message.html)

      return
    }

    const anchor = anchorOf(scope, message)

    if (anchor === null) {
      location.reload()

      return
    }

    const content = fragmentOf(message.html)
    const inserted = Array.from(content.childNodes)
    anchor.parent.insertBefore(content, anchor.before)
    mount(inserted)
    edit.drafts.add(id)
    scan()
    redraw()
  }

  // A block taken out of the page — removed, or hidden, which the site does not draw.
  function remove(id) {
    const block = find(id)

    if (block !== null) {
      for (const node of nodesOf(block)) node.remove()
    }

    edit.drafts.delete(id)
    if (edit.selected === id) edit.selected = null
    if (edit.highlight === id) edit.highlight = null
    scan()
    redraw()
  }

  // The same block somewhere else: its nodes carried over as they are, so whatever its script
  // set up goes with it. A block the page does not have is put in from the HTML instead.
  function move(scope, message) {
    const id = idOf(scope, message.key)
    const block = find(id)

    if (block === null || block.start.parentNode !== block.end.parentNode) {
      if (block !== null) remove(id)
      insert(scope, message)

      return
    }

    const nodes = nodesOf(block)
    const fragment = document.createDocumentFragment()
    fragment.append(...nodes)
    // The block is out of the page while its neighbours are looked for.
    scan()

    const anchor = anchorOf(scope, message)

    if (anchor === null) {
      location.reload()

      return
    }

    anchor.parent.insertBefore(fragment, anchor.before)
    edit.drafts.add(id)
    scan()
    redraw()
  }

  // The styles and the script of a block type the page did not have, added once: the page's set
  // was glued when it was printed (§9 of the blocks spec).
  function addAssets(message) {
    if (
      typeof message.css === 'string' &&
      !document.querySelector(`link[href="${CSS.escape(message.css)}"]`)
    ) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'
      link.href = message.css
      document.head.append(link)
    }

    if (
      typeof message.js === 'string' &&
      !document.querySelector(`script[src="${CSS.escape(message.js)}"]`)
    ) {
      const script = document.createElement('script')
      script.type = 'module'
      script.src = message.js
      document.body.append(script)
    }

    if (typeof message.hash === 'string') edit.bundles.add(message.hash)
  }

  // The sets the page has, for the panel to send with an edit: what is in them is not sent again.
  function bundles() {
    for (const element of document.querySelectorAll('link[href], script[src]')) {
      const found = BUNDLE.exec(element.getAttribute('href') ?? element.getAttribute('src') ?? '')
      if (found) edit.bundles.add(found[1])
    }

    return [...edit.bundles]
  }

  // A message names a block by its key and its tree; one without a tree is the only tree there is.
  function scopeFrom(message) {
    return typeof message.scope === 'string'
      ? message.scope
      : (edit.sources.keys().next().value ?? null)
  }

  function idFrom(message) {
    const scope = scopeFrom(message)

    return typeof message.key !== 'string' || scope === null ? null : idOf(scope, message.key)
  }

  // Frames follow their blocks: the page scrolls, the window changes, the page reflows when a
  // picture loads or a block is replaced.
  window.addEventListener('scroll', redraw, { capture: true, passive: true })
  window.addEventListener('resize', redraw, { passive: true })

  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(redraw).observe(document.body)
  }

  // A block that changes inside without the page changing size — a filter of questions, an open
  // accordion, a block just put in shifting the ones below — moves the frames too. Only while
  // there is a frame to move: the page of an editor reading their site costs nothing more.
  if (typeof MutationObserver === 'function') {
    new MutationObserver(() => {
      if (layer.childElementCount > 0) redraw()
    }).observe(document.body, {
      subtree: true,
      childList: true,
      attributes: true,
      characterData: true,
    })
  }

  // Esc on the page ends picking first and shuts the drawer the second time; inside the frame
  // the panel says the same over the bridge.
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || event.defaultPrevented) return

    if (edit.picking) {
      setPicking(false, true)
    } else if (state.open) {
      setOpen(false)
    }
  })

  // The bridge to the frame. Only the frame this script made, and only from this origin: any
  // other window on the page — an embedded video, an ad — can post messages too.
  window.addEventListener('message', (event) => {
    if (
      frame === null ||
      event.origin !== location.origin ||
      event.source !== frame.contentWindow
    ) {
      return
    }

    const message = event.data

    if (typeof message !== 'object' || message === null || message.wxBar !== 1) return

    if (message.type === 'close') {
      setOpen(false)
      tab.focus()
    }

    // The panel saved the page's title: shown in the browser's tab at once. Only the title —
    // the rest of `<head>` is the site's markup, and it is right again on the next load.
    if (message.type === 'title' && typeof message.title === 'string' && message.title !== '') {
      document.title = message.title
    }

    // The tab in front, for the frame of the next page to open on.
    if (message.type === 'tab' && typeof message.name === 'string') {
      state.tab = message.name
      writeState(state)
    }

    // Whose blocks the panel edits here — the record's, the regions' — and what each is called.
    // A panel of the release before named one record and no tree; it is read as that one tree.
    if (message.type === 'blocks') {
      const sources = Array.isArray(message.sources)
        ? message.sources
        : typeof message.entity === 'object' && message.entity !== null
          ? [{ scope: `${message.entity.type}:${message.entity.id}`, items: message.items }]
          : []

      edit.sources = new Map(
        sources
          .filter((source) => typeof source?.scope === 'string')
          .map((source) => [
            source.scope,
            {
              title: typeof source.title === 'string' ? source.title : null,
              labels: new Map(
                (Array.isArray(source.items) ? source.items : [])
                  .filter((item) => typeof item?.key === 'string')
                  .map((item) => [item.key, String(item.label ?? item.key)]),
              ),
            },
          ]),
      )
      scan()
      redraw()
      post({ type: 'bundles', hashes: bundles() })
      tellRegions()
    }

    if (message.type === 'mode') {
      setPicking(message.on === true, false)
    }

    if (message.type === 'select' || message.type === 'highlight') {
      const id = idFrom(message)

      edit[message.type === 'select' ? 'selected' : 'highlight'] = id
      if (id !== null) reveal(id)
      redraw()
    }

    if (message.type === 'replace' && typeof message.html === 'string') {
      const id = idFrom(message)

      if (id !== null) replace(id, message.html)
    }

    // The structure changed in the draft: a block put in, taken out or moved (SB5).
    if (message.type === 'assets') {
      addAssets(message)
      post({ type: 'bundles', hashes: bundles() })
    }

    if (
      (message.type === 'insert' || message.type === 'move') &&
      typeof message.html === 'string' &&
      idFrom(message) !== null
    ) {
      if (message.type === 'insert') insert(scopeFrom(message), message)
      else move(scopeFrom(message), message)
    }

    if (message.type === 'remove') {
      const id = idFrom(message)

      if (id !== null) remove(id)
    }

    if (message.type === 'reload') {
      location.reload()
    }
  })

  render()
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true })
} else {
  start()
}
