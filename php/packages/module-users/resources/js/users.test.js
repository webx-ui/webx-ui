import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { start, visitor } from './users.js'

/*
 * users.js over the markup a cached page prints (spec §8.3, §11.6): a guest asks nothing, a signed-in
 * visitor gets «Account» for «Sign in», an unconfirmed one the banner with the masked address, the
 * cross closes it for the session, and «Send again» holds its button for the minute of the limit.
 */

const page = () => {
  document.body.innerHTML = `
    <a data-webx-users-guest href="/account/sign-in">Sign in</a>
    <a data-webx-users-signed-in hidden href="/account">Account</a>
    <div class="webx-users-verify" data-webx-users-verify data-webx-users-state="/account/state" hidden>
      <p>Please confirm your e-mail: <span data-webx-users-email></span></p>
      <form data-webx-users-sent="Sent"><button type="submit">Send again</button></form>
      <button type="button" data-webx-users-close>×</button>
    </div>`
  return document.querySelector('[data-webx-users-verify]')
}

const answer = (data) =>
  vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve(data) }))

describe('users.js', () => {
  beforeEach(() => sessionStorage.clear())
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.useRealTimers()
  })

  it('reads the cookie among the others', () => {
    expect(visitor('a=1; webx_visitor=u; b=2')).toBe('u')
    expect(visitor('a=1')).toBeNull()
  })

  it('leaves the page of a guest alone and asks nothing', () => {
    const banner = page()
    const fetch = answer({})
    vi.stubGlobal('fetch', fetch)

    start(document, 'other=1')

    expect(banner.hidden).toBe(true)
    expect(document.querySelector('[data-webx-users-guest]').hidden).toBe(false)
    expect(fetch).not.toHaveBeenCalled()
  })

  it('shows «Account» to a confirmed visitor and no banner', () => {
    const banner = page()
    const fetch = answer({})
    vi.stubGlobal('fetch', fetch)

    start(document, 'webx_visitor=1')

    expect(document.querySelector('[data-webx-users-guest]').hidden).toBe(true)
    expect(document.querySelector('[data-webx-users-signed-in]').hidden).toBe(false)
    expect(banner.hidden).toBe(true)
    expect(fetch).not.toHaveBeenCalled()
  })

  it('shows the banner to an unconfirmed visitor with the masked address', async () => {
    const banner = page()
    const fetch = answer({ signedIn: true, verified: false, email: 'a…@example.com' })
    vi.stubGlobal('fetch', fetch)

    await start(document, 'webx_visitor=u')

    expect(banner.hidden).toBe(false)
    expect(fetch).toHaveBeenCalledWith(
      '/account/state',
      expect.objectContaining({ credentials: 'same-origin' }),
    )
    expect(banner.querySelector('[data-webx-users-email]').textContent).toBe('a…@example.com')
  })

  it('hides it again when the server says the address is confirmed meanwhile', async () => {
    const banner = page()
    vi.stubGlobal('fetch', answer({ signedIn: true, verified: true, email: 'a…@example.com' }))

    await start(document, 'webx_visitor=u')

    expect(banner.hidden).toBe(true)
  })

  it('closes it for the session of the browser', async () => {
    let banner = page()
    vi.stubGlobal('fetch', answer({ signedIn: true, verified: false, email: 'a…@example.com' }))
    await start(document, 'webx_visitor=u')

    banner.querySelector('[data-webx-users-close]').click()
    expect(banner.hidden).toBe(true)

    banner = page()
    await start(document, 'webx_visitor=u')
    expect(banner.hidden).toBe(true)
  })

  it('holds «Send again» for the minute of the limit', async () => {
    vi.useFakeTimers()
    const banner = page()
    vi.stubGlobal('fetch', answer({ signedIn: true, verified: false, email: 'a…@example.com' }))
    await start(document, 'webx_visitor=u')

    const button = banner.querySelector('button[type="submit"]')
    banner
      .querySelector('form')
      .dispatchEvent(new CustomEvent('webx:form:success', { bubbles: true }))

    expect(button.disabled).toBe(true)
    expect(button.textContent).toBe('Sent')

    vi.advanceTimersByTime(60000)
    expect(button.disabled).toBe(false)
    expect(button.textContent).toBe('Send again')
  })
})
