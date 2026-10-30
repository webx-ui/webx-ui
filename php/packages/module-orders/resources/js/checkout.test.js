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

afterEach(() => {
  vi.restoreAllMocks()
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
