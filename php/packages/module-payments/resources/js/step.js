// The payment step's script (payments spec §10.2), on top of a form that works without it: what
// the list of methods chose (`methods.js`) is written at once with `PUT selection`, and the
// checkout hears of it on `document` (`data-checkout-event`) to redraw its column. A single method,
// shown chosen, is written once on load. The checkout redraws the whole step itself after the
// delivery changes.

const ROOT = '[data-webx-payments]'

const token = (form) => form.querySelector('input[name="_token"]')?.value ?? ''

async function save(root, key) {
  const form = root.querySelector('form')
  root.classList.add('is-loading')
  try {
    const response = await fetch(root.getAttribute('data-selection'), {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token(form),
      },
      body: JSON.stringify({ method: key }),
    })
    const body = await response.json().catch(() => ({}))
    if (!response.ok) {
      form.submit()
      return
    }
    const event = root.getAttribute('data-checkout-event')
    if (event) {
      document.dispatchEvent(
        new CustomEvent(event, { detail: { slot: 'payment', summary: body.summary ?? null } }),
      )
    }
  } finally {
    root.classList.remove('is-loading')
  }
}

function start(root) {
  if (root.classList.contains('is-live')) return
  root.classList.add('is-live')
  root.addEventListener('webx:payments:method', (event) => save(root, event.detail.key))
  const single = root.querySelector('input[data-autosave]')
  if (single) save(root, single.value)
}

function startAll() {
  for (const root of document.querySelectorAll(ROOT)) start(root)
}

// The checkout swaps the step for a fresh one: a mutation brings the new root to life.
if (typeof document !== 'undefined') {
  startAll()
  new MutationObserver(startAll).observe(document.body, { childList: true, subtree: true })
}
