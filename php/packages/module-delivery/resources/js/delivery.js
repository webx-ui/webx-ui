// The script of the delivery step (spec §12.3), over a step that works without it as one form.
//
// - Every change is written at once — `PUT` of the step's form address as JSON, the CSRF token of
//   the form in `X-CSRF-TOKEN` — and the step comes back drawn by the server (`X-Webx-Delivery-View:
//   step`): the methods and points of a new town, the block of a new method. The step is put in
//   place whole, its widgets let go and started again (`webx.unmount` / `webx.mount`).
// - The town is chosen in the dialog «Where to deliver the order?» (decision 40): the search of the
//   server is the list with a search of the widgets, the popular towns are buttons. A choice
//   closes the dialog.
// - The address is written as it is typed, after a pause, without drawing the step again (the
//   focus stays in the field); a refusal shows only under the fields already left.
// - After each change: `webx:delivery:changed` on the step (detail: the method, the cost) and, when
//   the owner asked (`data-checkout-event`), its event on `document` — the checkout redraws its
//   column of the totals and the payment step.
// - The step is a widget of the runtime (`webx.widget`): whoever puts a step drawn again in place
//   — the checkout after a sign-in redraws all its steps — starts it with `webx.mount`. Started
//   only at load, such a step stayed dead: no popular town, no search answered.

const STEP = '[data-webx-delivery]'
const ADDRESS_PAUSE = 600

const webx = () => (typeof window !== 'undefined' ? (window.webx ?? {}) : {})

/** The server, as a test replaces it. */
export const server = {
  put: (url, body, headers) =>
    fetch(url, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...headers },
      body: JSON.stringify(body),
    }).then(async (response) => ({
      status: response.status,
      body: await response.json().catch(() => ({})),
    })),
}

const token = (root) => root.querySelector('input[name="_token"]')?.value ?? ''

/** The field's name without the owner's prefix: `delivery[address][house]` → `address.house`. */
export function keyOf(name, prefix) {
  let rest = name
  if (prefix && rest.startsWith(`${prefix}[`)) rest = rest.slice(prefix.length)
  return rest.replace(/\[([^\]]*)\]/g, '.$1').replace(/^\./, '')
}

/** The address fields as the API takes them. */
export function addressOf(root) {
  const prefix = root.getAttribute('data-name')
  const address = {}
  for (const field of root.querySelectorAll('[data-delivery-address] [name]')) {
    const key = keyOf(field.getAttribute('name'), prefix)
    if (!key.startsWith('address.')) continue
    address[key.slice('address.'.length)] = field.value
  }
  return address
}

function showErrors(root, errors, only = null) {
  for (const holder of root.querySelectorAll('[data-delivery-error]')) {
    const key = holder.getAttribute('data-delivery-error')
    const message = errors?.[key]?.[0] ?? ''
    const shown = message !== '' && (only === null || only.has(key))
    holder.textContent = shown ? message : ''
    holder.hidden = !shown
  }
}

function announce(root, body) {
  const detail = {
    method: body?.selection?.method ?? null,
    cost: body?.cost ?? null,
    selection: body?.selection ?? null,
  }
  root.dispatchEvent(new CustomEvent('webx:delivery:changed', { bubbles: true, detail }))
  const event = root.getAttribute('data-checkout-event')
  if (event)
    document.dispatchEvent(new CustomEvent(event, { detail: { slot: 'delivery', ...detail } }))
}

/** Put the step drawn by the server in place of the old one; the focus goes back to its field. */
function swap(old, html) {
  const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector(STEP)
  if (!fresh) return old
  const focused =
    document.activeElement && old.contains(document.activeElement)
      ? document.activeElement.id
      : null
  webx().unmount?.(old)
  old.replaceWith(fresh)
  webx().mount?.(fresh)
  start(fresh)
  if (focused) document.getElementById(focused)?.focus?.()
  return fresh
}

export function start(root) {
  if (root.__webxDelivery) return root.__webxDelivery
  const form = root.querySelector('.webx-delivery__form')
  const url = root.getAttribute('data-selection')
  if (!form || !url) return null

  const prefix = root.getAttribute('data-name')
  let busy = Promise.resolve()
  let timer = null
  const touched = new Set()

  const put = (body, { redraw = true } = {}) => {
    busy = busy.then(async () => {
      root.classList.add('is-loading')
      const headers = { 'X-CSRF-TOKEN': token(root) }
      if (redraw) headers['X-Webx-Delivery-View'] = 'step'
      const event = root.getAttribute('data-checkout-event')
      if (event) headers['X-Webx-Delivery-Event'] = event
      try {
        const answer = await server.put(
          url,
          prefix ? { [prefix]: body, _prefix: prefix } : body,
          headers,
        )
        if (answer.status === 422) {
          showErrors(root, answer.body?.errors ?? {}, redraw ? null : touched)
          return
        }
        if (answer.status >= 400) throw new Error(String(answer.status))
        if (redraw && answer.body?.html) {
          root = swap(root, answer.body.html)
        } else {
          showErrors(root, {})
        }
        announce(root, answer.body)
      } catch {
        // A failed write leaves the step as it was; the form under it still works without us.
        root.classList.add('is-failed')
      } finally {
        root.classList.remove('is-loading')
      }
    })
    return busy
  }

  const field = (name) => keyOf(name ?? '', prefix)
  const dialog = root.querySelector('dialog')
  const closeDialog = () => {
    if (dialog?.open) dialog.close()
  }

  const onChange = (event) => {
    const target = event.target
    if (!(target instanceof Element) || !target.hasAttribute('name')) return
    const key = field(target.getAttribute('name'))

    if (target.closest('[data-delivery-locality]') && key === 'locality') {
      if (target.value === '') return
      closeDialog()
      put({ country: root.querySelector('[data-delivery-country]')?.value, locality: target.value })
      return
    }
    if (key === 'country') put({ country: target.value })
    else if (key === 'method') put({ method: target.value })
    else if (key === 'point') put({ point: target.value })
    else if (key.startsWith('address.')) {
      touched.add(key === 'address.street_id' ? 'address.street' : key)
      clearTimeout(timer)
      put({ address: addressOf(root) }, { redraw: false })
    }
  }

  const onInput = (event) => {
    const target = event.target
    if (!(target instanceof Element) || !target.closest('[data-delivery-address]')) return
    clearTimeout(timer)
    timer = setTimeout(() => put({ address: addressOf(root) }, { redraw: false }), ADDRESS_PAUSE)
  }

  const onFocusOut = (event) => {
    const target = event.target
    if (
      target instanceof Element &&
      target.closest('[data-delivery-address]') &&
      target.hasAttribute('name')
    ) {
      const key = field(target.getAttribute('name'))
      touched.add(key === 'address.street_id' ? 'address.street' : key)
    }
  }

  const onClick = (event) => {
    const town =
      event.target instanceof Element ? event.target.closest('[data-delivery-town]') : null
    if (!town || !root.contains(town)) return
    event.preventDefault()
    closeDialog()
    put({
      country: root.querySelector('[data-delivery-country]')?.value,
      locality: town.getAttribute('data-delivery-town'),
    })
  }

  // The form without JavaScript sends everything; with it, every change is sent already.
  const onSubmit = (event) => {
    event.preventDefault()
    put({ address: addressOf(root) })
  }

  root.addEventListener('change', onChange)
  root.addEventListener('input', onInput)
  root.addEventListener('focusout', onFocusOut)
  root.addEventListener('click', onClick)
  form.addEventListener('submit', onSubmit)
  root.classList.add('is-live')

  const api = {
    put,
    stop() {
      clearTimeout(timer)
      root.removeEventListener('change', onChange)
      root.removeEventListener('input', onInput)
      root.removeEventListener('focusout', onFocusOut)
      root.removeEventListener('click', onClick)
      form.removeEventListener('submit', onSubmit)
      delete root.__webxDelivery
    },
  }
  root.__webxDelivery = api
  return api
}

export function startAll(scope = document) {
  for (const root of scope.querySelectorAll(STEP)) start(root)
}

/** Registers the step with the runtime once it is there; true when it is. */
export function register() {
  const runtime = webx()
  if (typeof runtime.widget !== 'function') return false
  runtime.widget('delivery', STEP, (root) => {
    const api = start(root)
    return () => api?.stop()
  })
  return true
}

if (typeof document !== 'undefined') {
  // The runtime may be a module script printed after this one: the steps start on their own now,
  // and are handed to it once it runs.
  const boot = () => {
    startAll()
    if (!register()) window.addEventListener('load', register, { once: true })
  }
  if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', boot, { once: true })
  else boot()
}
