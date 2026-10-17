/*
 * The debug bar (WEBX_UI_DEBUGBAR.md, §11).
 *
 * The one script the server puts on a page for an administrator with `debug.view`, never on a
 * visitor's and never in production. The tag carries the report's id and its summary, not the
 * report: the details are asked for from the panel's API, under the same session, when the bar
 * is opened (§9).
 *
 * This is the summary line only — the tabs, the drag of the height and the neighbourhood of the
 * panel on the site are the next stage of the work (DB3). It draws in the shadow root of one
 * element at the end of `<body>`, reads no cookie, writes none, and keeps whether it is hidden
 * in `localStorage` under `wx-debug`.
 *
 * No framework and no dependencies: it runs on somebody else's page.
 */

// The panel's design tokens, rewritten for a shadow host. Filled in by the server, which serves
// this file (`WebxUi\Debug\Bar`); empty when the file is read straight from the disk.
const TOKENS = '' // tokens: written in when served

const KEY = 'wx-debug'

const STYLE = `
:host {
  all: initial;
}

.line {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: calc(var(--wx-z-index-overlay) - 2);
  display: flex;
  gap: var(--wx-space-12);
  align-items: center;
  box-sizing: border-box;
  height: 28px;
  padding: 0 var(--wx-space-12);
  overflow: hidden;
  font: 12px/1 var(--wx-font-family-mono, ui-monospace, monospace);
  color: var(--wx-text-default);
  white-space: nowrap;
  background: var(--wx-bg-surface);
  border-top: 1px solid var(--wx-border-default);
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

button {
  all: unset;
  margin-left: auto;
  cursor: pointer;
}
`

function read(element) {
  try {
    return JSON.parse(element.dataset.wxDebug || '{}')
  } catch {
    return {}
  }
}

function remembered() {
  try {
    return JSON.parse(localStorage.getItem(KEY) || '{}')
  } catch {
    return {}
  }
}

function remember(state) {
  try {
    localStorage.setItem(KEY, JSON.stringify(state))
  } catch {
    // A private window: the bar is shown again next time, which is the safe side.
  }
}

function span(text, tone) {
  const node = document.createElement('span')
  node.textContent = text
  if (tone) node.className = tone
  return node
}

function draw(script) {
  const data = read(script)
  const summary = data.summary || {}
  const words = data.words || {}
  const host = document.createElement('div')
  host.setAttribute('data-wx-debug-root', '')
  if (data.theme) host.setAttribute('data-theme', data.theme)

  const root = host.attachShadow({ mode: 'open' })
  const style = document.createElement('style')
  style.textContent = TOKENS + STYLE
  root.append(style)

  const line = document.createElement('div')
  line.className = 'line'
  line.setAttribute('role', 'status')
  line.setAttribute('aria-label', words.title || 'Debug')

  line.append(span(`${words.queries || 'SQL'} ${summary.queries ?? 0} · ${summary.sql_ms ?? 0} ms`))
  if (summary.repeated)
    line.append(span(`×${summary.repeated} ${words.repeated || 'repeated'}`, 'warn'))
  if (summary.slow) line.append(span(`${summary.slow} ${words.slow || 'slow'}`, 'warn'))
  line.append(span(`${summary.response_ms ?? 0} ms`))
  line.append(span(`${summary.memory_mb ?? 0} MB`))
  if (summary.errors) line.append(span(`⚠ ${summary.errors} ${words.errors || 'errors'}`, 'danger'))
  if (data.early === false) line.append(span(words.early || 'Early phase not recorded', 'muted'))

  const hide = document.createElement('button')
  hide.type = 'button'
  hide.textContent = '×'
  hide.setAttribute('aria-label', words.hide || 'Hide the debug bar')
  hide.addEventListener('click', () => {
    remember({ ...remembered(), hidden: true })
    host.remove()
  })
  line.append(hide)

  root.append(line)
  document.body.append(host)
}

const script = document.currentScript || document.querySelector('script[data-wx-debug]')

if (script && !remembered().hidden) {
  draw(script)
}
