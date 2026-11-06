// The list of methods wherever it stands (groups spec §6.5), on top of markup that works without
// it: the radios and the opening of a group are the browser's and the stylesheet's. Choosing a
// group here chooses its first visible method, so there is never a group without a method (§6.3).
// Where Apple Pay cannot work, what needs it leaves the list (§4). What becomes of the choice is
// the page's: it hears `webx:payments:method` from the list, a key of null meaning «nothing chosen».
// Listening on `document`, it needs no waking when the checkout swaps the step for a fresh one.

const ROOT = '[data-webx-payments-methods]'
const GROUP = '.webx-payments__group'
const HEAD = '.webx-payments__group-head'
const SUBLIST = '.webx-payments__group-methods'
const OPTION = '.webx-payments__option'
// One radio a method: of a row under a head, or of the head of a group of one.
const METHOD = 'input[name="method"], input[data-method]'

/** Yes, no, or null for «the check did not work», which shows the method: a payment is not to lose. */
function applePay() {
  if (!('ApplePaySession' in window)) return false // Chrome on Android: the API is not there at all
  try {
    return window.ApplePaySession.canMakePayments()
  } catch {
    return null // an insecure page, a frame
  }
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

  const first = radio.closest(GROUP).querySelector('input[name="method"]')
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

function say(root, key) {
  root.dispatchEvent(new CustomEvent('webx:payments:method', { bubbles: true, detail: { key } }))
}

/** The one method left in a group speaks for it, as a group of one does from the server (§4.3). */
function collapse(group, option) {
  const head = group.querySelector(HEAD)
  head.querySelector('input').dataset.method = option.querySelector('input').value
  head
    .querySelector('.webx-payments__row-text')
    .replaceWith(option.querySelector('.webx-payments__row-text'))
  head.querySelector('.webx-payments__marks')?.remove()
  const marks = option.querySelector('.webx-payments__marks')
  if (marks) head.append(marks)
  const icon = option.querySelector('.webx-payments__icon')
  if (icon) head.querySelector('.webx-payments__pictogram')?.replaceWith(icon)
  group.querySelector(SUBLIST).remove()
}

/**
 * Takes away what needs Apple Pay (§4.3). Rows leave the page rather than hide, so that no radio of
 * theirs goes with the form and a theme's `display` cannot bring one back. Unless that is every
 * method: an empty step would take no order at all, so then nothing goes, not even a mark.
 */
function dropApplePay(root) {
  const only = root.querySelectorAll('[data-apple-pay-only]')
  if (only.length === root.querySelectorAll(METHOD).length) return

  for (const mark of root.querySelectorAll('[data-mark="apple-pay"]')) {
    const marks = mark.parentElement
    mark.remove()
    if (marks.children.length === 0) marks.remove()
  }

  const lost = root.querySelector('[data-apple-pay-only] input:checked')
  const scope = lost?.closest(GROUP) ?? root

  for (const row of only) (row.matches(HEAD) ? row.closest(GROUP) : row).remove()

  for (const group of root.querySelectorAll(GROUP)) {
    const options = group.querySelectorAll(OPTION)
    if (!group.querySelector(SUBLIST)) continue
    if (options.length === 0) group.remove()
    if (options.length === 1) collapse(group, options[0])
  }

  // undefined — the choice stands; null — it is gone and the group has nobody to take over.
  let owed
  if (lost) {
    const first = root.contains(scope) ? scope.querySelector(METHOD) : null
    if (first) first.checked = true
    owed = first ? settle(root, first) : null
  }

  // One group left has no head (decision 10), and one method left is chosen, as on load.
  const groups = root.querySelectorAll(GROUP)
  if (groups.length === 1 && groups[0].querySelector(SUBLIST)) {
    root.append(...groups[0].querySelectorAll(OPTION))
    groups[0].remove()
    root.classList.add('is-flat')
  }

  const left = root.querySelectorAll(METHOD)
  if (left.length === 1) {
    root.classList.add('is-single')
    if (!left[0].checked) {
      left[0].checked = true
      owed = settle(root, left[0])
    }
  }

  mark(root)
  if (owed !== undefined) {
    // Said on load, maybe before `step.js` listens: it reads this when it starts, and clears it.
    root.dataset.owed = owed ?? ''
    say(root, owed)
  }
}

const checked = new WeakSet()

/** Each list once: the check is synchronous and a redraw brings a fresh list, so nothing is kept. */
export function checkAll() {
  for (const root of document.querySelectorAll(ROOT)) {
    if (checked.has(root)) continue
    checked.add(root)
    if (applePay() === false) dropApplePay(root)
  }
}

if (typeof document !== 'undefined') {
  checkAll()
  new MutationObserver(checkAll).observe(document.body, { childList: true, subtree: true })

  // A form that goes to the server: the head of a group folded here still says the group, whose
  // first method on the server may be the one this device took away (§4.3, §6.4).
  document.addEventListener('formdata', (event) => {
    const head = event.target.querySelector(`${ROOT} input[data-method]:checked`)
    if (head) event.formData.set('method', head.dataset.method)
  })

  document.addEventListener('change', (event) => {
    const radio = event.target
    if (!(radio instanceof HTMLInputElement) || radio.type !== 'radio') return
    const root = radio.closest(ROOT)
    if (!root) return

    const key = settle(root, radio)
    mark(root)
    if (key) say(root, key)
  })
}
