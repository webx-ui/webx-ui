import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  document.body.innerHTML = ''
  vi.useRealTimers()
  vi.restoreAllMocks()
})

// What `<x-webx-delivery::step>` prints, cut to what the script reads.
const step = ({
  chosen = '',
  event = 'webx:checkout:slot-changed',
  name = null,
  town = 'Town A, Region A',
} = {}) => {
  const f = (key) =>
    name ? `${name}[${key.replace(/\./g, '][')}]` : key.replace(/\.(\w+)/g, '[$1]')
  return `
  <section id="webx-delivery" class="webx-delivery" data-webx-delivery data-selection="/api/delivery/selection"
           ${event ? `data-checkout-event="${event}"` : ''} ${name ? `data-name="${name}"` : ''}>
    <form class="webx-delivery__form" method="post" action="/api/delivery/selection">
      <input type="hidden" name="_token" value="csrf-1">
      <input type="hidden" name="${f('country')}" value="XX">
      <p class="webx-delivery__card"><span data-delivery-locality-label>${town}</span></p>
      <fieldset class="webx-delivery__methods">
        <input type="radio" id="m-1" name="${f('method')}" value="1" ${chosen === '1' ? 'checked' : ''}>
        <input type="radio" id="m-2" name="${f('method')}" value="2" ${chosen === '2' ? 'checked' : ''}>
        ${
          chosen === '2'
            ? `<div class="webx-delivery__address" data-delivery-address>
                 <input id="street" name="${f('address.street')}" value="Elm">
                 <p data-delivery-error="address.street" hidden></p>
                 <input id="house" name="${f('address.house')}" value="">
                 <p data-delivery-error="address.house" hidden></p>
               </div>`
            : ''
        }
        <p data-delivery-error="method" hidden></p>
      </fieldset>
    </form>
    <dialog id="webx-delivery-where">
      <div data-delivery-locality><select name="locality"><option value=""></option><option value="7">Town B</option></select></div>
      <button type="button" data-delivery-town="9">Town C</button>
    </dialog>
  </section>`
}

async function load(html, answer) {
  document.body.innerHTML = html
  window.webx = { mount: vi.fn(), unmount: vi.fn() }
  vi.resetModules()
  const module = await import('./delivery.js')
  const put = vi
    .spyOn(module.server, 'put')
    .mockImplementation(answer ?? (async () => ({ status: 200, body: {} })))
  return { module, put, root: document.querySelector('[data-webx-delivery]') }
}

const change = (el) => el.dispatchEvent(new Event('change', { bubbles: true }))

describe('delivery step', () => {
  it('writes a method at once and puts the step the server drew in place', async () => {
    const { put, root } = await load(step(), async () => ({
      status: 200,
      body: { selection: { method: 2 }, cost: { text: '5,00 €' }, html: step({ chosen: '2' }) },
    }))
    const changed = vi.fn()
    const slot = vi.fn()
    document.addEventListener('webx:delivery:changed', changed)
    document.addEventListener('webx:checkout:slot-changed', slot)

    expect(root.classList.contains('is-live')).toBe(true)
    root.querySelector('#m-2').checked = true
    change(root.querySelector('#m-2'))
    await vi.waitFor(() => expect(slot).toHaveBeenCalled())

    expect(put).toHaveBeenCalledWith(
      '/api/delivery/selection',
      { method: '2' },
      {
        'X-CSRF-TOKEN': 'csrf-1',
        'X-Webx-Delivery-View': 'step',
        'X-Webx-Delivery-Event': 'webx:checkout:slot-changed',
      },
    )
    const fresh = document.querySelector('[data-webx-delivery]')
    expect(fresh).not.toBe(root)
    expect(fresh.querySelector('[data-delivery-address]')).not.toBeNull()
    expect(fresh.classList.contains('is-live')).toBe(true)
    expect(window.webx.unmount).toHaveBeenCalledWith(root)
    expect(window.webx.mount).toHaveBeenCalledWith(fresh)
    expect(changed.mock.calls[0][0].detail.method).toBe(2)
    expect(slot.mock.calls[0][0].detail).toMatchObject({ slot: 'delivery', method: 2 })
  })

  it('chooses a town in the dialog — the search or a popular one — and closes it', async () => {
    const { put, root } = await load(step())
    const dialog = root.querySelector('dialog')
    dialog.close = vi.fn()
    Object.defineProperty(dialog, 'open', { value: true, configurable: true })

    root.querySelector('[data-delivery-town]').click()
    await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(1))
    expect(put.mock.calls[0][1]).toEqual({ country: undefined, locality: '9' })
    expect(dialog.close).toHaveBeenCalled()

    const select = root.querySelector('[data-delivery-locality] select')
    select.value = '7'
    change(select)
    await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(2))
    expect(put.mock.calls[1][1]).toEqual({ country: undefined, locality: '7' })
  })

  it('writes the address as it is typed without drawing the step, and shows a refusal only under fields left', async () => {
    vi.useFakeTimers()
    const { put, root } = await load(step({ chosen: '2' }), async () => ({
      status: 422,
      body: {
        errors: {
          'address.house': ['Enter the house number.'],
          'address.street': ['Enter the street.'],
        },
      },
    }))

    const street = root.querySelector('#street')
    street.dispatchEvent(new FocusEvent('focusout', { bubbles: true }))
    root.querySelector('#house').dispatchEvent(new Event('input', { bubbles: true }))
    await vi.advanceTimersByTimeAsync(700)

    expect(put).toHaveBeenCalledTimes(1)
    expect(put.mock.calls[0][1]).toEqual({ address: { street: 'Elm', house: '' } })
    expect(put.mock.calls[0][2]['X-Webx-Delivery-View']).toBeUndefined()
    expect(root.querySelector('[data-delivery-error="address.street"]').hidden).toBe(false)
    expect(root.querySelector('[data-delivery-error="address.house"]').hidden).toBe(true)
  })

  it('sends the fields under the owner’s prefix when the step stands inside its form', async () => {
    const { put, root, module } = await load(step({ name: 'delivery' }))

    expect(module.keyOf('delivery[address][house]', 'delivery')).toBe('address.house')
    root.querySelector('#m-1').checked = true
    change(root.querySelector('#m-1'))
    await vi.waitFor(() => expect(put).toHaveBeenCalled())
    expect(put.mock.calls[0][1]).toEqual({ delivery: { method: '1' }, _prefix: 'delivery' })
  })

  it('chooses a popular town in a step the checkout drew again (after a sign-in)', async () => {
    // The runtime's contract, as the widgets' core keeps it: a setup per element, its cleanup on
    // unmount.
    const widgets = new Map()
    const cleanups = new Map()
    const runtime = {
      widget: vi.fn((name, selector, setup) => {
        widgets.set(name, { selector, setup })
        runtime.mount(document)
      }),
      mount: (scope) => {
        for (const { selector, setup } of widgets.values()) {
          const found = [...scope.querySelectorAll(selector)]
          if (scope instanceof Element && scope.matches(selector)) found.unshift(scope)
          for (const el of found) if (!cleanups.has(el)) cleanups.set(el, setup(el))
        }
      },
      unmount: (scope) => {
        for (const [el, cleanup] of cleanups) {
          if (scope === el || scope.contains?.(el)) {
            cleanup?.()
            cleanups.delete(el)
          }
        }
      },
    }
    document.body.innerHTML = step()
    window.webx = runtime
    vi.resetModules()
    const module = await import('./delivery.js')
    const put = vi.spyOn(module.server, 'put').mockResolvedValue({ status: 200, body: {} })
    expect(runtime.widget).toHaveBeenCalledWith(
      'delivery',
      '[data-webx-delivery]',
      expect.any(Function),
    )

    // What the checkout's redrawPage does with the step: the server's copy put in its place.
    const old = document.querySelector('[data-webx-delivery]')
    const fresh = new DOMParser()
      .parseFromString(step(), 'text/html')
      .querySelector('[data-webx-delivery]')
    runtime.unmount(old)
    old.replaceWith(fresh)
    runtime.mount(fresh)

    expect(fresh.classList.contains('is-live')).toBe(true)
    fresh.querySelector('[data-delivery-town]').click()
    await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(1))
    expect(put.mock.calls[0][1]).toEqual({ country: undefined, locality: '9' })

    // The step let go answers nothing.
    old.querySelector('[data-delivery-town]').click()
    await Promise.resolve()
    expect(put).toHaveBeenCalledTimes(1)
  })

  it('keeps the step as it was when the write fails', async () => {
    const { put, root } = await load(step(), async () => ({ status: 500, body: {} }))

    change(root.querySelector('#m-1'))
    await vi.waitFor(() => expect(put).toHaveBeenCalled())
    await vi.waitFor(() => expect(root.classList.contains('is-loading')).toBe(false))
    expect(document.querySelector('[data-webx-delivery]')).toBe(root)
  })
})
