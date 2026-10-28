/*
 * The cart's widget on the site (§9.4): the widget `cart` on `[data-webx-cart]`, the buy buttons
 * `[data-webx-cart-add]` by one listener on the document, and `window.webx.cart` for the rest of
 * the page — a bundle goes in as one batch through `webx.cart.add(items)` (decision 28).
 *
 * The server draws everything: the panels' HTML comes with the state when the widget asks with
 * `X-Webx-Cart-View` (the main view) and `X-Webx-Cart-Preview` (the mini cart), every sum is its
 * `*_formatted` string, and this only puts them in place — no template and no `Intl` here
 * (decision 32). After every write `webx:cart:change` goes out on the document with the state, and
 * the counters and the buttons' `is-in-cart` follow it.
 *
 * The main view — the drawer or the dialog — is where the lines change; a buy button and
 * `webx.cart.open()` always open it, never the mini cart (decision of 28.10.2026). The icon opens
 * the mini cart when there is one, and the main view on a narrow header or in the mobile menu. A
 * refused buy into a cart that is there (an item already at its maximum) answers with the cart:
 * it opens with the server's note in it, and nothing is said under the button.
 *
 * The panel of a drawer or a dialog is moved to the end of <body> once: the header prints its
 * actions twice — in the bar and at the bottom of the mobile menu — and the copy of the bar is
 * hidden on a phone, where a dialog inside it could not be shown. The second copy goes.
 *
 * It mounts through `webx.widget` of the widgets' runtime when the page has it, and on its own
 * otherwise; the runtime is a module script printed after this one, so it is waited for.
 */

const SELECTOR = '[data-webx-cart]'
const webx = (window.webx ??= {})

let last = null
let writes = 0
const timers = new Map()
let status = null
// The panel was just drawn from a write's answer: the dialog opening does not fetch it again — the
// note of a refusal is only in that answer.
let drawn = false

const root = () => document.querySelector(SELECTOR)
const data = (name) =>
  root()?.getAttribute(`data-webx-cart-${name}`) ??
  document.querySelector(`[data-webx-cart-${name}]`)?.getAttribute(`data-webx-cart-${name}`) ??
  null

function api() {
  const form = document.querySelector('form.webx-cart-add-form')
  return (
    data('api') ??
    form?.getAttribute('action')?.replace(/\/items\/?$/, '') ??
    '/api/cart'
  ).replace(/\/$/, '')
}

function cookie(name) {
  const found = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`))
  return found ? decodeURIComponent(found.slice(name.length + 1)) : null
}

/** One request of the API; a stale CSRF token (419) is fetched afresh and the request sent once more. */
async function send(method, path = '', body, again = true) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const lang = document.documentElement.lang
  const mode = root()?.getAttribute('data-webx-cart')
  const preview = root()?.getAttribute('data-webx-cart-preview')
  const token = cookie('XSRF-TOKEN')
  if (lang) headers['X-Webx-Locale'] = lang
  if (mode) headers['X-Webx-Cart-View'] = mode
  if (preview) headers['X-Webx-Cart-Preview'] = preview
  if (token) headers['X-XSRF-TOKEN'] = token
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  const response = await fetch(api() + path, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (response.status === 419 && again) {
    await fetch(data('csrf') ?? '/webx/widgets/csrf', { credentials: 'same-origin' })
    return send(method, path, body, false)
  }

  const answer = await response.json().catch(() => null)
  if (!response.ok) {
    const errors = Object.values(answer?.errors ?? {}).flat()
    const error = new Error(errors[0] ?? answer?.message ?? response.statusText)
    error.status = response.status
    error.errors = answer?.errors ?? {}
    error.answer = answer
    throw error
  }
  return answer
}

/** Every `type:ref` the cart holds, children too: the buttons' `is-in-cart`. */
function held(lines, into = new Set()) {
  for (const line of lines) {
    if (line.source === null) into.add(`${line.type}:${line.ref}`)
    held(line.children ?? [], into)
  }
  return into
}

/** The main view's panels (`main`), or the mini cart's (`preview`). */
function bodies(which) {
  return document.querySelectorAll(
    which === 'preview'
      ? '[data-webx-cart-body="preview"]'
      : '[data-webx-cart-body]:not([data-webx-cart-body="preview"])',
  )
}

/** Puts the server's HTML in place, keeping the focus on the same control of the same line. */
function draw(html, which) {
  const active = document.activeElement
  const inside = active?.closest?.('[data-webx-cart-body]')
  const line = active?.closest?.('[data-webx-cart-line]')?.getAttribute('data-webx-cart-line')
  const control = ['data-webx-cart-step', 'data-webx-cart-remove', 'data-webx-cart-qty-input']
    .map((name) =>
      active?.hasAttribute?.(name) ? `[${name}="${active.getAttribute(name)}"]` : null,
    )
    .find(Boolean)

  for (const body of bodies(which)) {
    webx.unmount?.(body)
    body.innerHTML = html
    body.removeAttribute('aria-busy')
    webx.mount?.(body)
  }

  if (!inside) return
  const again = control
    ? [...inside.querySelectorAll('[data-webx-cart-line]')]
        .find((el) => el.getAttribute('data-webx-cart-line') === line)
        ?.querySelector(control)
    : null
  const dialog = inside.closest('dialog, details')
  ;(again ?? dialog?.querySelector('[data-webx-dialog-close], summary') ?? inside).focus?.()
}

function apply(answer, write) {
  if (!answer) return answer
  const { html, preview_html: preview, added, ...state } = answer
  // A refusal's answer carries the cart too: its words are not the state.
  delete state.message
  delete state.errors
  last = state

  for (const count of document.querySelectorAll('[data-webx-cart-count]')) {
    count.textContent = String(state.units)
    count.classList.toggle('is-empty', state.units === 0)
  }
  const keys = held(state.lines ?? [])
  for (const button of document.querySelectorAll('[data-webx-cart-add]')) {
    button.classList.toggle('is-in-cart', keys.has(button.getAttribute('data-webx-cart-add')))
  }
  if (typeof html === 'string') draw(html)
  if (typeof preview === 'string') draw(preview, 'preview')

  if (write)
    document.dispatchEvent(new CustomEvent('webx:cart:change', { detail: { state, added } }))
  return state
}

/** A write: the answer of a write sent before a newer one is not drawn — the newer one will be. */
async function write(method, path, body) {
  const mine = ++writes
  const answer = await send(method, path, body)
  return mine === writes ? apply(answer, true) : answer
}

async function load() {
  for (const body of document.querySelectorAll('[data-webx-cart-body]'))
    body.setAttribute('aria-busy', 'true')
  return apply(await send('GET'), false)
}

function announce(text) {
  if (!status) {
    status = document.createElement('p')
    status.setAttribute('role', 'status')
    // Read, not seen: the button itself shows that it worked.
    status.style.cssText =
      'position:absolute;width:1px;height:1px;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap'
    document.body.append(status)
  }
  status.textContent = ''
  setTimeout(() => (status.textContent = text), 50)
}

/** The main view, whatever the icon opens: the mini cart closes first. `fresh` — just drawn. */
function open(fresh = false) {
  const el = root()
  if (!el) return
  const details = el.querySelector('details')
  if (details) details.open = false
  const dialog = document.getElementById(el.getAttribute('data-webx-cart-panel') ?? '')
  drawn = fresh && !!dialog && !dialog.open
  if (dialog instanceof HTMLDialogElement && typeof webx.openDialog === 'function') {
    webx.openDialog(dialog, el.querySelector('.webx-cart__trigger'))
  } else if (dialog) {
    location.hash = dialog.id
  }
}

const cart = {
  add: (items) => write('POST', '/items', { items }),
  remove: (id) => write('DELETE', `/items/${encodeURIComponent(id)}`),
  setQty: (id, qty) => write('PATCH', `/items/${encodeURIComponent(id)}`, { qty }),
  open: () => open(),
  state: () => (last ? Promise.resolve(last) : load()),
}

/** A buy button: one line, in place; the panel opens after it unless the button says not to. */
async function buy(button) {
  if (button.classList.contains('is-busy')) return
  const [type, ref] = (button.getAttribute('data-webx-cart-add') ?? '').split(/:(?=[^:]*$)/)
  const from = button.getAttribute('data-webx-cart-qty-from')
  const field = from ? document.querySelector(from) : null
  const qty = Number(field?.value || button.getAttribute('data-webx-cart-qty') || 1)
  const options = button.getAttribute('data-webx-cart-options')
  const alert = button.parentElement?.querySelector('.webx-cart-add__error')

  button.classList.add('is-busy')
  button.setAttribute('aria-busy', 'true')
  if (alert) alert.hidden = true
  try {
    await cart.add([
      { type, ref: Number(ref), qty, ...(options ? { options: JSON.parse(options) } : {}) },
    ])
    button.classList.add('is-added')
    setTimeout(() => button.classList.remove('is-added'), 2000)
    announce(button.getAttribute('data-webx-cart-added') ?? data('added') ?? 'Added to cart')
    if (button.getAttribute('data-webx-cart-open') !== 'false') open(true)
  } catch (error) {
    const text = error.status === 422 ? error.message : (data('error') ?? error.message)
    if (error.status === 422 && Array.isArray(error.answer?.lines) && root()) {
      // The cart is there and says why in its panel — the line at its maximum, where it can change.
      apply(error.answer, false)
      open(true)
    } else if (alert) {
      alert.textContent = text
      alert.hidden = false
    } else {
      announce(text)
    }
  } finally {
    button.classList.remove('is-busy')
    button.removeAttribute('aria-busy')
  }
}

/** The stepper: the number changes at once, the request goes 300 ms after the last press. */
function step(line, qty) {
  const id = line.getAttribute('data-webx-cart-line')
  clearTimeout(timers.get(id))
  timers.set(
    id,
    setTimeout(() => {
      timers.delete(id)
      cart.setQty(id, qty).catch((error) => announce(error.message))
    }, 300),
  )
}

function clamp(input, value) {
  const max = Number(input.max) || Infinity
  return Math.min(Math.max(1, Math.round(value) || 1), max)
}

/** A header too narrow for the mini cart, or the mobile menu: the icon opens the main view. */
const narrow = (el) =>
  !!el.closest('.webx-mobile-menu') ||
  (el.closest('.webx-header') ?? document.documentElement).clientWidth < 640

// Before the dropdown's own listener on its trigger, which would open the mini cart.
document.addEventListener(
  'click',
  (event) => {
    const target = event.target instanceof Element ? event.target : null
    const trigger = target?.closest('[data-webx-cart-preview] summary')
    if (trigger && narrow(trigger)) {
      event.preventDefault()
      event.stopPropagation()
      open()
    }
  },
  true,
)

document.addEventListener('click', (event) => {
  const target = event.target instanceof Element ? event.target : null
  if (target?.closest('[data-webx-cart-edit]')) {
    event.preventDefault()
    open()
    return
  }

  const button = target?.closest('[data-webx-cart-add]')
  if (button) {
    event.preventDefault()
    buy(button)
    return
  }

  const remove = target?.closest('[data-webx-cart-remove]')
  if (remove) {
    cart
      .remove(remove.getAttribute('data-webx-cart-remove'))
      .catch((error) => announce(error.message))
    return
  }

  const by = target?.closest('[data-webx-cart-step]')
  const line = by?.closest('[data-webx-cart-line]')
  const input = line?.querySelector('[data-webx-cart-qty-input]')
  if (by && input) {
    input.value = String(
      clamp(input, Number(input.value) + Number(by.getAttribute('data-webx-cart-step'))),
    )
    step(line, Number(input.value))
  }
})

document.addEventListener('change', (event) => {
  const input =
    event.target instanceof Element ? event.target.closest('[data-webx-cart-qty-input]') : null
  const line = input?.closest('[data-webx-cart-line]')
  if (!line) return
  input.value = String(clamp(input, Number(input.value)))
  step(line, Number(input.value))
})

// A page from the back-forward cache shows the counter of when it was left.
window.addEventListener('pageshow', (event) => {
  if (event.persisted && root()) load().catch(() => {})
})

const watched = new WeakSet()

function setup(el) {
  const details = el.querySelector('details')
  const toggle = () => details.open && load().catch(() => {})
  details?.addEventListener('toggle', toggle)
  const off = () => details?.removeEventListener('toggle', toggle)

  const id = el.getAttribute('data-webx-cart-panel')
  const dialog = [...el.querySelectorAll('dialog')].find((child) => child.id === id)
  if (!dialog) return off
  const placed = [...document.body.children].find((child) => child.id === id && child !== dialog)
  if (placed) {
    dialog.remove()
    return off
  }
  document.body.append(dialog)
  if (watched.has(dialog)) return off
  watched.add(dialog)

  // However it opens — the trigger, a link to #webx-cart, webx.cart.open() — the panel is fetched.
  new MutationObserver(() => {
    if (!dialog.open) return
    if (drawn) drawn = false
    else load().catch(() => {})
  }).observe(dialog, {
    attributes: true,
    attributeFilter: ['open'],
  })

  // The page came back from a buy button without the script: the open panel becomes a modal.
  if (dialog.open && dialog.querySelector('[data-webx-returned]') && webx.openDialog) {
    dialog.close()
    webx.openDialog(dialog, el.querySelector('.webx-cart__trigger'))
  } else if (dialog.open) {
    load().catch(() => {})
  }
  return off
}

webx.cart = cart

function start() {
  if (typeof webx.widget === 'function') {
    webx.widget('cart', SELECTOR, setup)
    return
  }
  for (const el of document.querySelectorAll(SELECTOR)) setup(el)
}

if (typeof webx.widget === 'function') {
  start()
} else {
  let started = false
  const once = () => {
    if (started) return
    started = true
    start()
  }
  if (document.readyState === 'complete') once()
  else {
    document.addEventListener('DOMContentLoaded', once, { once: true })
    window.addEventListener('load', once, { once: true })
  }
}
