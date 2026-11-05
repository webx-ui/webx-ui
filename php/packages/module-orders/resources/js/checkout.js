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

const ROOT = '.webx-orders-checkout'
const STEP = '.webx-orders-checkout__step'
const SUMMARY = '#checkout-summary'
const PLACE = 'checkout-place'
// A field the buyer fills: not a hidden one, nor the honeypot of the antispam (§28).
const FIELD = 'input:not([type="hidden"], [tabindex="-1"]), select, textarea'

const webx = () => (typeof window !== 'undefined' ? (window.webx ?? {}) : {})

/** The page as the server prints it now; a test's stand-in (jsdom has no network). */
export const server = {
  fetch: (url) =>
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } }).then((r) =>
      r.ok ? r.text() : Promise.reject(new Error(String(r.status))),
    ),
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
  const placed = swap(root.querySelector(SUMMARY), fresh)
  sticky(root)
  return placed
}

/**
 * The page again: the steps named (all without names) and the column. A step the buyer is typing
 * in is left as it is — only its state and summary follow; a link or a button in focus is no
 * typing (the dialog of the sign-in gives the focus back to its opener).
 */
export async function redrawPage(root, keys = null) {
  const doc = parse(await server.fetch(window.location.href))
  for (const fresh of doc.querySelectorAll(STEP)) {
    const key = fresh.getAttribute('data-step')
    const old = root.querySelector(`${STEP}[data-step="${key}"]`)
    if (!old) continue
    const active = root.ownerDocument.activeElement
    const typing = active instanceof Element && active.matches(FIELD) && old.contains(active)
    if ((keys === null || keys.includes(key)) && !typing) {
      swap(old, fresh)
    } else {
      old.classList.toggle('is-done', fresh.classList.contains('is-done'))
      old.classList.toggle('is-open', fresh.classList.contains('is-open'))
      const summary = fresh.querySelector('.webx-orders-checkout__step-summary')
      const before = old.querySelector('.webx-orders-checkout__step-summary')
      if (summary && before) before.replaceWith(summary)
      else if (summary) old.querySelector('.webx-orders-checkout__step-head')?.after(summary)
    }
  }
  swap(root.querySelector(SUMMARY), doc.querySelector(SUMMARY))
  sticky(root)
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

  doc.addEventListener('webx:users:signed-in', (event) => {
    event.preventDefault()
    keep(redrawPage(root))
  })

  watchSticky(root)
}

if (typeof document !== 'undefined') start()
