// The dialog of the sign-in for a checkout (spec §12.1). Three views in one dialog — the
// sign-in, «Forgot your password?», the registration — switched in place, the dialog's title
// going with them. An opener — `data-webx-sign-in="<view>"` on a link or a button, or a link to
// `#webx-sign-in` — opens it on its view; without this script the opener is a link to the page.
//
// A form of the dialog that signed the visitor in answers `visitor` and no redirect: the dialog
// closes, `webx:users:signed-in` goes to `document` with the name and whether the address is
// confirmed, and the page reloads — unless a listener cancelled the event to redraw itself, as a
// checkout does. «Forgot your password?» answers with its sentence in place, as any form in a
// dialog does (WIDGETS §17.3).

export const ID = 'webx-sign-in'

/** The reload after the event; a test's stand-in (jsdom cannot reload). */
export const navigation = { reload: () => window.location.reload() }

function show(dialog, name) {
  const views = [...dialog.querySelectorAll('[data-webx-sign-in-view]')]
  const view = views.find((el) => el.getAttribute('data-webx-sign-in-view') === name) ?? views[0]
  if (!view) return null
  for (const el of views) el.hidden = el !== view
  const title = dialog.querySelector('.webx-dialog__title')
  const words = view.getAttribute('data-webx-sign-in-title')
  if (title && words) title.textContent = words
  return view
}

function focusFirst(view) {
  view
    ?.querySelector('input:not([type="hidden"]), select, textarea')
    ?.focus({ preventScroll: true })
}

function open(dialog, opener, name) {
  const view = show(dialog, name)
  const webx = window.webx
  if (typeof webx?.openDialog === 'function') webx.openDialog(dialog, opener)
  else if (!dialog.open) dialog.showModal?.()
  focusFirst(view)
}

function signedIn(dialog, visitor) {
  dialog.close?.()
  const event = new CustomEvent('webx:users:signed-in', {
    bubbles: true,
    cancelable: true,
    detail: { name: visitor.name ?? '', verified: visitor.verified === true },
  })
  if (document.dispatchEvent(event)) navigation.reload()
}

const started = new WeakSet()

export function start(doc = document) {
  if (started.has(doc)) return
  started.add(doc)

  // On the document, the dialog found when it is needed: openers a script adds later work too,
  // and a page that swaps its dialog keeps its listeners.
  doc.addEventListener('click', (event) => {
    const dialog = doc.getElementById(ID)
    const target = event.target instanceof Element ? event.target : null
    if (!(dialog instanceof HTMLDialogElement) || !target) return

    const opener = target.closest('[data-webx-sign-in], a[href="#webx-sign-in"]')
    if (opener && !dialog.contains(opener)) {
      event.preventDefault()
      open(dialog, opener, opener.getAttribute('data-webx-sign-in') || 'sign-in')
      return
    }

    const link = target.closest('[data-webx-sign-in-show]')
    if (link && dialog.contains(link)) {
      event.preventDefault()
      focusFirst(show(dialog, link.getAttribute('data-webx-sign-in-show')))
    }
  })

  doc.addEventListener('webx:form:success', (event) => {
    const dialog = event.target instanceof Element ? event.target.closest('dialog') : null
    const visitor = event.detail?.response?.visitor
    if (dialog?.id === ID && visitor && typeof visitor === 'object') signedIn(dialog, visitor)
  })
}

if (typeof document !== 'undefined') start()
