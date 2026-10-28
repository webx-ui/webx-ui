import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

/*
 * The widget's script (§9.4) over the markup the server prints: the buy button sent in place, the
 * counters and `is-in-cart` from the answer, the server's HTML in the panels, the stepper's pause,
 * a stale answer not drawn, a stale CSRF token fetched afresh once, `webx.cart` and its event; the
 * main view opened by a buy button, by «Edit cart» and by the icon on a narrow header, and a buy at
 * the maximum answered with the cart rather than an error under the button.
 */

const state = (units, lines = [], html) => ({
  units,
  lines,
  payable_formatted: `${units},00 €`,
  ...(html === undefined ? {} : { html }),
})

const line = (id, ref, qty = 1) => ({
  id,
  type: 'catalog.product',
  ref,
  source: null,
  qty,
  children: [],
})

function page(preview = false) {
  const trigger = preview
    ? `<details class="webx-dropdown"><summary class="webx-cart__trigger"><span data-webx-cart-count>0</span></summary>
        <div class="webx-dropdown__panel"><div class="webx-cart__panel" data-webx-cart-body="preview"></div></div></details>`
    : `<a class="webx-cart__trigger" href="#webx-cart" data-webx-dialog="webx-cart">
        <span class="webx-cart__count is-empty" data-webx-cart-count>0</span>
      </a>`
  document.body.innerHTML = `
    <header class="webx-header">
    <div class="webx-cart webx-cart--drawer" data-webx-cart="drawer" data-webx-cart-panel="webx-cart"
         ${preview ? 'data-webx-cart-preview="dropdown"' : ''}
         data-webx-cart-api="/api/cart" data-webx-cart-csrf="/webx/widgets/csrf" data-webx-cart-added="Added">
      ${trigger}
      <dialog id="webx-cart"><div class="webx-cart__panel" data-webx-cart-body></div></dialog>
    </div>
    </header>
    <form class="webx-cart-add-form" action="/api/cart/items" method="post">
      <button type="submit" class="webx-cart-add" data-webx-cart-add="catalog.product:42" data-webx-cart-qty="2">Buy</button>
      <p class="webx-cart-add__error" role="alert" hidden></p>
    </form>`
}

function answer(body, status = 200) {
  return Promise.resolve({
    ok: status < 400,
    status,
    statusText: 'x',
    json: () => Promise.resolve(body),
  })
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('cart widget', () => {
  let fetch
  let mounted

  // Imported once, as a page loads it once: its listeners are on the document.
  beforeAll(async () => {
    page()
    await import('./cart.js')
    document.dispatchEvent(new Event('DOMContentLoaded'))
    mounted = {
      last: document.body.lastElementChild?.id,
      copies: document.querySelectorAll('#webx-cart').length,
    }
  })

  beforeEach(() => {
    page()
    document.cookie = 'XSRF-TOKEN=abc%3D'
    document.documentElement.lang = 'pl'
    fetch = vi.fn()
    globalThis.fetch = fetch
    window.webx.openDialog = vi.fn()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('moves the panel to the end of the body and keeps one copy of it', () => {
    expect(mounted).toEqual({ last: 'webx-cart', copies: 1 })
  })

  it('sends a buy button in place and follows the answer', async () => {
    const changed = vi.fn()
    document.addEventListener('webx:cart:change', changed)
    fetch.mockReturnValueOnce(answer(state(2, [line(1, 42, 2)], '<p>lines</p>')))

    const button = document.querySelector('.webx-cart-add')
    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    button.dispatchEvent(click)
    expect(click.defaultPrevented).toBe(true)
    expect(button.classList.contains('is-busy')).toBe(true)
    await flush()

    const [url, init] = fetch.mock.calls[0]
    expect(url).toBe('/api/cart/items')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ items: [{ type: 'catalog.product', ref: 42, qty: 2 }] })
    expect(init.headers['X-XSRF-TOKEN']).toBe('abc=')
    expect(init.headers['X-Webx-Locale']).toBe('pl')
    expect(init.headers['X-Webx-Cart-View']).toBe('drawer')

    const count = document.querySelector('[data-webx-cart-count]')
    expect(count.textContent).toBe('2')
    expect(count.classList.contains('is-empty')).toBe(false)
    expect(button.classList.contains('is-in-cart')).toBe(true)
    expect(button.classList.contains('is-added')).toBe(true)
    expect(button.classList.contains('is-busy')).toBe(false)
    expect(document.querySelector('[data-webx-cart-body]').innerHTML).toBe('<p>lines</p>')
    expect(changed).toHaveBeenCalledTimes(1)
    expect(changed.mock.calls[0][0].detail.state.units).toBe(2)
    expect(changed.mock.calls[0][0].detail.state.html).toBeUndefined()
    // The main view opens after a buy.
    expect(window.webx.openDialog).toHaveBeenCalledWith(
      document.getElementById('webx-cart'),
      document.querySelector('.webx-cart__trigger'),
    )
  })

  it('opens the main view after a buy even with the mini cart, and fills both panels', async () => {
    page(true)
    const details = document.querySelector('details')
    details.open = true
    fetch.mockReturnValueOnce(
      answer({ ...state(1, [line(1, 42)], '<p>main</p>'), preview_html: '<p>mini</p>' }),
    )

    document.querySelector('.webx-cart-add').click()
    await flush()

    expect(fetch.mock.calls[0][1].headers['X-Webx-Cart-View']).toBe('drawer')
    expect(fetch.mock.calls[0][1].headers['X-Webx-Cart-Preview']).toBe('dropdown')
    expect(document.querySelector('[data-webx-cart-body=""]').innerHTML).toBe('<p>main</p>')
    expect(document.querySelector('[data-webx-cart-body="preview"]').innerHTML).toBe('<p>mini</p>')
    expect(details.open).toBe(false)
    expect(window.webx.openDialog).toHaveBeenCalledTimes(1)
  })

  it('opens the main view from «Edit cart» of the mini cart', () => {
    page(true)
    const details = document.querySelector('details')
    details.open = true
    document.querySelector('[data-webx-cart-body="preview"]').innerHTML =
      '<a class="webx-cart__edit" href="#webx-cart" data-webx-cart-edit>Edit cart</a>'

    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    document.querySelector('[data-webx-cart-edit]').dispatchEvent(click)

    expect(click.defaultPrevented).toBe(true)
    expect(details.open).toBe(false)
    expect(window.webx.openDialog).toHaveBeenCalledWith(
      document.getElementById('webx-cart'),
      document.querySelector('.webx-cart__trigger'),
    )
  })

  it('opens the main view from the icon on a narrow header, the mini cart on a wide one', () => {
    page(true)
    const header = document.querySelector('.webx-header')
    const summary = document.querySelector('summary')

    Object.defineProperty(header, 'clientWidth', { configurable: true, value: 1280 })
    const wide = new MouseEvent('click', { bubbles: true, cancelable: true })
    summary.dispatchEvent(wide)
    expect(window.webx.openDialog).not.toHaveBeenCalled()

    Object.defineProperty(header, 'clientWidth', { configurable: true, value: 360 })
    const narrow = new MouseEvent('click', { bubbles: true, cancelable: true })
    summary.dispatchEvent(narrow)
    expect(narrow.defaultPrevented).toBe(true)
    expect(window.webx.openDialog).toHaveBeenCalledTimes(1)
  })

  it('opens the cart with its note when the item is already at its maximum', async () => {
    fetch.mockReturnValueOnce(
      answer(
        {
          message: 'The quantity is from 1 to 999.',
          errors: { 'items.0.qty': ['The quantity is from 1 to 999.'] },
          ...state(999, [line(1, 42, 999)], '<p class="webx-cart__notice">max</p>'),
        },
        422,
      ),
    )

    document.querySelector('.webx-cart-add').click()
    await flush()

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(document.querySelector('.webx-cart-add__error').hidden).toBe(true)
    expect(document.querySelector('[data-webx-cart-body]').innerHTML).toBe(
      '<p class="webx-cart__notice">max</p>',
    )
    expect(document.querySelector('[data-webx-cart-count]').textContent).toBe('999')
    expect(window.webx.openDialog).toHaveBeenCalledTimes(1)
  })

  it('says a refusal under the button', async () => {
    fetch.mockReturnValueOnce(
      answer({ message: 'x', errors: { 'items.0.ref': ['Item A cannot be bought now.'] } }, 422),
    )

    document.querySelector('.webx-cart-add').click()
    await flush()

    const alert = document.querySelector('.webx-cart-add__error')
    expect(alert.hidden).toBe(false)
    expect(alert.textContent).toBe('Item A cannot be bought now.')
    // No cart to say it in: nothing opens.
    expect(window.webx.openDialog).not.toHaveBeenCalled()
  })

  it('fetches a fresh CSRF token on 419 and sends once more', async () => {
    fetch
      .mockReturnValueOnce(answer({ message: 'CSRF token mismatch.' }, 419))
      .mockReturnValueOnce(answer(null, 204))
      .mockReturnValueOnce(answer(state(1, [line(1, 7)])))

    await window.webx.cart.add([{ type: 'catalog.product', ref: 7 }])

    expect(fetch.mock.calls.map(([url]) => url)).toEqual([
      '/api/cart/items',
      '/webx/widgets/csrf',
      '/api/cart/items',
    ])
  })

  it('takes a bundle as one batch', async () => {
    fetch.mockReturnValueOnce(answer(state(3)))
    const items = [
      { type: 'catalog.product', ref: 1, key: 'set' },
      { type: 'catalog.product', ref: 2, parent: 'set', qty: 2 },
    ]

    await window.webx.cart.add(items)

    expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ items })
  })

  it('sends the stepper 300 ms after the last press', async () => {
    vi.useFakeTimers()
    document.querySelector('[data-webx-cart-body]').innerHTML = `
      <li data-webx-cart-line="5">
        <button data-webx-cart-step="1">+</button>
        <input data-webx-cart-qty-input type="number" max="999" value="1">
      </li>`
    fetch.mockReturnValue(answer(state(3)))

    const plus = document.querySelector('[data-webx-cart-step]')
    plus.click()
    plus.click()
    vi.advanceTimersByTime(299)
    expect(fetch).not.toHaveBeenCalled()

    vi.advanceTimersByTime(1)
    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch.mock.calls[0][0]).toBe('/api/cart/items/5')
    expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ qty: 3 })
  })

  it('does not draw an answer that a newer write overtook', async () => {
    let first
    fetch
      .mockReturnValueOnce(new Promise((resolve) => (first = resolve)))
      .mockReturnValueOnce(answer(state(4, [], '<p>newer</p>')))

    const older = window.webx.cart.setQty(1, 2)
    await window.webx.cart.setQty(1, 4)
    first({ ok: true, status: 200, json: () => Promise.resolve(state(2, [], '<p>older</p>')) })
    await older

    expect(document.querySelector('[data-webx-cart-count]').textContent).toBe('4')
    expect(document.querySelector('[data-webx-cart-body]').innerHTML).toBe('<p>newer</p>')
  })

  it('removes a line by its id, a generated one by its key', async () => {
    fetch.mockReturnValue(answer(state(0)))

    await window.webx.cart.remove('rule#12:0')

    expect(fetch.mock.calls[0][0]).toBe('/api/cart/items/rule%2312%3A0')
    expect(fetch.mock.calls[0][1].method).toBe('DELETE')
  })
})
