// The script of the checkout (orders spec §27.3), on top of a page that works without it: every
// step its own form, sent in place by the shell of the widgets' forms (`webx:form:*`), the column
// of the totals with the confirmation beside them.
//
// - `data-steps="collapse"` on the root: «Change» opens the form of a step done in place, and a
//   step saved folds back into its fresh summary. At `open` the steps are left alone.
// - The column is redrawn whole after a step is saved, on `webx:checkout:slot-changed` (the
//   payment step too, after delivery), and on a 409 of the confirmation, so `expected_payable` is
//   the amount on the screen (§23.2, §24.2).
// - `webx:users:signed-in` is cancelled — the dialog does not reload the page — and the steps and
//   the column are redrawn: the cart joined the account's, prices may have changed (§25).
// - The sticky «To pay · Confirm» of a narrow container hides while the column's own button is
//   in sight or a field has the focus (the keyboard of a phone).
// - «Thank you» says `webx:checkout:placed` with the number, the amount and the currency (§30).
// - «Place the order» saves a step not done or typed in first, as its «Save» would, and goes on
//   after the redraw; a step refused keeps its errors at its fields and no order is sent.
// - «Order contents» only lists the lines (§23.5): its «Edit» and the warnings' «Open the cart» are
//   the cart's own `data-webx-cart-edit`, and the widget's dialog changes them. On its
//   `webx:cart:change` the slots' steps, the warnings and the column follow, and a slot's fields
//   the buyer changed go over by name: a slot writes as it is typed, but the server refuses a
//   half-done address, so the drawing has none of it. The last line gone, the page is the empty
//   cart's. The dialog's «Checkout» leads here: on this page it only closes the dialog.

const ROOT = '.webx-orders-checkout'
const STEP = '.webx-orders-checkout__step'
const SUMMARY = '#checkout-summary'
const LINES = '.webx-orders-checkout__lines'
const WARNINGS = '#checkout-warnings'
// The cart's own opener of its dialog (module-cart's script handles the click).
const EDIT = '[data-webx-cart-edit]'
const CART = 'webx-cart'
const PLACE = 'checkout-place'
// A field the buyer fills: not a hidden one, nor the honeypot of the antispam (§28).
const FIELD = 'input:not([type="hidden"], [tabindex="-1"]), select, textarea'
// A field with a value of its own. A choice of radios stays the server's: it resets what the cart
// no longer allows (§23.5).
const VALUE =
  'input:not([type="hidden"], [type="radio"], [type="checkbox"], [type="file"], [tabindex="-1"]), select:not([multiple]), textarea'

const webx = () => (typeof window !== 'undefined' ? (window.webx ?? {}) : {})

/** The page as the server prints it now; a test's stand-in (jsdom has no network). */
export const server = {
  fetch: (url) =>
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } }).then((r) =>
      r.ok ? r.text() : Promise.reject(new Error(String(r.status))),
    ),
  send: (url, body) =>
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body,
    }).then(async (r) => ({ status: r.status, body: await r.json().catch(() => ({})) })),
  reload: () => window.location.reload(),
}

const parse = (html) => new DOMParser().parseFromString(html, 'text/html')

/** Put `fresh` in place of `old`, the widgets inside it let go and started again. */
function swap(old, fresh) {
  if (!old || !fresh) return null
  webx().unmount?.(old)
  old.replaceWith(fresh)
  webx().mount?.(fresh)
  return fresh
}

/** A fresh column: «Order contents» open or closed as the buyer left it. */
function column(root, fresh) {
  const old = root.querySelector(SUMMARY)
  const lines = old?.querySelector(LINES)
  const placed = swap(old, fresh)
  if (!placed) return null
  const details = placed.querySelector(LINES)
  if (details && lines) details.open = lines.open
  return placed
}

function collapsing(root) {
  return root.getAttribute('data-steps') === 'collapse'
}

/** The sticky row's amount follows the column. */
function sticky(root) {
  const amount = root.querySelector(`${SUMMARY} .webx-orders-checkout__payable dd`)
  const target = root.querySelector('.webx-orders-checkout__sticky [data-payable]')
  if (amount && target) target.textContent = amount.textContent
}

/** The column alone, from `data-summary`. */
export async function redrawSummary(root) {
  const url = root.querySelector('[data-summary]')?.getAttribute('data-summary')
  if (!url) return null
  const fresh = parse(await server.fetch(url)).querySelector(SUMMARY)
  const placed = column(root, fresh)
  sticky(root)
  return placed
}

/**
 * The page again: the steps named (all without names) and the column. A step the buyer is typing
 * in is left as it is — only its state and summary follow; a link or a button in focus is no
 * typing (the dialog of the sign-in gives the focus back to its opener).
 */
export async function redrawPage(root, keys = null) {
  draw(root, parse(await server.fetch(window.location.href)), keys)
}

/** The value the server printed: a list without a `selected` shows its first option. */
function printed(field) {
  if (field.tagName !== 'SELECT') return field.defaultValue
  const options = Array.from(field.options)
  return (options.find((option) => option.defaultSelected) ?? options[0])?.value ?? ''
}

/**
 * What the buyer changed in a step, into its new drawing by name; a field the drawing no longer
 * has takes nothing along. An option found by the server's search is not among those printed.
 */
function carry(old, fresh) {
  const fields = Array.from(fresh.querySelectorAll(VALUE))
  for (const field of old.querySelectorAll(VALUE)) {
    const same = fields.find((el) => el.name === field.name)
    if (!field.name || !same || field.value === printed(field)) continue
    if (same.options && !Array.from(same.options).some((el) => el.value === field.value)) {
      same.append(new Option(field.selectedOptions?.[0]?.text ?? field.value, field.value))
    }
    same.value = field.value
  }
}

function draw(root, doc, keys, keep = false) {
  for (const fresh of doc.querySelectorAll(STEP)) {
    const key = fresh.getAttribute('data-step')
    const old = root.querySelector(`${STEP}[data-step="${key}"]`)
    if (!old) continue
    const active = root.ownerDocument.activeElement
    const typing = active instanceof Element && active.matches(FIELD) && old.contains(active)
    if ((keys === null || keys.includes(key)) && !typing) {
      if (old.classList.contains('is-editing')) fresh.classList.add('is-editing')
      if (keep) carry(old, fresh)
      swap(old, fresh)
    } else {
      const head = fresh.querySelector('.webx-orders-checkout__step-head')
      const title = old.querySelector('.webx-orders-checkout__step-head')
      if (head && title) title.textContent = head.textContent
      old.classList.toggle('is-done', fresh.classList.contains('is-done'))
      old.classList.toggle('is-open', fresh.classList.contains('is-open'))
      const summary = fresh.querySelector('.webx-orders-checkout__step-summary')
      const before = old.querySelector('.webx-orders-checkout__step-summary')
      if (summary && before) before.replaceWith(summary)
      else if (summary) old.querySelector('.webx-orders-checkout__step-head')?.after(summary)
    }
  }
  column(root, doc.querySelector(SUMMARY))
  sticky(root)
}

/** The steps the server lists now: what the cart no longer needs goes, what it needs comes in place. */
function followSteps(root, doc) {
  const fresh = Array.from(doc.querySelectorAll(STEP))
  const keys = fresh.map((step) => step.getAttribute('data-step'))
  for (const old of root.querySelectorAll(STEP)) {
    if (keys.includes(old.getAttribute('data-step'))) continue
    webx().unmount?.(old)
    old.remove()
  }
  let previous = null
  for (const step of fresh) {
    const key = step.getAttribute('data-step')
    let old = root.querySelector(`${STEP}[data-step="${key}"]`)
    if (!old) {
      old = step.cloneNode(true)
      if (previous) previous.after(old)
      else root.querySelector('.webx-orders-checkout__steps')?.prepend(old)
      webx().mount?.(old)
    }
    previous = old
  }
}

/** The lines the server warns about now: the list goes with the last of them. */
function followWarnings(root, doc) {
  const old = root.querySelector(WARNINGS)
  const fresh = doc.querySelector(WARNINGS)
  if (old && fresh) old.replaceWith(fresh)
  else if (old) old.remove()
  else if (fresh) root.querySelector('.webx-orders-checkout__title')?.after(fresh)
}

/**
 * The page after the cart changed (§23.5): delivery and payment count from the lines, so the
 * slots' steps are drawn again, with what the buyer typed in them; the buyer's other steps only
 * keep their state and number. The last line gone, the page is the empty cart's own.
 */
export async function redrawCart(root) {
  const doc = parse(await server.fetch(window.location.href))
  if (doc.querySelector(`${ROOT}.is-empty`)) {
    server.reload()
    return
  }
  followSteps(root, doc)
  followWarnings(root, doc)
  const slots = Array.from(doc.querySelectorAll(`${STEP}[data-slot]`))
  draw(
    root,
    doc,
    slots.map((step) => step.getAttribute('data-step')),
    true,
  )
}

/** The first step with an error of the confirmation: opened, its field in focus (§20.1). */
function towardError(root, errors) {
  for (const key of Object.keys(errors ?? {})) {
    const name = key.replace(/\.(\w+)/g, '[$1]')
    const control =
      root.querySelector(`[name="${key}"]`) ?? root.querySelector(`[name="${name}"]`) ?? null
    const step =
      control?.closest(STEP) ?? root.querySelector(`${STEP}[data-step="${key.split('.')[0]}"]`)
    if (!step) continue
    step.classList.add('is-editing')
    const target = control ?? step.querySelector(FIELD)
    target?.focus({ preventScroll: true })
    ;(target ?? step).scrollIntoView?.({ block: 'center', behavior: 'smooth' })
    return step
  }
  return null
}

/** A step's own form of the shell; a slot's saves itself (§22). */
function stepForm(step) {
  return (
    Array.from(step.querySelectorAll('form.webx-form')).find(
      (form) => form.closest(STEP) === step,
    ) ?? null
  )
}

/** The first step to save before the order: not done or typed in, not saved in this press. */
function unsaved(root, typed, passed) {
  for (const step of root.querySelectorAll(STEP)) {
    const key = step.getAttribute('data-step')
    const form = stepForm(step)
    if (!form || passed.has(key)) continue
    if (!step.classList.contains('is-done') || typed.has(form)) return { step, key, form }
  }
  return null
}

function reveal(step) {
  step.classList.add('is-editing')
  step.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
}

function say(form, message) {
  const box = form?.querySelector('.webx-form__error')
  if (!box || !message) return
  const line = document.createElement('p')
  line.className = 'webx-form__refusal'
  line.textContent = message
  const list = document.createElement('div')
  list.className = 'webx-form__refusals'
  list.append(line)
  box.replaceChildren(list)
}

function watchSticky(root) {
  const row = root.querySelector('.webx-orders-checkout__sticky')
  if (!row) return
  let seen = false
  let typing = false
  const update = () => row.classList.toggle('is-hidden', seen || typing)
  if (typeof IntersectionObserver === 'function') {
    const observer = new IntersectionObserver((entries) => {
      seen = entries.some((entry) => entry.isIntersecting)
      update()
    })
    const watch = () => {
      observer.disconnect()
      const button = root.querySelector(`${SUMMARY} .webx-form__submit`)
      if (button) observer.observe(button)
    }
    watch()
    root.addEventListener('webx:checkout:redrawn', watch)
  }
  root.addEventListener('focusin', (event) => {
    typing = event.target instanceof Element && event.target.matches('input, select, textarea')
    update()
  })
  root.addEventListener('focusout', () => {
    typing = false
    update()
  })
}

const started = new WeakSet()

export function start(doc = document) {
  if (started.has(doc)) return
  started.add(doc)

  const thanks = doc.querySelector('.webx-orders-thanks__analytics')
  if (thanks) {
    doc.dispatchEvent(
      new CustomEvent('webx:checkout:placed', {
        bubbles: true,
        detail: {
          number: thanks.getAttribute('data-number'),
          payable: Number(thanks.getAttribute('data-payable')),
          currency: thanks.getAttribute('data-currency'),
        },
      }),
    )
  }

  const root = doc.querySelector(ROOT)
  if (!root) return

  const redrawn = () => root.dispatchEvent(new CustomEvent('webx:checkout:redrawn'))
  const keep = (promise) =>
    promise.then(redrawn, () => {
      // The server did not answer: the page as it is still works, a reload shows the truth.
    })

  root.addEventListener('click', (event) => {
    const edit =
      event.target instanceof Element
        ? event.target.closest('.webx-orders-checkout__step-edit')
        : null
    if (!edit || !collapsing(root)) return
    event.preventDefault()
    const step = edit.closest(STEP)
    step?.classList.add('is-editing')
    step?.querySelector(FIELD)?.focus({ preventScroll: true })
  })

  // Typed in since drawn: a redrawn step is a new form.
  const typed = new WeakSet()
  const typing = (event) => {
    const form = event.target instanceof Element ? event.target.closest('form') : null
    if (form?.closest(STEP)) typed.add(form)
  }
  root.addEventListener('input', typing)
  root.addEventListener('change', typing)

  // Each step once a press: one the server keeps open must not go round in circles.
  let passed = null
  let pressing = null

  root.addEventListener('webx:form:submit', (event) => {
    const place = event.target instanceof Element ? event.target : null
    if (place?.id !== PLACE) return
    passed ??= new Set()
    const next = unsaved(root, typed, passed)
    if (!next) {
      passed = null
      return
    }
    event.preventDefault()
    passed.add(next.key)

    // The shell says `webx:form:submit` during the dispatch; silence — a field is wrong, said.
    let sent = false
    const went = () => (sent = true)
    next.form.addEventListener('webx:form:submit', went)
    next.form.requestSubmit()
    next.form.removeEventListener('webx:form:submit', went)
    if (!sent) {
      passed = null
      reveal(next.step)
      return
    }
    pressing = next.form
  })

  root.addEventListener('webx:form:success', (event) => {
    const form = event.target instanceof Element ? event.target : null
    const step = form?.closest(STEP)
    if (!step || event.detail?.response?.redirect) return
    step.classList.remove('is-editing')
    const redraw = keep(redrawPage(root, [step.getAttribute('data-step')]))
    if (pressing === form) {
      pressing = null
      redraw.then(() => root.querySelector(`#${PLACE}`)?.requestSubmit())
    }
  })

  root.addEventListener('webx:form:error', (event) => {
    const form = event.target instanceof Element ? event.target : null
    if (form && pressing === form) {
      pressing = null
      passed = null
      const step = form.closest(STEP)
      if (step) reveal(step)
      return
    }
    if (form?.id !== PLACE) return
    const { status, errors, message } = event.detail ?? {}
    if (status === 422) {
      towardError(root, errors)
      return
    }
    // 409 and the refusals: a new column — a new amount or key — with the reason under its button.
    if (status >= 400 && status !== 429) {
      keep(redrawSummary(root).then((summary) => say(summary?.querySelector(`#${PLACE}`), message)))
    }
  })

  const slotEvent = root.querySelector('[data-slot-event]')?.getAttribute('data-slot-event')
  if (slotEvent) {
    doc.addEventListener(slotEvent, (event) => {
      // Delivery changes what payment offers (§22.3); a part of the column changes only the column.
      // `redraw`: the slot changed by itself, and its own step, maybe folded, says the old choice.
      const { slot, redraw } = event.detail ?? {}
      const keys = slot === 'delivery' ? ['payment'] : redraw ? [slot] : []
      keep(keys.length ? redrawPage(root, keys) : redrawSummary(root))
    })
  }

  // The cart's own dialog changes the lines (§23.5): the page follows each write.
  doc.addEventListener('webx:cart:change', () => keep(redrawCart(root)))

  // Its «Checkout» leads to this very page: here it is the way back to the steps.
  doc.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null
    const link = target?.closest('.webx-cart__checkout')
    const dialog = link?.closest('dialog')
    if (!link || !dialog?.open) return
    const to = new URL(link.href, window.location.href)
    if (to.pathname !== window.location.pathname) return
    event.preventDefault()
    dialog.close()
  })

  // The link that opened the dialog was redrawn while it was open: the focus comes back to the
  // link of the same place, not to the page's start.
  let opener = null
  root.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest(EDIT) : null
    if (link) opener = link.closest(WARNINGS) ? `${WARNINGS} ${EDIT}` : `${SUMMARY} ${EDIT}`
  })
  // `close` does not bubble, but it is caught on its way down; the dialog may be the layout's.
  doc.addEventListener(
    'close',
    (event) => {
      if (!(event.target instanceof Element) || event.target.id !== CART || !opener) return
      const again = opener
      opener = null
      // After the dialog's own return of the focus, to an opener no longer on the page.
      setTimeout(() => {
        const active = doc.activeElement
        if (active && active !== doc.body && active.isConnected) return
        ;(root.querySelector(again) ?? root.querySelector(`${SUMMARY} ${EDIT}`))?.focus({
          preventScroll: true,
        })
      })
    },
    true,
  )

  doc.addEventListener('webx:users:signed-in', (event) => {
    event.preventDefault()
    keep(redrawPage(root))
  })

  watchSticky(root)
}

if (typeof document !== 'undefined') start()
