/*
 * The debug bar (WEBX_UI_DEBUGBAR.md, §11 and §12).
 *
 * The one script the server puts on a page for an administrator with `debug.view`, never on a
 * visitor's and never in production. The tag carries the report's id and its summary, not the
 * report: the details are asked for from the panel's API, under the same session, the first time
 * the bar is opened (§9).
 *
 * Three states: a summary line along the bottom, a panel with tabs over the lower part of the
 * window (its height dragged by the top edge), and hidden — a dot in the corner that brings the
 * line back. Over the page, never beside it: a padding on `<body>` would break the site's fixed
 * footers. It draws in the shadow root of one element at the end of `<body>`, reads no cookie,
 * writes none, and keeps its state in `localStorage` under `wx-debug`.
 *
 * No framework and no dependencies: it runs on somebody else's page.
 */

// The panel's design tokens, rewritten for a shadow host. Filled in by the server, which serves
// this file (`WebxUi\Debug\Bar`); empty when the file is read straight from the disk.
const TOKENS = '' // tokens: written in when served

const KEY = 'wx-debug'
const LINE = 28
const MIN_HEIGHT = 120
// Below this width of the bar the line is cut down, the panel takes the whole window and rows
// become cards. The bar's own width rather than the window's: the drawer of the panel on the
// site may be taking part of it.
const NARROW = 640

const TABS = ['sql', 'timeline', 'blocks', 'cache', 'request', 'other', 'history']

const KEYWORDS =
  /\b(select|from|where|and|or|not|in|is|null|join|left|right|inner|outer|on|as|order|by|group|having|limit|offset|insert|into|values|update|set|delete|distinct|exists|between|like|union|all|case|when|then|else|end|asc|desc|count|begin|commit|rollback)\b/gi

const STYLE = `
:host {
  all: initial;
}

.bar {
  position: fixed;
  right: var(--wx-debug-right, 0px);
  bottom: 0;
  left: 0;
  z-index: calc(var(--wx-z-index-overlay) - 2);
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
  color: var(--wx-text-default);
  transition: right var(--wx-duration-normal);
}

*,
*::before,
*::after {
  box-sizing: border-box;
}

/* The hidden attribute loses to any display below without this. */
[hidden] {
  display: none !important;
}

button {
  all: unset;
  box-sizing: border-box;
  cursor: pointer;
}

button:focus-visible,
input:focus-visible,
a:focus-visible,
.grip:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: -2px;
}

/* The summary line, always at the bottom: in the open panel it is the button that closes it. */
.line {
  display: flex;
  gap: var(--wx-space-12);
  align-items: center;
  height: ${LINE}px;
  padding: 0 var(--wx-space-4) 0 var(--wx-space-12);
  overflow: hidden;
  font: var(--wx-font-size-xs) / 1 var(--wx-font-family-mono);
  white-space: nowrap;
  background: var(--wx-bg-surface);
  border-top: 1px solid var(--wx-border-default);
}

.summary {
  display: flex;
  flex: 1 1 auto;
  gap: var(--wx-space-12);
  align-items: center;
  min-width: 0;
  height: 100%;
  overflow: hidden;
}

.summary > span {
  flex: none;
}

.sep {
  color: var(--wx-border-strong);
}

.warn {
  color: var(--wx-color-warning);
}

.danger {
  color: var(--wx-color-danger);
}

.muted {
  color: var(--wx-text-muted);
}

.icon {
  display: inline-flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  color: var(--wx-text-muted);
  border-radius: var(--wx-radius-sm);
}

.icon:hover {
  color: var(--wx-text-strong);
  background: var(--wx-bg-fill-hover);
}

.icon svg {
  width: 14px;
  height: 14px;
}

.is-open .chevron svg {
  transform: rotate(180deg);
}

.is-narrow .wide,
.narrow-only {
  display: none;
}

.is-narrow .narrow-only {
  display: inline;
}

/* The open panel above the line. */
.panel {
  display: none;
  flex-direction: column;
  height: var(--wx-debug-height, 40vh);
  min-height: 0;
  background: var(--wx-bg-surface);
  border-top: 1px solid var(--wx-border-default);
  box-shadow: var(--wx-shadow-dialog);
}

.is-open .panel {
  display: flex;
}

.is-open .line {
  border-top-color: var(--wx-border-muted);
}

.is-narrow.is-open .panel {
  height: calc(100vh - ${LINE}px);
  height: calc(100dvh - ${LINE}px);
}

.grip {
  flex: none;
  height: 6px;
  margin-top: -3px;
  cursor: ns-resize;
  touch-action: none;
}

.grip:hover,
.grip.is-dragging {
  background: var(--wx-color-primary-soft);
}

.is-narrow .grip {
  display: none;
}

.head {
  display: flex;
  flex: none;
  gap: var(--wx-space-8);
  align-items: center;
  padding: 0 var(--wx-space-8);
  border-bottom: 1px solid var(--wx-border-muted);
}

.tabs {
  display: flex;
  flex: 1 1 auto;
  min-width: 0;
  overflow-x: auto;
  scrollbar-width: none;
}

.tab {
  flex: none;
  padding: var(--wx-space-8) var(--wx-space-10);
  color: var(--wx-text-muted);
  white-space: nowrap;
  border-bottom: 2px solid transparent;
}

.tab:hover {
  color: var(--wx-text-strong);
}

.tab[aria-selected='true'] {
  color: var(--wx-text-strong);
  border-bottom-color: var(--wx-color-primary);
}

.tab .count {
  margin-left: var(--wx-space-4);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}

.filter {
  flex: 0 1 200px;
  min-width: 80px;
  padding: var(--wx-space-4) var(--wx-space-8);
  font: inherit;
  color: var(--wx-text-default);
  background: var(--wx-bg-body);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
}

.body {
  flex: 1 1 auto;
  min-height: 0;
  /* Room on the right for the tab of the panel on the site, which stays over the bar. */
  padding: var(--wx-space-8) var(--wx-space-40) var(--wx-space-16) var(--wx-space-12);
  overflow: auto;
  overscroll-behavior: contain;
}

.note {
  margin: var(--wx-space-8) 0;
  color: var(--wx-text-muted);
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8) var(--wx-space-16);
  align-items: center;
  margin-bottom: var(--wx-space-8);
}

.chip {
  padding: var(--wx-space-2) var(--wx-space-8);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-default);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-full);
}

.chip:hover {
  background: var(--wx-bg-fill-hover);
}

.chip[aria-pressed='true'] {
  color: var(--wx-color-primary-contrast);
  background: var(--wx-color-primary);
  border-color: var(--wx-color-primary);
}

h3 {
  margin: var(--wx-space-16) 0 var(--wx-space-6);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
  color: var(--wx-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

h3:first-child {
  margin-top: var(--wx-space-4);
}

/* Rows: a grid on a wide bar, cards on a narrow one. */
.rows {
  display: grid;
  border-top: 1px solid var(--wx-border-muted);
}

.row {
  display: grid;
  grid-template-columns: var(--cols);
  gap: var(--wx-space-12);
  align-items: baseline;
  padding: var(--wx-space-6) 0;
  border-bottom: 1px solid var(--wx-border-muted);
}

.row > * {
  min-width: 0;
}

.is-narrow .row {
  grid-template-columns: 1fr auto;
  gap: var(--wx-space-2) var(--wx-space-8);
}

.is-narrow .row > .main {
  grid-column: 1 / -1;
}

.row.is-repeated {
  background: var(--wx-color-warning-soft);
}

.row.is-slow .time {
  font-weight: var(--wx-font-weight-semibold);
  color: var(--wx-color-danger);
}

.row.is-marker {
  font: var(--wx-font-size-xs) / 1.4 var(--wx-font-family-mono);
  color: var(--wx-text-muted);
}

.num,
.time {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  text-align: right;
  white-space: nowrap;
}

.is-narrow .num,
.is-narrow .time {
  text-align: left;
}

.code {
  margin: 0;
  font: var(--wx-font-size-xs) / 1.5 var(--wx-font-family-mono);
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.kw {
  font-weight: var(--wx-font-weight-semibold);
  color: var(--wx-color-primary);
}

.site {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

.actions {
  display: flex;
  gap: var(--wx-space-8);
  justify-content: flex-end;
}

.link {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-link);
  text-decoration: none;
  white-space: nowrap;
}

.link:hover {
  text-decoration: underline;
}

.badge {
  display: inline-block;
  padding: 0 var(--wx-space-6);
  margin-left: var(--wx-space-6);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-color-warning-contrast);
  white-space: nowrap;
  background: var(--wx-color-warning);
  border-radius: var(--wx-radius-full);
}

.badge.is-danger {
  color: var(--wx-color-danger-contrast);
  background: var(--wx-color-danger);
}

/* The timeline. */
.track {
  position: relative;
  height: 14px;
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-xs);
}

.span {
  position: absolute;
  top: 2px;
  bottom: 2px;
  min-width: 2px;
  background: var(--wx-color-primary);
  border-radius: var(--wx-radius-xs);
  opacity: 0.8;
}

.span.is-block {
  background: var(--wx-color-info);
}

.span.is-region {
  background: var(--wx-color-success);
}

.dot {
  position: absolute;
  top: 3px;
  width: 2px;
  height: 8px;
  background: var(--wx-color-warning);
}

.dot.is-slow {
  background: var(--wx-color-danger);
}

/* Key and value. */
.pairs {
  display: grid;
  grid-template-columns: minmax(120px, max-content) 1fr;
  gap: var(--wx-space-4) var(--wx-space-16);
  margin: 0;
}

.is-narrow .pairs {
  grid-template-columns: 1fr;
  gap: 0;
}

.pairs dt {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

.is-narrow .pairs dd {
  margin-bottom: var(--wx-space-6);
}

.pairs dd {
  margin: 0;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  overflow-wrap: anywhere;
}

.tree {
  padding-left: calc(var(--depth, 0) * var(--wx-space-16));
}

/* Hidden: a dot in the corner that brings the line back. */
.restore {
  position: fixed;
  bottom: var(--wx-space-8);
  left: var(--wx-space-8);
  z-index: calc(var(--wx-z-index-overlay) - 2);
  display: flex;
  align-items: center;
  justify-content: center;
  width: ${LINE}px;
  height: ${LINE}px;
  font: var(--wx-font-weight-bold) 11px / 1 var(--wx-font-family-mono);
  color: var(--wx-text-inverse);
  background: var(--wx-bg-inverse);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-md);
  opacity: 0.6;
}

.restore:hover {
  opacity: 1;
}

.restore.is-warn {
  background: var(--wx-color-warning);
}

.restore.is-danger {
  background: var(--wx-color-danger);
}
`

const ICONS = {
  up: '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 10.25 8 5.75l4.5 4.5"/></svg>',
  close:
    '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>',
}

function read(element) {
  try {
    return JSON.parse(element.dataset.wxDebug || '{}')
  } catch {
    return {}
  }
}

function remembered() {
  try {
    const state = JSON.parse(localStorage.getItem(KEY) || '{}')
    return state && typeof state === 'object' ? state : {}
  } catch {
    return {}
  }
}

function remember(state) {
  try {
    localStorage.setItem(KEY, JSON.stringify(state))
  } catch {
    // A private window: the bar starts as a line next time, which is the safe side.
  }
}

function el(tag, className, text) {
  const node = document.createElement(tag)
  if (className) node.className = className
  if (text !== undefined && text !== null) node.textContent = String(text)
  return node
}

function ms(value) {
  const number = Number(value) || 0
  return `${number >= 100 ? Math.round(number) : number.toFixed(number >= 10 ? 1 : 2)} ms`
}

/** The statement with its keywords picked out — text nodes only, never HTML from the report. */
function sql(text) {
  const pre = el('pre', 'code')
  let last = 0

  for (const match of text.matchAll(KEYWORDS)) {
    if (match.index > last) pre.append(text.slice(last, match.index))
    pre.append(el('span', 'kw', match[0]))
    last = match.index + match[0].length
  }

  if (last < text.length) pre.append(text.slice(last))

  return pre
}

async function copy(text) {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    // An address without https has no clipboard API: the old way, through a selection.
    const area = document.createElement('textarea')
    area.value = text
    area.style.cssText = 'position:fixed;opacity:0'
    document.body.append(area)
    area.select()
    let done = false
    try {
      done = document.execCommand('copy')
    } catch {
      done = false
    }
    area.remove()
    return done
  }
}

function draw(script) {
  const data = read(script)
  const summary = data.summary || {}
  const words = data.words || {}
  const w = (key, fallback) => words[key] || fallback

  const saved = remembered()
  const state = {
    // `hidden: true` is what the first version of the bar kept.
    mode: ['line', 'open', 'hidden'].includes(saved.mode)
      ? saved.mode
      : saved.hidden
        ? 'hidden'
        : 'line',
    height: typeof saved.height === 'number' ? saved.height : 0.4,
    tab: TABS.includes(saved.tab) ? saved.tab : 'sql',
    sort: saved.sort === 'time' ? 'time' : 'order',
  }

  const save = () =>
    remember({ mode: state.mode, height: state.height, tab: state.tab, sort: state.sort })

  const host = document.createElement('div')
  host.setAttribute('data-wx-debug-root', '')
  if (data.theme === 'light' || data.theme === 'dark') host.setAttribute('data-theme', data.theme)

  const root = host.attachShadow({ mode: 'open' })
  const style = document.createElement('style')
  style.textContent = TOKENS + STYLE
  root.append(style)

  const bar = el('div', 'bar')
  bar.setAttribute('role', 'region')
  bar.setAttribute('aria-label', w('title', 'Debug'))

  // The panel.
  const panel = el('div', 'panel')
  panel.id = 'wx-debug-panel'

  const grip = el('div', 'grip')
  grip.tabIndex = 0
  grip.setAttribute('role', 'separator')
  grip.setAttribute('aria-orientation', 'horizontal')
  grip.setAttribute('aria-label', w('resize', 'Resize the debug bar'))

  const head = el('div', 'head')
  const tabs = el('div', 'tabs')
  tabs.setAttribute('role', 'tablist')
  const filter = el('input', 'filter')
  filter.type = 'search'
  filter.placeholder = w('filter', 'Filter')
  filter.setAttribute('aria-label', w('filter', 'Filter'))
  head.append(tabs, filter)

  const body = el('div', 'body')
  body.setAttribute('role', 'tabpanel')
  body.tabIndex = 0
  panel.append(grip, head, body)

  // The line.
  const line = el('div', 'line')
  const toggle = el('button', 'summary')
  toggle.type = 'button'
  toggle.setAttribute('aria-controls', panel.id)

  const part = (text, tone) => toggle.append(el('span', tone || '', text))
  const sep = () => toggle.append(el('span', 'sep wide', '│'))
  part(`${w('queries', 'SQL')} ${summary.queries ?? 0} · ${ms(summary.sql_ms)}`)
  if (summary.repeated) part(`${w('repeated', 'Repeated')} ×${summary.repeated}`, 'warn wide')
  if (summary.slow) part(`${w('slow', 'Slow')} ${summary.slow}`, 'warn wide')
  sep()
  part(ms(summary.response_ms))
  sep()
  part(`${summary.memory_mb ?? 0} MB`, 'wide')
  if (summary.errors) {
    sep()
    part(`⚠ ${w('errors', 'Errors')} ${summary.errors}`, 'danger')
  } else if (summary.repeated || summary.slow) {
    part('⚠', 'warn narrow-only')
  }
  if (data.early === false) part(w('early', 'Early phase not recorded'), 'muted wide')

  const chevron = el('button', 'icon chevron')
  chevron.type = 'button'
  chevron.tabIndex = -1
  chevron.setAttribute('aria-hidden', 'true')
  chevron.innerHTML = ICONS.up

  const hide = el('button', 'icon')
  hide.type = 'button'
  hide.innerHTML = ICONS.close
  hide.setAttribute('aria-label', w('hide', 'Hide the debug bar'))
  hide.title = w('hide', 'Hide the debug bar')

  line.append(toggle, chevron, hide)
  bar.append(panel, line)

  // Hidden.
  const restore = el('button', 'restore', 'D')
  restore.type = 'button'
  restore.setAttribute('aria-label', w('show', 'Show the debug bar'))
  restore.title = w('show', 'Show the debug bar')
  if (summary.errors) restore.classList.add('is-danger')
  else if (summary.repeated || summary.slow) restore.classList.add('is-warn')

  root.append(bar, restore)
  document.body.append(host)

  // The report: asked for the first time the panel opens, kept for the life of the page.
  const reports = new Map()
  let shown = data.id
  let failure = null

  async function load(id) {
    if (reports.has(id)) return reports.get(id)

    const response = await fetch(`${data.api}/${encodeURIComponent(id)}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })

    if (response.status === 404) throw new Error('expired')
    if (!response.ok) throw new Error('failed')

    const report = (await response.json()).data
    reports.set(id, report)
    return report
  }

  let history = null

  async function loadHistory() {
    if (history !== null) return history

    const response = await fetch(data.api, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) throw new Error('failed')
    history = (await response.json()).data || []
    return history
  }

  // Layout: how high, how far from the right edge, how wide.
  function applyHeight() {
    const max = Math.max(MIN_HEIGHT, window.innerHeight * 0.9)
    const px = Math.min(Math.max(state.height * window.innerHeight, MIN_HEIGHT), max)
    bar.style.setProperty('--wx-debug-height', `${Math.round(px)}px`)
    grip.setAttribute('aria-valuenow', String(Math.round(px)))
  }

  function setRight(right) {
    const px = Number(right) > 0 ? Number(right) : 0
    bar.style.setProperty('--wx-debug-right', `${px}px`)
    measure()
  }

  function measure() {
    const right = parseFloat(bar.style.getPropertyValue('--wx-debug-right')) || 0
    bar.classList.toggle('is-narrow', window.innerWidth - right < NARROW)
  }

  // The panel on the site says how much of the right edge its drawer takes (§11.3) — when it
  // opens and shuts, and on its root for a drawer that was open before this bar started.
  window.addEventListener('wx-bar:layout', (event) => setRight(event.detail?.right))
  setRight(document.querySelector('[data-wx-bar-root]')?.getAttribute('data-open'))

  window.addEventListener('resize', () => {
    applyHeight()
    measure()
  })

  // Dragging the height by the top edge, with a mouse or a finger; arrows from the keyboard.
  let drag = null

  grip.addEventListener('pointerdown', (event) => {
    if (event.button !== 0) return
    drag = { id: event.pointerId }
    grip.setPointerCapture?.(event.pointerId)
    grip.classList.add('is-dragging')
    event.preventDefault()
  })

  grip.addEventListener('pointermove', (event) => {
    if (drag === null || event.pointerId !== drag.id) return
    state.height = (window.innerHeight - event.clientY - LINE) / window.innerHeight
    applyHeight()
  })

  function endDrag(event) {
    if (drag === null || event.pointerId !== drag.id) return
    drag = null
    grip.classList.remove('is-dragging')
    clampHeight()
    save()
  }

  grip.addEventListener('pointerup', endDrag)
  grip.addEventListener('pointercancel', endDrag)
  grip.addEventListener('keydown', (event) => {
    const step = 24 / window.innerHeight
    if (event.key === 'ArrowUp') state.height += step
    else if (event.key === 'ArrowDown') state.height -= step
    else return
    event.preventDefault()
    clampHeight()
    applyHeight()
    save()
  })

  // What is kept is what was shown: not a height the window could never give.
  function clampHeight() {
    const min = MIN_HEIGHT / window.innerHeight
    state.height = Math.min(Math.max(state.height, min), 0.9)
  }

  // The state.
  function render() {
    host.hidden = false
    bar.hidden = state.mode === 'hidden'
    restore.hidden = state.mode !== 'hidden'
    bar.classList.toggle('is-open', state.mode === 'open')
    toggle.setAttribute('aria-expanded', String(state.mode === 'open'))
    toggle.setAttribute(
      'aria-label',
      state.mode === 'open'
        ? w('collapse', 'Close the debug bar')
        : w('expand', 'Open the debug bar'),
    )
    toggle.title = toggle.getAttribute('aria-label')
    applyHeight()
    measure()

    if (state.mode === 'open') show()
  }

  function setMode(mode) {
    state.mode = mode
    save()
    render()
  }

  toggle.addEventListener('click', () => setMode(state.mode === 'open' ? 'line' : 'open'))
  chevron.addEventListener('click', () => setMode(state.mode === 'open' ? 'line' : 'open'))
  hide.addEventListener('click', () => setMode('hidden'))
  restore.addEventListener('click', () => setMode('line'))

  panel.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setMode('line')
      toggle.focus()
    }
  })

  filter.addEventListener('input', () => show())

  // The tabs.
  const buttons = new Map()

  for (const name of TABS) {
    const button = el('button', 'tab')
    button.type = 'button'
    button.setAttribute('role', 'tab')
    button.dataset.tab = name
    button.append(w(`tab_${name}`, name))
    button.addEventListener('click', () => {
      state.tab = name
      save()
      show()
    })
    buttons.set(name, button)
    tabs.append(button)
  }

  tabs.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return
    const at = TABS.indexOf(state.tab) + (event.key === 'ArrowRight' ? 1 : -1)
    state.tab = TABS[(at + TABS.length) % TABS.length]
    save()
    show()
    buttons.get(state.tab).focus()
  })

  function counts(report) {
    for (const [name, button] of buttons) {
      button.querySelector('.count')?.remove()
      const n = report ? countOf(name, report) : null
      if (n) {
        const badge = el('span', 'count', n)
        button.append(badge)
      }
    }
  }

  function countOf(name, report) {
    switch (name) {
      case 'sql':
        return report.summary?.queries || null
      case 'blocks':
        return countNodes(report.blocks || []) || null
      case 'other':
        return (
          (report.other?.http?.length || 0) +
            (report.other?.jobs?.length || 0) +
            (report.other?.mail?.length || 0) +
            (report.other?.log?.length || 0) || null
        )
      default:
        return null
    }
  }

  function countNodes(nodes) {
    return nodes.reduce((sum, node) => sum + 1 + countNodes(node.children || []), 0)
  }

  let showing = 0

  async function show() {
    const ticket = ++showing

    for (const [name, button] of buttons) {
      button.setAttribute('aria-selected', String(name === state.tab))
      button.tabIndex = name === state.tab ? 0 : -1
    }

    body.setAttribute('aria-label', w(`tab_${state.tab}`, state.tab))

    if (state.tab === 'history') {
      body.replaceChildren(el('p', 'note', w('loading', 'Loading the report…')))
      try {
        const list = await loadHistory()
        if (ticket === showing) body.replaceChildren(...historyTab(list))
      } catch {
        if (ticket === showing)
          body.replaceChildren(el('p', 'note', w('failed', 'Could not load the report.')))
      }
      return
    }

    if (!reports.has(shown))
      body.replaceChildren(el('p', 'note', w('loading', 'Loading the report…')))

    let report
    try {
      report = await load(shown)
      failure = null
    } catch (error) {
      failure = error.message
    }

    if (ticket !== showing) return

    if (failure !== null) {
      body.replaceChildren(
        el(
          'p',
          'note',
          failure === 'expired'
            ? w('expired', 'The report has expired. Reload the page.')
            : w('failed', 'Could not load the report.'),
        ),
      )
      return
    }

    counts(report)
    const query = filter.value.trim().toLowerCase()
    const nodes = []

    if (shown !== data.id) nodes.push(viewing(report))
    if (report.truncated)
      nodes.push(
        el(
          'p',
          'note warn',
          w('truncated', 'Some records were left out: the report reached its limits.'),
        ),
      )

    const tab = {
      sql: sqlTab,
      timeline: timelineTab,
      blocks: blocksTab,
      cache: cacheTab,
      request: requestTab,
      other: otherTab,
    }[state.tab]

    nodes.push(...tab(report, query))
    body.replaceChildren(...nodes)
  }

  const matches = (query, ...texts) =>
    query === '' ||
    texts.some((text) =>
      String(text ?? '')
        .toLowerCase()
        .includes(query),
    )

  const none = () => el('p', 'note', w('none', 'Nothing here.'))

  function viewing(report) {
    const head = el('div', 'toolbar')
    head.append(el('span', 'muted', `${report.method || 'GET'} ${report.url || ''}`))
    const back = el('button', 'chip', w('back', 'Back to this page'))
    back.type = 'button'
    back.addEventListener('click', () => {
      shown = data.id
      show()
    })
    head.append(back)
    return head
  }

  function rows(cols) {
    const list = el('div', 'rows')
    list.style.setProperty('--cols', cols)
    return list
  }

  function copyButton(label, text) {
    const button = el('button', 'link', label)
    button.type = 'button'
    button.addEventListener('click', async () => {
      if (await copy(text)) {
        button.textContent = w('copied', 'Copied')
        setTimeout(() => (button.textContent = label), 1200)
      }
    })
    return button
  }

  // SQL (§12.1).
  let focus = null
  // A stretch of the timeline the SQL tab is narrowed to: what ran while this span was open.
  let windowed = null

  function sqlTab(report, query) {
    const out = []
    const all = report.queries || []
    const slowMs = report.thresholds?.slow_ms ?? 50
    const repeated = report.repeated || []
    const likely = new Set()
    for (const group of repeated)
      if (group.likely) group.queries.forEach((index) => likely.add(index))

    const total = el('div', 'toolbar')
    const s = report.summary || {}
    const share = s.response_ms ? Math.round(((s.sql_ms || 0) / s.response_ms) * 100) : 0
    total.append(
      el('span', '', `${w('queries', 'SQL')} ${s.queries ?? 0}`),
      el('span', '', `${w('sql_time', 'SQL time')} ${ms(s.sql_ms)}`),
      el('span', 'muted', `${w('share', 'Of the response')} ${share}%`),
    )

    for (const [value, label] of [
      ['order', w('order', 'In order')],
      ['time', w('by_time', 'By time')],
    ]) {
      const chip = el('button', 'chip', label)
      chip.type = 'button'
      chip.setAttribute('aria-pressed', String(state.sort === value))
      chip.addEventListener('click', () => {
        state.sort = value
        save()
        show()
      })
      total.append(chip)
    }
    out.push(total)

    if (repeated.length > 0) {
      out.push(el('h3', '', w('shapes', 'Repeated statements')))
      const list = rows('minmax(0, 1fr) 64px 80px auto')

      for (const group of repeated) {
        const row = el('div', 'row' + (group.likely ? ' is-repeated' : ''))
        const main = el('div', 'main')
        main.append(sql(group.shape))
        const sites = Object.entries(group.sites || {})
          .map(([site, n]) => `${site} ×${n}`)
          .join(', ')
        if (sites) main.append(el('div', 'site', sites))
        if (group.in?.length) main.append(el('div', 'site', group.in.join(', ')))

        const count = el('span', 'num', `×${group.count}`)
        if (group.likely) count.append(el('span', 'badge', w('likely', 'Likely N+1')))

        const pick = el(
          'button',
          'chip',
          focus === group.shape ? w('show_all', 'Show all') : w('only_these', 'Show these'),
        )
        pick.type = 'button'
        pick.setAttribute('aria-pressed', String(focus === group.shape))
        pick.addEventListener('click', () => {
          focus = focus === group.shape ? null : group.shape
          show()
        })

        row.append(main, count, el('span', 'time', ms(group.time)), pick)
        list.append(row)
      }
      out.push(list)
    }

    out.push(el('h3', '', w('tab_sql', 'SQL')))

    if (windowed !== null) {
      const clear = el('button', 'chip', `${windowed.label} ×`)
      clear.type = 'button'
      clear.setAttribute('aria-pressed', 'true')
      clear.setAttribute('aria-label', w('show_all', 'Show all'))
      clear.addEventListener('click', () => {
        windowed = null
        show()
      })
      const bar = el('div', 'toolbar')
      bar.append(clear)
      out.push(bar)
    }

    const plain = focus === null && windowed === null && query === '' && state.sort === 'order'

    let shownQueries = all.filter((entry) => {
      if (entry.kind !== 'query') return plain
      if (focus !== null && entry.shape !== focus) return false
      if (windowed !== null && ((entry.at ?? 0) < windowed.from || (entry.at ?? 0) > windowed.to))
        return false
      return matches(query, entry.inline, entry.sql, entry.site, entry.in, entry.connection)
    })

    if (state.sort === 'time') {
      shownQueries = [...shownQueries].sort((a, b) => (b.time || 0) - (a.time || 0))
    }

    if (shownQueries.length === 0) {
      out.push(none())
      return out
    }

    const list = rows('40px minmax(0, 1fr) 72px auto')

    for (const entry of shownQueries) {
      if (entry.kind !== 'query') {
        const marker = el('div', 'row is-marker')
        marker.style.setProperty('--depth', entry.depth || 0)
        marker.append(
          el('span', ''),
          el('span', 'main tree', entry.kind.toUpperCase()),
          el('span', 'time', ms(entry.at)),
          el('span', ''),
        )
        list.append(marker)
        continue
      }

      const slow = (entry.time || 0) >= slowMs
      const row = el(
        'div',
        'row' + (likely.has(entry.index) ? ' is-repeated' : '') + (slow ? ' is-slow' : ''),
      )
      const main = el('div', 'main tree')
      main.style.setProperty('--depth', state.sort === 'order' ? entry.depth || 0 : 0)
      main.append(sql(entry.inline || entry.sql || ''))
      const where = [entry.site, entry.in, entry.connection].filter(Boolean).join(' · ')
      if (where) main.append(el('div', 'site', where))

      const actions = el('div', 'actions')
      actions.append(copyButton(w('copy', 'Copy'), entry.inline || entry.sql || ''))
      if (/^\s*select\b/i.test(entry.sql || '')) {
        actions.append(
          copyButton(w('copy_explain', 'Copy EXPLAIN'), `EXPLAIN ${entry.inline || entry.sql}`),
        )
      }

      row.append(
        el('span', 'num muted', `#${(entry.index ?? 0) + 1}`),
        main,
        el('span', 'time', ms(entry.time)),
        actions,
      )
      list.append(row)
    }

    out.push(list)
    return out
  }

  // Timeline (§12.2).
  function timelineTab(report, query) {
    const timeline = report.timeline || {}
    const total = Math.max(Number(timeline.total_ms) || 0, 0.01)
    const spans = timeline.spans || []
    const out = []

    const head = el('div', 'toolbar')
    head.append(
      el('span', '', `${w('response', 'Response')} ${ms(timeline.total_ms)}`),
      el('span', '', `${w('memory', 'Memory peak')} ${timeline.memory_peak_mb ?? 0} MB`),
      el('span', 'muted', `${w('collector', 'Collector cost')} ${ms(timeline.collector_ms)}`),
    )
    out.push(head)

    // The statements as marks along the whole request.
    const marks = el('div', 'track')
    marks.setAttribute('aria-label', w('tab_sql', 'SQL'))
    const slowMs = report.thresholds?.slow_ms ?? 50
    for (const entry of report.queries || []) {
      if (entry.kind !== 'query') continue
      const dot = el('span', 'dot' + ((entry.time || 0) >= slowMs ? ' is-slow' : ''))
      dot.style.left = `${Math.min(99.5, ((entry.at || 0) / total) * 100)}%`
      dot.title = `${ms(entry.time)} ${entry.sql || ''}`.slice(0, 200)
      marks.append(dot)
    }
    const markRow = el('div', 'row')
    markRow.style.setProperty('--cols', 'minmax(120px, 30%) minmax(0, 1fr) 72px 40px')
    markRow.append(
      el('span', 'site', w('tab_sql', 'SQL')),
      marks,
      el('span', 'time', ms(report.summary?.sql_ms)),
      el('span', 'num', report.summary?.queries ?? 0),
    )

    const list = rows('minmax(120px, 30%) minmax(0, 1fr) 72px 40px')
    list.append(markRow)

    const depth = new Map()
    for (const span of spans) {
      depth.set(
        span.id,
        span.parent === null || span.parent === undefined ? 0 : (depth.get(span.parent) ?? 0) + 1,
      )
      if (!matches(query, span.label, span.kind)) continue

      const row = el('div', 'row')
      const label = el('button', 'site tree', span.label)
      label.type = 'button'
      label.style.setProperty('--depth', depth.get(span.id))
      label.addEventListener('click', () => {
        // The statements carry the time they finished at: the window runs to the span's end.
        windowed = { label: span.label, from: span.start, to: span.start + span.ms }
        state.tab = 'sql'
        save()
        show()
      })
      const track = el('div', 'track')
      const piece = el(
        'span',
        'span' + (span.kind === 'block' ? ' is-block' : span.kind === 'region' ? ' is-region' : ''),
      )
      piece.style.left = `${Math.min(100, (span.start / total) * 100)}%`
      piece.style.width = `${Math.min(100, (span.ms / total) * 100)}%`
      track.append(piece)
      row.append(
        label,
        track,
        el('span', 'time', ms(span.ms)),
        el('span', 'num', span.queries || ''),
      )
      list.append(row)
    }

    out.push(list)
    return out
  }

  // Blocks and views (§12.3).
  function blocksTab(report, query) {
    const out = []
    const links = report.links || {}
    const list = rows('minmax(0, 1fr) 96px 72px auto')
    let any = false

    const walk = (node, level, region) => {
      const isRegion = node.kind === 'region'
      const name = isRegion ? node.name : node.type
      const inRegion = isRegion ? node.name : region

      if (matches(query, name, node.key, node.layer, node.source)) {
        any = true
        const row = el('div', 'row')
        const main = el('div', 'main tree')
        main.style.setProperty('--depth', level)
        const title = el('span', '', isRegion ? `▣ ${node.name}` : node.type)
        main.append(title)

        if (!isRegion && node.error)
          title.append(el('span', 'badge is-danger', w('block_failed', 'Failed')))

        const detail = isRegion
          ? [node.source]
          : [
              node.key,
              node.version ? `v${node.version}` : null,
              node.layer === null || node.layer === undefined
                ? null
                : node.layer === 'site'
                  ? w('site', 'site')
                  : node.layer,
            ]
        const text = detail.filter(Boolean).join(' · ')
        if (text) main.append(el('div', 'site', text))
        if (!isRegion && node.error) main.append(el('div', 'site danger', node.error))

        const own = `${node.self_queries ?? 0} / ${node.queries ?? 0}`
        const time = el('span', 'time', ms(node.ms))
        time.title = `${w('own', 'own')} ${ms(node.self_ms)}`

        const actions = el('div', 'actions')
        const href = inRegion
          ? links.region
            ? links.region + encodeURIComponent(inRegion)
            : null
          : links.page
        if (href) {
          const link = el('a', 'link', w('open_panel', 'Open in panel'))
          link.href = href
          link.target = '_blank'
          link.rel = 'noopener'
          actions.append(link)
        }

        const queries = el('span', 'num', own)
        queries.title = `${w('own', 'own')} / ${w('total', 'total')}`
        row.append(main, queries, time, actions)
        list.append(row)
      }

      for (const child of node.children || []) walk(child, level + 1, inRegion)
    }

    for (const node of report.blocks || []) walk(node, 0, null)

    out.push(any ? list : none())

    const views = (report.views || []).filter((view) =>
      matches(query, view.name, view.owner, view.file),
    )
    out.push(el('h3', '', w('views', 'Views')))

    if (views.length === 0) {
      out.push(none())
      return out
    }

    const table = rows('minmax(0, 1fr) minmax(0, 1fr) 40px')
    for (const view of views) {
      const row = el('div', 'row')
      const main = el('div', 'main')
      main.append(el('span', '', view.name))
      if (view.file) main.append(el('div', 'site', view.file))
      row.append(main, el('span', 'site', view.owner || ''), el('span', 'num', `×${view.count}`))
      table.append(row)
    }
    out.push(table)
    return out
  }

  // Cache (§12.4).
  function cacheTab(report, query) {
    const cache = report.cache || {}
    const out = []
    const c = cache.counts || {}

    const head = el('div', 'toolbar')
    head.append(
      el('span', '', `hit ${c.hit ?? 0}`),
      el('span', c.miss ? 'warn' : '', `miss ${c.miss ?? 0}`),
      el('span', 'muted', `write ${c.write ?? 0}`),
      el('span', 'muted', `forget ${c.forget ?? 0}`),
    )
    out.push(head)

    out.push(el('h3', '', w('groups', 'Groups')))
    const groups = (cache.groups || []).filter((group) =>
      matches(query, group.group, ...(group.entries || [])),
    )

    if (groups.length === 0) out.push(none())
    else {
      const list = rows('minmax(0, 1fr) 64px 64px 64px 64px')

      for (const group of groups) {
        const row = el('div', 'row' + (group.miss ? ' is-repeated' : ''))
        const main = el('div', 'main')
        main.append(el('span', '', group.group))
        if (group.entries?.length) main.append(el('div', 'site', group.entries.join(', ')))
        row.append(
          main,
          el('span', 'num', `${w('memo', 'Memo')} ${group.memo}`),
          el('span', 'num', `${w('store', 'Store')} ${group.store}`),
          el('span', 'num' + (group.miss ? ' warn' : ''), `${w('miss', 'Miss')} ${group.miss}`),
          el('span', 'num muted', `v${group.version ?? '–'}`),
        )
        list.append(row)
      }
      out.push(list)
    }

    out.push(el('h3', '', w('keys', 'Other keys')))
    const keys = (cache.keys || []).filter((key) => matches(query, key.key, key.type, key.store))

    if (keys.length === 0) out.push(none())
    else {
      const list = rows('64px minmax(0, 1fr) auto')
      for (const key of keys) {
        const row = el('div', 'row')
        row.append(
          el('span', 'site' + (key.type === 'miss' ? ' warn' : ''), key.type),
          el('span', 'main code', key.key),
          el('span', 'site', key.store || ''),
        )
        list.append(row)
      }
      out.push(list)
    }

    return out
  }

  function pairs(entries, query) {
    const list = el('dl', 'pairs')
    let any = false

    for (const [key, value] of entries) {
      const text = Array.isArray(value)
        ? value.join(', ')
        : value !== null && typeof value === 'object'
          ? JSON.stringify(value)
          : value === null || value === undefined || value === ''
            ? '—'
            : String(value)
      if (!matches(query, key, text)) continue
      any = true
      list.append(el('dt', '', key), el('dd', '', text))
    }

    return any ? list : none()
  }

  // Request (§12.5).
  function requestTab(report, query) {
    const request = report.request || {}
    const out = []

    const section = (title, entries) => {
      out.push(el('h3', '', title), pairs(entries, query))
    }

    section(w('page', 'Page'), [
      ['url', `${report.method || 'GET'} ${report.url || ''}`],
      ['status', request.status],
      ['view', request.view],
      [w('theme', 'Theme'), request.theme],
      [w('locale', 'Language'), request.locale],
      ['connection', request.connection],
      ['at', report.at],
    ])

    if (report.links?.page) {
      const link = el('a', 'link', w('open_panel', 'Open in panel'))
      link.href = report.links.page
      link.target = '_blank'
      link.rel = 'noopener'
      out.push(link)
    }

    section(w('route', 'Route'), Object.entries(request.route || {}))
    section(w('resolution', 'Resolver'), Object.entries(request.resolution || {}))
    section(w('session', 'Session'), Object.entries(request.session || {}))
    section(
      w('cookies', 'Cookies'),
      (request.cookies || []).map((name) => [name, '•']),
    )
    section(w('request_headers', 'Request headers'), Object.entries(request.request_headers || {}))
    section(
      w('response_headers', 'Response headers'),
      Object.entries(request.response_headers || {}),
    )

    return out
  }

  // Other (§12.6).
  function otherTab(report, query) {
    const other = report.other || {}
    const out = []

    const list = (title, items, describe) => {
      out.push(el('h3', '', title))
      const shown = items.map(describe).filter((parts) => matches(query, ...parts.flat()))
      if (shown.length === 0) {
        out.push(none())
        return
      }
      const table = rows('minmax(0, 1fr) auto 72px')
      for (const [main, side, at, tone] of shown) {
        const row = el('div', 'row')
        const cell = el('div', 'main')
        for (const [index, text] of [main].flat().entries()) {
          if (text)
            cell.append(el(index === 0 ? 'span' : 'div', index === 0 ? tone || '' : 'site', text))
        }
        row.append(
          cell,
          el('span', 'site', side ?? ''),
          el('span', 'time', at === null || at === undefined ? '' : ms(at)),
        )
        table.append(row)
      }
      out.push(table)
    }

    list(w('http', 'HTTP requests'), other.http || [], (call) => [
      [`${call.method} ${call.url}`],
      call.status ?? '',
      call.at,
      call.status >= 400 ? 'danger' : '',
    ])
    list(w('jobs', 'Jobs'), other.jobs || [], (job) => [
      [job.job, [job.connection, job.queue].filter(Boolean).join(' · ')],
      job.delay ? `+${job.delay}s` : '',
      job.at,
    ])
    list(w('mail', 'Mail'), other.mail || [], (mail) => [
      [mail.subject || '—', [].concat(mail.to || []).join(', ')],
      mail.mailer || '',
      mail.at,
    ])
    list(w('events', 'Events'), Object.entries(other.events || {}), ([name, count]) => [
      [name],
      `×${count}`,
      null,
    ])
    list(w('log', 'Log'), other.log || [], (line) => [
      [
        line.message,
        line.exception
          ? `${line.exception.class}: ${line.exception.message} — ${line.exception.at}`
          : null,
      ],
      line.level,
      line.at,
      ['error', 'critical', 'alert', 'emergency'].includes(line.level)
        ? 'danger'
        : line.level === 'warning' || line.message?.toLowerCase().includes('deprecat')
          ? 'warn'
          : '',
    ])

    return out
  }

  // History (§12.7).
  function historyTab(list) {
    const query = filter.value.trim().toLowerCase()
    const items = list.filter((item) => matches(query, item.url))
    if (items.length === 0) return [none()]

    const table = rows('minmax(0, 1fr) auto auto')
    for (const item of items) {
      const row = el('div', 'row')
      const main = el('div', 'main')
      const open = el('button', 'link', item.url || item.id)
      open.type = 'button'
      open.addEventListener('click', () => {
        shown = item.id
        state.tab = 'sql'
        save()
        show()
      })
      main.append(open)
      if (item.id === data.id) main.append(el('span', 'badge', w('current', 'This page')))
      if (item.at) main.append(el('div', 'site', item.at))
      const s = item.summary || {}
      row.append(
        main,
        el('span', 'num' + (s.repeated ? ' warn' : ''), `${w('queries', 'SQL')} ${s.queries ?? 0}`),
        el('span', 'time', ms(s.response_ms)),
      )
      table.append(row)
    }
    return [table]
  }

  render()
}

// A module script has no `document.currentScript`: the tag is found by its attribute.
const script = document.currentScript || document.querySelector('script[data-wx-debug]')

if (script && document.querySelector('[data-wx-debug-root]') === null) {
  draw(script)
}
