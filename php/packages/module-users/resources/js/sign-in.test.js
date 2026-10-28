import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { navigation, start } from './sign-in.js'

/*
 * sign-in.js over the markup of the dialog (spec §12.1): an opener opens it on its view, the links
 * inside switch the views and the title without leaving the page, and a form that signed the
 * visitor in closes it, says `webx:users:signed-in` and reloads the page — unless a listener
 * cancelled the event to redraw the step itself.
 */

const page = () => {
  document.body.innerHTML = `
    <a href="/account/sign-in?back=%2Fcheckout" data-webx-sign-in="sign-in">Sign in</a>
    <button type="button" data-webx-sign-in="register">Register</button>
    <a href="#webx-sign-in" id="plain">Sign in</a>
    <dialog id="webx-sign-in" class="webx-dialog">
      <h2 class="webx-dialog__title">Sign in</h2>
      <section data-webx-sign-in-view="sign-in" data-webx-sign-in-title="Sign in">
        <form id="webx-sign-in-form"><input type="hidden" name="back"><input name="email"></form>
        <a href="/account/forgot" data-webx-sign-in-show="forgot">Forgot your password?</a>
        <a href="/account/register" data-webx-sign-in-show="register">Register</a>
      </section>
      <section data-webx-sign-in-view="forgot" data-webx-sign-in-title="Restore the password" hidden>
        <form id="webx-sign-in-forgot"><input name="email" id="forgot-email"></form>
        <a href="/account/sign-in" data-webx-sign-in-show="sign-in">Back to sign-in</a>
      </section>
      <section data-webx-sign-in-view="register" data-webx-sign-in-title="Create an account" hidden>
        <form id="webx-sign-in-register"><input name="first_name" id="first-name"></form>
      </section>
    </dialog>`
  const dialog = document.getElementById('webx-sign-in')
  // jsdom has no modal dialogs: the runtime's openDialog and close() stand in.
  dialog.close = vi.fn(() => dialog.removeAttribute('open'))
  window.webx = { openDialog: vi.fn((el) => el.setAttribute('open', '')) }
  return dialog
}

const shown = (dialog) =>
  [...dialog.querySelectorAll('[data-webx-sign-in-view]')]
    .filter((el) => !el.hidden)
    .map((el) => el.getAttribute('data-webx-sign-in-view'))

const title = (dialog) => dialog.querySelector('.webx-dialog__title').textContent

const success = (form, response) =>
  form.dispatchEvent(
    new CustomEvent('webx:form:success', { bubbles: true, detail: { status: 200, response } }),
  )

describe('sign-in.js', () => {
  let reload

  beforeEach(() => {
    start(document)
    reload = vi.spyOn(navigation, 'reload').mockImplementation(() => {})
  })

  afterEach(() => {
    vi.restoreAllMocks()
    delete window.webx
  })

  it('opens the dialog on the view of its opener instead of following the link', () => {
    const dialog = page()
    const link = document.querySelector('a[data-webx-sign-in]')
    const event = new MouseEvent('click', { bubbles: true, cancelable: true })

    link.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
    expect(window.webx.openDialog).toHaveBeenCalledWith(dialog, link)
    expect(shown(dialog)).toEqual(['sign-in'])
    expect(document.activeElement?.getAttribute('name')).toBe('email')

    dialog.close()
    document.querySelector('button[data-webx-sign-in]').click()
    expect(shown(dialog)).toEqual(['register'])
    expect(title(dialog)).toBe('Create an account')
    expect(document.activeElement?.id).toBe('first-name')
  })

  it('takes a link to #webx-sign-in for an opener of the sign-in', () => {
    const dialog = page()
    dialog.querySelector('[data-webx-sign-in-view="forgot"]').hidden = false
    dialog.querySelector('[data-webx-sign-in-view="sign-in"]').hidden = true

    document.getElementById('plain').click()

    expect(window.webx.openDialog).toHaveBeenCalledTimes(1)
    expect(shown(dialog)).toEqual(['sign-in'])
  })

  it('switches the views and the title inside without leaving the page', () => {
    const dialog = page()
    const forgot = dialog.querySelector('[data-webx-sign-in-show="forgot"]')
    const event = new MouseEvent('click', { bubbles: true, cancelable: true })

    forgot.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
    expect(shown(dialog)).toEqual(['forgot'])
    expect(title(dialog)).toBe('Restore the password')
    expect(document.activeElement?.id).toBe('forgot-email')
    expect(window.webx.openDialog).not.toHaveBeenCalled()

    dialog.querySelector('[data-webx-sign-in-show="sign-in"]').click()
    expect(shown(dialog)).toEqual(['sign-in'])
    expect(title(dialog)).toBe('Sign in')
  })

  it('closes, says who signed in and reloads the page', () => {
    const dialog = page()
    dialog.setAttribute('open', '')
    const heard = vi.fn()
    document.addEventListener('webx:users:signed-in', heard, { once: true })

    success(document.getElementById('webx-sign-in-form'), {
      ok: true,
      redirect: null,
      visitor: { name: 'Anna Lind', verified: false },
    })

    expect(dialog.close).toHaveBeenCalled()
    expect(heard).toHaveBeenCalledTimes(1)
    expect(heard.mock.calls[0][0].detail).toEqual({ name: 'Anna Lind', verified: false })
    expect(heard.mock.calls[0][0].cancelable).toBe(true)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('leaves the page to a listener that cancelled the event', () => {
    page()
    document.addEventListener('webx:users:signed-in', (event) => event.preventDefault(), {
      once: true,
    })

    success(document.getElementById('webx-sign-in-register'), {
      ok: true,
      visitor: { name: 'new', verified: false },
    })

    expect(reload).not.toHaveBeenCalled()
  })

  it('does nothing for an answer without a visitor — «Forgot your password?»', () => {
    const dialog = page()
    const heard = vi.fn()
    document.addEventListener('webx:users:signed-in', heard, { once: true })

    success(document.getElementById('webx-sign-in-forgot'), { ok: true, message: '<p>Sent</p>' })

    expect(dialog.close).not.toHaveBeenCalled()
    expect(heard).not.toHaveBeenCalled()
    expect(reload).not.toHaveBeenCalled()
    document.removeEventListener('webx:users:signed-in', heard)
  })
})
