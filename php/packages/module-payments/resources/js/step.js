// The payment step's script (payments spec §10.2), on top of a form that works without it: what
// the list of methods chose (`methods.js`) is written at once with `PUT selection`, and the
// checkout hears of it on `document` (`data-checkout-event`) to redraw its column. A single method,
// shown chosen, is written once on load. A key of null is the list dropping a choice this device
// cannot pay with (groups spec §4.3): `DELETE selection`. The checkout redraws the whole step itself
// after the delivery changes.

const ROOT = '[data-webx-payments]'
const LIST = '[data-webx-payments-methods]'

const token = (form) => form.querySelector('input[name="_token"]')?.value ?? ''

/** `redraw`: the buyer did not do this, so a step folded on the old choice has to follow it. */
async function save(root, key, redraw = false) {
  const form = root.querySelector('form')
  root.classList.add('is-loading')
  try {
    const response = await fetch(root.getAttribute('data-selection'), {
      method: key === null ? 'DELETE' : 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token(form),
      },
      body: key === null ? undefined : JSON.stringify({ method: key }),
    })
    const body = await response.json().catch(() => ({}))
    if (!response.ok) {
      // A refused drop has no form to fall back on: nothing is chosen, and the page would only loop.
      if (key !== null) form.submit()
      return
    }
    const event = root.getAttribute('data-checkout-event')
    if (event) {
      document.dispatchEvent(
        new CustomEvent(event, {
          detail: { slot: 'payment', summary: body.summary ?? null, redraw },
        }),
      )
    }
  } finally {
    root.classList.remove('is-loading')
  }
}

function start(root) {
  if (root.classList.contains('is-live')) return
  root.classList.add('is-live')
  root.addEventListener('webx:payments:method', (event) => {
    const owed = 'owed' in event.target.dataset
    delete event.target.dataset.owed
    save(root, event.detail.key, owed)
  })
  // A site's copy of the step with its own radios has no list to speak for them.
  root.addEventListener('change', (event) => {
    const radio = event.target
    if (!(radio instanceof HTMLInputElement) || radio.type !== 'radio') return
    if (!radio.closest(LIST)) save(root, radio.value)
  })
  const single = root.querySelector('input[data-autosave]')
  if (single) save(root, single.value)
  // What the list said before this script listened: the two run in either order.
  const owed = root.querySelector(`${LIST}[data-owed]`)
  if (owed) {
    save(root, owed.dataset.owed || null, true)
    delete owed.dataset.owed
  }
}

export function startAll() {
  for (const root of document.querySelectorAll(ROOT)) start(root)
}

// The checkout swaps the step for a fresh one: a mutation brings the new root to life.
if (typeof document !== 'undefined') {
  startAll()
  new MutationObserver(startAll).observe(document.body, { childList: true, subtree: true })
}
