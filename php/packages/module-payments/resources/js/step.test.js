import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { startAll } from './step.js'

/*
 * What the payment step's script sends: the choice at once, and the language of the page — the
 * API's address has none, and the step it answers for came back in the application's.
 */

const step = () => `
  <section data-webx-payments data-selection="/api/payments/selection">
    <form method="post" action="/api/payments/selection">
      <input type="hidden" name="_token" value="csrf-1">
      <label><input type="radio" name="method" value="card"> Card</label>
    </form>
  </section>`

describe('step.js', () => {
  let fetch

  beforeEach(() => {
    fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({ summary: null }) })
    vi.stubGlobal('fetch', fetch)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    document.documentElement.lang = ''
    document.body.innerHTML = ''
  })

  it('says the language of the page with the choice', async () => {
    document.documentElement.lang = 'uk'
    document.body.innerHTML = step()
    startAll()

    const radio = document.querySelector('input[value="card"]')
    radio.checked = true
    radio.dispatchEvent(new Event('change', { bubbles: true }))
    await vi.waitFor(() => expect(fetch).toHaveBeenCalledTimes(1))

    const [url, init] = fetch.mock.calls[0]
    expect(url).toBe('/api/payments/selection')
    expect(init.method).toBe('PUT')
    expect(init.headers).toMatchObject({ 'X-CSRF-TOKEN': 'csrf-1', 'X-Webx-Locale': 'uk' })
  })

  it('sends no language when the page has none', async () => {
    document.body.innerHTML = step()
    startAll()

    const radio = document.querySelector('input[value="card"]')
    radio.checked = true
    radio.dispatchEvent(new Event('change', { bubbles: true }))
    await vi.waitFor(() => expect(fetch).toHaveBeenCalledTimes(1))

    expect(fetch.mock.calls[0][1].headers).not.toHaveProperty('X-Webx-Locale')
  })
})
