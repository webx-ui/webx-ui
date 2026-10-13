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
  font: 600 18px/1 var(--wx-font-family-sans);
  cursor: pointer;
  touch-action: none;
  user-select: none;
  transition: right var(--wx-duration-normal) var(--wx-easing-standard);
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
  const state = { open: false, top: null, ...readState() }

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

  const drawer = document.createElement('aside')
  drawer.className = 'drawer'
  drawer.id = 'wx-bar-drawer'
  drawer.setAttribute('aria-label', words.title ?? 'Site panel')

  shell.append(tab, drawer)
  root.append(style, shell)
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
    frame.src = `${src}?url=${encodeURIComponent(location.pathname)}`
    drawer.append(frame)
  }

  function render() {
    shell.classList.toggle('is-open', state.open)
    tab.textContent = state.open ? '›' : '‹'
    tab.setAttribute('aria-expanded', String(state.open))
    tab.setAttribute('aria-label', state.open ? (words.close ?? 'Close') : (words.open ?? 'Open'))

    if (typeof state.top === 'number') {
      host.style.setProperty('--wx-bar-top', `${Math.round(state.top * 100)}%`)
    }

    if (state.open) ensureFrame()
  }

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

  // Esc on the page shuts the drawer; inside the frame the panel says so over the bridge.
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && state.open && !event.defaultPrevented) {
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
  })

  render()
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true })
} else {
  start()
}
