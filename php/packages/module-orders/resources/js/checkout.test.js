import { afterEach, describe, expect, it, vi } from 'vitest'
import { server, start } from './checkout.js'

/*
 * checkout.js over the markup of the page (orders spec §27.3): «Change» only at `collapse`, the
 * column redrawn after a step, a slot and a 409, the sign-in taken over without a reload, the
 * sticky row following the amount, `webx:checkout:placed` on «Thank you».
 */

const step = (key, state, summary, value = '') => `
  <li id="checkout-step-${key}" class="webx-orders-checkout__step ${state}" data-step="${key}">
    <h2 class="webx-orders-checkout__step-head">${key}</h2>
    <p class="webx-orders-checkout__step-summary">${summary}
      <a class="webx-orders-checkout__step-edit" href="#checkout-step-${key}-form">Change</a></p>
    <div class="webx-orders-checkout__step-body">
      <form id="checkout-step-${key}-form"><input name="${key}" value="${value}"></form>
    </div>
  </li>`

const summary = (payable) => `
  <div class="webx-orders-checkout__summary" id="checkout-summary">
    <dl><div class="webx-orders-checkout__payable"><dt>To pay</dt><dd>${payable}</dd></div></dl>
    <form id="checkout-place"><div class="webx-form__error"></div><button class="webx-form__submit">Place</button></form>
  </div>`

const markup = (view, { contacts = 'Buyer A', payable = '€10.00', value = '' } = {}) => `
  <div class="webx-orders-checkout" data-steps="${view}">
    <div class="webx-orders-checkout__main">
      <ol class="webx-orders-checkout__steps" data-slot-event="webx:checkout:slot-changed" data-summary="/checkout/summary">
        ${step('contacts', 'is-done', contacts, value)}
        ${step('payment', 'is-open', 'Card')}
      </ol>
    </div>
    <aside class="webx-orders-checkout__aside">${summary(payable)}</aside>
    <div class="webx-orders-checkout__sticky"><strong data-payable>${payable}</strong></div>
  </div>`

let doc

const page = (view) => {
  doc = document.implementation.createHTMLDocument('checkout')
  doc.body.innerHTML = markup(view)
  start(doc)
  return doc.querySelector('.webx-orders-checkout')
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

const emit = (el, name, detail) =>
  el.dispatchEvent(new CustomEvent(name, { bubbles: true, cancelable: true, detail }))

// The answers the fake shell schedules; a test that ends first must not hear them after teardown.
const answers = []

afterEach(() => {
  answers.splice(0).forEach(clearTimeout)
  vi.restoreAllMocks()
  delete window.webx
})

describe('checkout.js', () => {
  it('opens a folded step with «Change» at collapse and leaves the link alone at open', () => {
    const root = page('collapse')
    const edit = root.querySelector('[data-step="contacts"] .webx-orders-checkout__step-edit')
    const click = new MouseEvent('click', { bubbles: true, cancelable: true })

    edit.dispatchEvent(click)
    expect(click.defaultPrevented).toBe(true)
    expect(root.querySelector('[data-step="contacts"]').classList.contains('is-editing')).toBe(true)

    const open = page('open')
    const other = new MouseEvent('click', { bubbles: true, cancelable: true })
    open.querySelector('.webx-orders-checkout__step-edit').dispatchEvent(other)
    expect(other.defaultPrevented).toBe(false)
    expect(open.querySelector('.is-editing')).toBe(null)
  })

  it('redraws the saved step and the column, the sticky amount with it', async () => {
    const root = page('collapse')
    const fetch = vi
      .spyOn(server, 'fetch')
      .mockResolvedValue(markup('collapse', { contacts: 'Buyer B', payable: '€12.00' }))
    const saved = root.querySelector('[data-step="contacts"]')
    saved.classList.add('is-editing')

    emit(saved.querySelector('form'), 'webx:form:success', { status: 200, response: { ok: true } })
    await flush()

    expect(fetch).toHaveBeenCalledOnce()
    const fresh = root.querySelector('[data-step="contacts"]')
    expect(fresh).not.toBe(saved)
    expect(fresh.classList.contains('is-editing')).toBe(false)
    expect(fresh.querySelector('.webx-orders-checkout__step-summary').textContent).toContain(
      'Buyer B',
    )
    expect(root.querySelector('#checkout-summary dd').textContent).toBe('€12.00')
    expect(root.querySelector('[data-payable]').textContent).toBe('€12.00')
  })

  it('puts a fresh column and the reason under its button on a 409', async () => {
    const root = page('open')
    const fetch = vi.spyOn(server, 'fetch').mockResolvedValue(summary('€9.00'))

    emit(root.querySelector('#checkout-place'), 'webx:form:error', {
      status: 409,
      errors: {},
      message: 'The amount changed: was €10.00, now €9.00.',
    })
    await flush()

    expect(fetch).toHaveBeenCalledWith('/checkout/summary')
    expect(root.querySelector('#checkout-summary dd').textContent).toBe('€9.00')
    expect(root.querySelector('#checkout-place .webx-form__error').textContent).toBe(
      'The amount changed: was €10.00, now €9.00.',
    )
  })

  it('opens the step an error of the confirmation is about, its field in focus', () => {
    const root = page('collapse')
    const input = root.querySelector('input[name="contacts"]')
    const focus = vi.spyOn(input, 'focus')

    emit(root.querySelector('#checkout-place'), 'webx:form:error', {
      status: 422,
      errors: { contacts: ['Enter a name.'] },
    })

    expect(root.querySelector('[data-step="contacts"]').classList.contains('is-editing')).toBe(true)
    expect(focus).toHaveBeenCalled()
  })

  it('takes the sign-in over: no reload, the steps and the column redrawn', async () => {
    const root = page('collapse')
    vi.spyOn(server, 'fetch').mockResolvedValue(
      markup('collapse', { contacts: 'Visitor C', payable: '€8.00' }),
    )

    const event = new CustomEvent('webx:users:signed-in', { bubbles: true, cancelable: true })
    expect(doc.dispatchEvent(event)).toBe(false)
    await flush()

    expect(
      root.querySelector('[data-step="contacts"] .webx-orders-checkout__step-summary').textContent,
    ).toContain('Visitor C')
    expect(root.querySelector('#checkout-summary dd').textContent).toBe('€8.00')
  })

  it('redraws the column after a slot, and the payment after delivery', async () => {
    const root = page('open')
    const fetch = vi.spyOn(server, 'fetch').mockResolvedValue(markup('open', { payable: '€15.00' }))
    const contacts = root.querySelector('[data-step="contacts"]')

    emit(doc, 'webx:checkout:slot-changed', { slot: 'coupons' })
    await flush()
    expect(fetch).toHaveBeenLastCalledWith('/checkout/summary')

    const payment = root.querySelector('[data-step="payment"]')
    emit(doc, 'webx:checkout:slot-changed', { slot: 'delivery' })
    await flush()
    expect(root.querySelector('[data-step="payment"]')).not.toBe(payment)
    // The step the buyer is in stays the element it was.
    expect(root.querySelector('[data-step="contacts"]')).toBe(contacts)
    expect(root.querySelector('#checkout-summary dd').textContent).toBe('€15.00')
  })

  it('redraws the step of a slot that changed by itself, and only when it asks', async () => {
    const root = page('open')
    vi.spyOn(server, 'fetch').mockResolvedValue(markup('open', { payable: '€15.00' }))

    const payment = root.querySelector('[data-step="payment"]')
    emit(doc, 'webx:checkout:slot-changed', { slot: 'payment', redraw: false })
    await flush()
    expect(root.querySelector('[data-step="payment"]')).toBe(payment)

    emit(doc, 'webx:checkout:slot-changed', { slot: 'payment', redraw: true })
    await flush()
    expect(root.querySelector('[data-step="payment"]')).not.toBe(payment)
  })

  describe('«Place the order» with a step left unsaved', () => {
    // The page of the press: «Contacts» filled by the account, never saved — not done.
    const pressPage = (contacts = 'is-open') => {
      doc = document.implementation.createHTMLDocument('checkout')
      doc.body.innerHTML = `
        <div class="webx-orders-checkout" data-steps="collapse">
          <ol class="webx-orders-checkout__steps" data-summary="/checkout/summary">
            <li class="webx-orders-checkout__step ${contacts}" data-step="contacts">
              <div class="webx-orders-checkout__step-body">
                <form class="webx-form" id="checkout-step-contacts-form"><input name="first_name" value="Ann"></form>
              </div>
            </li>
          </ol>
          ${summary('€10.00')}
        </div>`
      start(doc)
      return doc.querySelector('.webx-orders-checkout')
    }

    /**
     * What the widgets' shell does on submit, in short: its checks (`valid`), then
     * `webx:form:submit` — a listener may cancel it —, then the answer.
     */
    const shell = (form, sent, { valid = true, answer = 'success' } = {}) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault()
        if (!valid) return
        if (!emit(form, 'webx:form:submit', { wait: () => {} })) return
        sent.push(form.id)
        answers.push(
          setTimeout(() =>
            emit(
              form,
              `webx:form:${answer}`,
              answer === 'success' ? { response: {} } : { status: 422, errors: {} },
            ),
          ),
        )
      })
    }
    const press = (root) => root.querySelector('#checkout-place').requestSubmit()

    it('saves the step first, as «Save» would, and presses itself again after the redraw', async () => {
      const root = pressPage()
      const sent = []
      shell(root.querySelector('#checkout-step-contacts-form'), sent)
      shell(root.querySelector('#checkout-place'), sent)
      // The page after the save: the step done; the column a fresh form, the shell's again.
      vi.spyOn(server, 'fetch').mockResolvedValue(markup('collapse'))
      window.webx = {
        mount: (el) =>
          el.querySelectorAll?.('#checkout-place').forEach((form) => shell(form, sent)),
      }

      press(root)
      expect(sent).toEqual(['checkout-step-contacts-form'])

      for (let i = 0; i < 4; i++) await flush()
      expect(sent).toEqual(['checkout-step-contacts-form', 'checkout-place'])
    })

    it('leaves the errors at the fields of a step the server refuses, and sends no order', async () => {
      const root = pressPage()
      const sent = []
      shell(root.querySelector('#checkout-step-contacts-form'), sent, { answer: 'error' })
      shell(root.querySelector('#checkout-place'), sent)
      const fetch = vi.spyOn(server, 'fetch')

      press(root)
      await flush()
      await flush()

      expect(sent).toEqual(['checkout-step-contacts-form'])
      expect(fetch).not.toHaveBeenCalled()
      expect(root.querySelector('[data-step="contacts"]').classList.contains('is-editing')).toBe(
        true,
      )
      expect(root.querySelector('#checkout-place .webx-form__error').textContent).toBe('')
    })

    it('stops at a field the shell finds wrong before anything goes', () => {
      const root = pressPage()
      const sent = []
      shell(root.querySelector('#checkout-step-contacts-form'), sent, { valid: false })
      shell(root.querySelector('#checkout-place'), sent)

      press(root)

      expect(sent).toEqual([])
      expect(root.querySelector('[data-step="contacts"]').classList.contains('is-editing')).toBe(
        true,
      )
    })

    it('sends at once when every step is done and untouched, and saves one typed in', () => {
      const root = pressPage('is-done')
      const sent = []
      shell(root.querySelector('#checkout-step-contacts-form'), sent)
      shell(root.querySelector('#checkout-place'), sent)

      press(root)
      expect(sent).toEqual(['checkout-place'])

      const input = root.querySelector('input[name="first_name"]')
      input.value = 'Bea'
      input.dispatchEvent(new Event('input', { bubbles: true }))
      press(root)
      expect(sent).toEqual(['checkout-place', 'checkout-step-contacts-form'])
    })
  })

  it('says webx:checkout:placed on «Thank you»', () => {
    doc = document.implementation.createHTMLDocument('thanks')
    doc.body.innerHTML =
      '<div class="webx-orders-thanks__analytics" hidden data-number="1042" data-payable="4499" data-currency="EUR"></div>'
    const heard = []
    doc.addEventListener('webx:checkout:placed', (event) => heard.push(event.detail))

    start(doc)

    expect(heard).toEqual([{ number: '1042', payable: 4499, currency: 'EUR' }])
  })
})

describe('order contents', () => {
  const line = (id, qty, { max = 9 } = {}) => `
    <li class="webx-orders-checkout__line" data-line="${id}">
      <span>Item ${id}</span>
      <form class="webx-orders-checkout__qty" method="post" action="/api/cart/items/${id}" data-max="${max}">
        <input type="hidden" name="_token" value="t"><input type="hidden" name="_method" value="PATCH">
        <button name="qty" value="${qty - 1}" data-control="decrease" ${qty <= 1 ? 'disabled' : ''}>-</button>
        <span data-qty>${qty}</span>
        <button name="qty" value="${qty + 1}" data-control="increase" ${qty >= max ? 'disabled' : ''}>+</button>
      </form>
      <form method="post" action="/api/cart/items/${id}">
        <input type="hidden" name="_method" value="DELETE">
        <button data-control="remove">Remove</button>
      </form>
    </li>`

  const column = (payable, lines) => `
    <div class="webx-orders-checkout__summary" id="checkout-summary">
      <h2 class="webx-orders-checkout__summary-title" tabindex="-1">Your order</h2>
      <dl><div class="webx-orders-checkout__payable"><dt>To pay</dt><dd>${payable}</dd></div></dl>
      <details class="webx-orders-checkout__lines" open><summary>Order contents</summary><ul>${lines}</ul></details>
      <form id="checkout-place"><div class="webx-form__error"></div><button class="webx-form__submit">Place</button></form>
    </div>`

  const stepOf = (key, summary, slot = false) => `
    <li class="webx-orders-checkout__step" data-step="${key}" ${slot ? 'data-slot' : ''}>
      <h2 class="webx-orders-checkout__step-head">${key}</h2>
      <p class="webx-orders-checkout__step-summary">${summary}</p>
      <div class="webx-orders-checkout__step-body"><form><input name="${key}"></form></div>
    </li>`

  const pageOf = (steps, payable, lines, warnings = '') => `
    <div class="webx-orders-checkout" data-steps="open">
      <h1 class="webx-orders-checkout__title">Checkout</h1>
      ${warnings}
      <ol class="webx-orders-checkout__steps" data-slot-event="webx:checkout:slot-changed" data-summary="/checkout/summary">${steps}</ol>
      <aside>${column(payable, lines)}</aside>
      <div class="webx-orders-checkout__sticky"><strong data-payable>${payable}</strong></div>
    </div>`

  const settle = async () => {
    await vi.advanceTimersByTimeAsync(400)
    await vi.runAllTimersAsync()
  }

  // In the page's own document, the only one jsdom gives the focus in; `start` keeps one run a
  // document, so each test gets a module of its own.
  let mod

  const open = async (html) => {
    vi.useFakeTimers()
    vi.resetModules()
    // The module starts on the page as it loads.
    document.body.innerHTML = html
    doc = document
    mod = await import('./checkout.js')
  }

  const root = () => doc.querySelector('.webx-orders-checkout')
  const press = (selector) => doc.querySelector(selector).click()

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('sends several presses as one write and redraws the slots and the column', async () => {
    await open(
      pageOf(stepOf('contacts', 'A') + stepOf('delivery', 'Old', true), '€10.00', line(1, 1)),
    )
    const send = vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      pageOf(stepOf('contacts', 'A') + stepOf('delivery', 'New', true), '€30.00', line(1, 3)),
    )
    root().querySelector('[name="contacts"]').value = 'typed'

    press('[data-control="increase"]')
    press('[data-control="increase"]')
    expect(doc.querySelector('[data-qty]').textContent).toBe('3')
    await settle()

    expect(send).toHaveBeenCalledTimes(1)
    expect(send.mock.calls[0][0]).toBe('/api/cart/items/1')
    expect(send.mock.calls[0][1].get('qty')).toBe('3')
    expect(send.mock.calls[0][1].get('_method')).toBe('PATCH')
    expect(doc.querySelector('[data-step="delivery"]').textContent).toContain('New')
    expect(doc.querySelector('#checkout-summary dd').textContent).toBe('€30.00')
    expect(doc.querySelector('[data-payable]').textContent).toBe('€30.00')
    // The contacts are not a slot: what the buyer typed stays.
    expect(root().querySelector('[name="contacts"]').value).toBe('typed')
  })

  it('keeps the focus on the control and the contents as the buyer left them', async () => {
    await open(pageOf(stepOf('contacts', 'A'), '€10.00', line(1, 2) + line(2, 1)))
    vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      pageOf(stepOf('contacts', 'A'), '€20.00', line(1, 3) + line(2, 1)),
    )
    const button = doc.querySelector('[data-line="1"] [data-control="increase"]')
    button.focus()
    button.click()
    await settle()

    const active = doc.activeElement
    expect(active.getAttribute('data-control')).toBe('increase')
    expect(active.closest('[data-line]').getAttribute('data-line')).toBe('1')
    expect(active).not.toBe(button)
  })

  it('gives the focus to the next line when a line goes, and to the neighbour at the minimum', async () => {
    await open(pageOf(stepOf('contacts', 'A'), '€10.00', line(1, 2) + line(2, 1)))
    const send = vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      pageOf(stepOf('contacts', 'A'), '€5.00', line(2, 1)),
    )

    const less = doc.querySelector('[data-line="1"] [data-control="decrease"]')
    less.focus()
    less.click()
    expect(less.disabled).toBe(true)
    expect(doc.activeElement.getAttribute('data-control')).toBe('increase')

    const remove = doc.querySelector('[data-line="1"] [data-control="remove"]')
    remove.focus()
    remove.click()
    await settle()

    // The removal took over from the press that waited.
    expect(send).toHaveBeenCalledTimes(1)
    expect(send.mock.calls[0][1].get('_method')).toBe('DELETE')
    expect(doc.querySelector('[data-line="1"]')).toBeNull()
    expect(doc.activeElement.closest('[data-line]').getAttribute('data-line')).toBe('2')
  })

  it('reloads into the empty cart when the last line goes', async () => {
    await open(pageOf(stepOf('contacts', 'A'), '€10.00', line(1, 1)))
    vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      '<div class="webx-orders-checkout is-empty"></div>',
    )
    const reload = vi.spyOn(mod.server, 'reload').mockImplementation(() => {})

    press('[data-control="remove"]')
    await settle()

    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('follows the steps and the warnings of the cart', async () => {
    const warning = '<div id="checkout-warnings"><ul><li>Gone</li></ul></div>'
    await open(
      pageOf(
        stepOf('contacts', 'A') + stepOf('delivery', 'D', true) + stepOf('comment', 'C'),
        '€10.00',
        line(1, 1) + line(2, 1),
        warning,
      ),
    )
    vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      pageOf(
        stepOf('contacts', 'A') + stepOf('comment', 'C').replace('>comment<', '>2. comment<'),
        '€5.00',
        line(1, 1),
      ),
    )

    press('[data-line="2"] [data-control="remove"]')
    await settle()

    expect(doc.querySelector('[data-step="delivery"]')).toBeNull()
    expect(doc.querySelector('#checkout-warnings')).toBeNull()
    expect(doc.querySelector('[data-step="comment"] h2').textContent).toBe('2. comment')
  })

  it('says a refusal under its line, with the number the server has', async () => {
    await open(pageOf(stepOf('contacts', 'A'), '€10.00', line(1, 2)))
    vi.spyOn(mod.server, 'send').mockResolvedValue({
      status: 422,
      body: { message: 'No', errors: { qty: ['At most 2.'] } },
    })
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      pageOf(stepOf('contacts', 'A'), '€10.00', line(1, 2)),
    )

    press('[data-control="increase"]')
    await settle()

    expect(doc.querySelector('[data-qty]').textContent).toBe('2')
    expect(doc.querySelector('[data-line="1"] [role="alert"]').textContent).toBe('At most 2.')
  })

  it('holds the confirmation back for a press on its way', async () => {
    await open(pageOf(stepOf('contacts', 'A'), '€10.00', line(1, 1)))
    const send = vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      pageOf(stepOf('contacts', 'A'), '€20.00', line(1, 2)),
    )
    const places = []

    press('[data-control="increase"]')
    const held = emit(doc.querySelector('#checkout-place'), 'webx:form:submit', {})
    expect(held).toBe(false)
    doc.addEventListener(
      'submit',
      (event) => {
        event.preventDefault()
        places.push(doc.querySelector('#checkout-summary dd').textContent)
      },
      { once: true },
    )
    await settle()

    expect(send).toHaveBeenCalledTimes(1)
    expect(places).toEqual(['€20.00'])
  })

  describe('what the buyer typed in a slot', () => {
    // The server refused the half-done address: its drawing has none of it.
    const courier = (fields) => `
      <li class="webx-orders-checkout__step" data-step="delivery" data-slot>
        <h2 class="webx-orders-checkout__step-head">delivery</h2>
        <div class="webx-orders-checkout__step-body"><form>${fields}</form></div>
      </li>`

    const address = `
      <input name="address[street]" value="">
      <input name="address[house]" value="">
      <textarea name="address[note]"></textarea>`

    const field = (name) => doc.querySelector(`[name="address[${name}]"]`)

    const type = () => {
      field('street').value = 'Only street'
      field('note').value = 'Ring twice'
    }

    it('stays after a press, and the sum follows', async () => {
      await open(pageOf(courier(address), '€10.00', line(1, 1) + line(2, 1)))
      vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
      vi.spyOn(mod.server, 'fetch').mockResolvedValue(
        pageOf(courier(address), '€30.00', line(1, 2) + line(2, 1)),
      )
      const old = field('street')
      type()

      doc.querySelector('[data-control="increase"]').focus()
      press('[data-control="increase"]')
      await settle()

      expect(field('street')).not.toBe(old)
      expect(field('street').value).toBe('Only street')
      expect(field('note').value).toBe('Ring twice')
      expect(field('house').value).toBe('')
      expect(doc.querySelector('#checkout-summary dd').textContent).toBe('€30.00')
      expect(doc.activeElement).toBe(doc.querySelector('[data-control="increase"]'))
    })

    it('stays after «Remove» and press after press', async () => {
      await open(pageOf(courier(address), '€10.00', line(1, 1) + line(2, 1)))
      vi.spyOn(mod.server, 'send').mockResolvedValue({ status: 200, body: {} })
      const fetch = vi
        .spyOn(mod.server, 'fetch')
        .mockResolvedValue(pageOf(courier(address), '€20.00', line(1, 2) + line(2, 1)))
      type()

      press('[data-control="increase"]')
      await settle()
      fetch.mockResolvedValue(pageOf(courier(address), '€5.00', line(2, 1)))
      press('[data-control="remove"]')
      await settle()

      expect(field('street').value).toBe('Only street')
      expect(doc.querySelector('#checkout-summary dd').textContent).toBe('€5.00')
    })

    it('stays when the widget of the cart changes the lines', async () => {
      await open(pageOf(courier(address), '€10.00', line(1, 1)))
      vi.spyOn(mod.server, 'fetch').mockResolvedValue(
        pageOf(courier(address), '€20.00', line(1, 2)),
      )
      type()

      doc.dispatchEvent(new CustomEvent('webx:cart:change'))
      await settle()

      expect(field('street').value).toBe('Only street')
      expect(doc.querySelector('[data-qty]').textContent).toBe('2')
    })

    it('leaves a field the buyer did not touch to the server', async () => {
      await open(
        pageOf(courier('<input name="address[street]" value="Old">'), '€10.00', line(1, 1)),
      )
      vi.spyOn(mod.server, 'fetch').mockResolvedValue(
        pageOf(courier('<input name="address[street]" value="New">'), '€20.00', line(1, 2)),
      )

      doc.dispatchEvent(new CustomEvent('webx:cart:change'))
      await settle()

      expect(field('street').value).toBe('New')
    })

    it('has nothing to take over when the drawing lost the field', async () => {
      await open(pageOf(courier(address), '€10.00', line(1, 1)))
      vi.spyOn(mod.server, 'fetch').mockResolvedValue(
        pageOf(courier('<input type="radio" name="method" value="pickup">'), '€20.00', line(1, 2)),
      )
      type()

      doc.dispatchEvent(new CustomEvent('webx:cart:change'))
      await settle()

      expect(field('street')).toBeNull()
      expect(doc.querySelector('[name="method"]')).not.toBeNull()
    })

    it("keeps an option of the server's search the drawing does not print", async () => {
      const list = '<select name="address[street_id]"><option value="">Choose</option></select>'
      await open(pageOf(courier(list), '€10.00', line(1, 1)))
      vi.spyOn(mod.server, 'fetch').mockResolvedValue(pageOf(courier(list), '€20.00', line(1, 2)))
      const select = field('street_id')
      select.append(new Option('Found street', '77'))
      select.value = '77'

      doc.dispatchEvent(new CustomEvent('webx:cart:change'))
      await settle()

      expect(field('street_id')).not.toBe(select)
      expect(field('street_id').value).toBe('77')
      expect(field('street_id').selectedOptions[0].textContent).toBe('Found street')
    })

    it('leaves a list on its placeholder to the server', async () => {
      const list = (chosen) =>
        `<select name="address[street_id]"><option value="">Choose</option><option value="5" ${chosen}>Saved</option></select>`
      await open(pageOf(courier(list('')), '€10.00', line(1, 1)))
      vi.spyOn(mod.server, 'fetch').mockResolvedValue(
        pageOf(courier(list('selected')), '€20.00', line(1, 2)),
      )

      doc.dispatchEvent(new CustomEvent('webx:cart:change'))
      await settle()

      expect(field('street_id').value).toBe('5')
    })
  })

  it('follows the widget of the cart', async () => {
    await open(pageOf(stepOf('contacts', 'A'), '€10.00', line(1, 1)))
    vi.spyOn(mod.server, 'fetch').mockResolvedValue(
      pageOf(stepOf('contacts', 'A'), '€20.00', line(1, 2)),
    )

    doc.dispatchEvent(new CustomEvent('webx:cart:change'))
    await settle()

    expect(doc.querySelector('[data-qty]').textContent).toBe('2')
  })
})
