// The list of methods wherever it stands (groups spec §6.5), on top of markup that works without
// it: the radios and the opening of a group are the browser's and the stylesheet's. Choosing a
// group here chooses its first visible method, so there is never a group without a method (§6.3).
// What becomes of the choice is the page's: it hears `webx:payments:method` from the list.
// Listening on `document`, it needs no waking when the checkout swaps the step for a fresh one.

const ROOT = '[data-webx-payments-methods]'
const GROUP = '.webx-payments__group'

const visible = (radio) => radio.closest('[hidden]') === null

function firstOf(group) {
  return [...group.querySelectorAll('input[name="method"]')].find(visible) ?? null
}

/** Settles the radios after this one changed; the key of the method they now say, or null. */
function settle(root, radio) {
  if (radio.name === 'method') {
    // Where the browser has no `:has()` every list is open, so a method can lead its group.
    const head = radio.closest(GROUP)?.querySelector('input[name="group"]')
    if (head) head.checked = true
    return radio.value
  }

  if (radio.dataset.method) {
    for (const method of root.querySelectorAll('input[name="method"]')) method.checked = false
    return radio.dataset.method
  }

  const first = firstOf(radio.closest(GROUP))
  if (first) first.checked = true
  return first?.value ?? null
}

function mark(root) {
  for (const group of root.querySelectorAll(GROUP)) {
    group.classList.toggle(
      'is-chosen',
      group.querySelector('input[name="group"]')?.checked === true,
    )
  }
  for (const option of root.querySelectorAll('.webx-payments__option')) {
    option.classList.toggle('is-chosen', option.querySelector('input')?.checked === true)
  }
}

if (typeof document !== 'undefined') {
  document.addEventListener('change', (event) => {
    const radio = event.target
    if (!(radio instanceof HTMLInputElement) || radio.type !== 'radio') return
    const root = radio.closest(ROOT)
    if (!root) return

    const key = settle(root, radio)
    mark(root)
    if (key) {
      root.dispatchEvent(
        new CustomEvent('webx:payments:method', { bubbles: true, detail: { key } }),
      )
    }
  })
}
