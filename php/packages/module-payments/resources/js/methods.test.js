import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { checkAll } from './methods.js'
import { startAll } from './step.js'

/*
 * What Apple Pay takes away (groups spec §4) over the markup of `<x-webx-payments::methods>`: the
 * three answers of the check, the shapes a list falls into after it, and the choice that went with
 * a row. How it looks is the browser's to say.
 */

const marks = (keys) =>
  keys.length === 0
    ? ''
    : `<ul class="webx-payments__marks">${keys.map((key) => `<li data-mark="${key}"></li>`).join('')}</ul>`

const row = (title, list) =>
  `<span class="webx-payments__row-text"><span class="webx-payments__row-title">${title}</span></span>${marks(list)}`

const only = (list) => (list.length === 1 && list[0] === 'apple-pay' ? 'data-apple-pay-only' : '')

const option = ([key, list], chosen) => `
  <label class="webx-payments__option" ${only(list)}>
    <input type="radio" name="method" value="${key}" ${chosen === key ? 'checked' : ''}>${row(key, list)}
  </label>`

/** A group: its key and methods as `[key, marks]`. */
function group([key, methods], chosen) {
  const isChosen = methods.some(([method]) => method === chosen) ? 'checked' : ''

  if (methods.length === 1) {
    const [method, list] = methods[0]
    return `<div class="webx-payments__group" data-group="${key}">
      <label class="webx-payments__group-head" ${only(list)}>
        <input type="radio" name="group" value="${key}" data-method="${method}" ${isChosen}>${row(method, list)}
      </label>
    </div>`
  }

  return `<div class="webx-payments__group" data-group="${key}">
    <label class="webx-payments__group-head">
      <input type="radio" name="group" value="${key}" ${isChosen}>
      <svg class="webx-payments__pictogram"></svg>${row(key, [...new Set(methods.flatMap(([, list]) => list))])}
    </label>
    <fieldset class="webx-payments__group-methods">${methods.map((method) => option(method, chosen)).join('')}</fieldset>
  </div>`
}

const list = (body, classes = '') =>
  `<fieldset class="webx-payments__groups ${classes}" data-webx-payments-methods>${body}</fieldset>`

const grouped = (groups, chosen = null) => list(groups.map((one) => group(one, chosen)).join(''))
const flat = (methods, chosen = null) =>
  list(methods.map((one) => option(one, chosen)).join(''), 'is-flat')

function page(html) {
  document.body.innerHTML = `<section data-webx-payments data-selection="/api/payments/selection">
    <form><input type="hidden" name="_token" value="t">${html}</form>
  </section>`
  return document.querySelector('[data-webx-payments-methods]')
}

const said = (root) => {
  const keys = []
  root.addEventListener('webx:payments:method', (event) => keys.push(event.detail.key))
  return keys
}

const titles = (root) =>
  [...root.querySelectorAll('.webx-payments__row-title')].map((title) => title.textContent)

const card = ['card', ['visa', 'apple-pay']]
const apple = ['apple', ['apple-pay']]
const google = ['google', ['google-pay']]
const cash = ['cash', []]

describe('methods.js and Apple Pay', () => {
  beforeEach(() => {
    delete window.ApplePaySession
    // `step.js` wakes on every page here, by its own observer.
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({ summary: null }) }),
    )
  })

  afterEach(() => {
    delete window.ApplePaySession
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('keeps everything where Apple Pay can work', () => {
    window.ApplePaySession = { canMakePayments: () => true }
    const root = page(grouped([['online', [card, apple]]]))

    checkAll()

    expect(titles(root)).toEqual(['online', 'card', 'apple'])
    expect(root.querySelectorAll('[data-mark="apple-pay"]')).toHaveLength(3)
  })

  it('keeps everything when the check breaks: not knowing is showing', () => {
    window.ApplePaySession = {
      canMakePayments() {
        throw new Error('insecure document')
      },
    }
    const root = page(grouped([['online', [card, apple, google]]]))

    checkAll()

    expect(titles(root)).toEqual(['online', 'card', 'apple', 'google'])
  })

  it('takes the method and the mark away, and never Google Pay', () => {
    const root = page(
      grouped([
        ['online', [card, apple, google]],
        ['receipt', [cash]],
      ]),
    )

    checkAll()

    expect(titles(root)).toEqual(['online', 'card', 'google', 'cash'])
    expect(root.querySelector('[data-mark="apple-pay"]')).toBe(null)
    expect(root.querySelector('[data-mark="google-pay"]')).not.toBe(null)
  })

  it('answers «no» when the session exists and says the device cannot', () => {
    window.ApplePaySession = { canMakePayments: () => false }
    const root = page(flat([card, apple]))

    checkAll()

    expect(titles(root)).toEqual(['card'])
  })

  it('takes a group of nothing but Apple Pay away whole', () => {
    const root = page(
      grouped([
        ['wallets', [apple]],
        ['online', [card, google]],
        ['receipt', [cash]],
      ]),
    )

    checkAll()

    expect(root.querySelector('[data-group="wallets"]')).toBe(null)
    expect(root.querySelectorAll('.webx-payments__group')).toHaveLength(2)
  })

  it('folds a group of one left into one row that speaks for the method', () => {
    const root = page(
      grouped([
        ['online', [card, apple]],
        ['receipt', [cash]],
      ]),
    )

    checkAll()

    const online = root.querySelector('[data-group="online"]')
    expect(online.querySelector('.webx-payments__group-methods')).toBe(null)
    expect(online.querySelector('.webx-payments__row-title').textContent).toBe('card')
    expect(online.querySelector('input').dataset.method).toBe('card')
    expect(online.querySelector('[data-mark="visa"]')).not.toBe(null)
  })

  it('drops the heads when one group is left', () => {
    const root = page(
      grouped([
        ['wallets', [apple]],
        ['online', [card, google]],
      ]),
    )

    checkAll()

    expect(root.querySelector('.webx-payments__group')).toBe(null)
    expect(root.classList.contains('is-flat')).toBe(true)
    expect(titles(root)).toEqual(['card', 'google'])
  })

  it('hides nothing, not even the mark, when that would leave no method', () => {
    const root = page(flat([apple]))

    checkAll()

    expect(titles(root)).toEqual(['apple'])
    expect(root.querySelector('[data-mark="apple-pay"]')).not.toBe(null)
  })

  it('moves a lost choice to the first method left in its group', () => {
    const root = page(
      grouped(
        [
          ['online', [apple, card, google]],
          ['receipt', [cash]],
        ],
        'apple',
      ),
    )
    const keys = said(root)

    checkAll()

    expect(keys).toEqual(['card'])
    expect(root.querySelector('input[name="method"]:checked').value).toBe('card')
    expect(root.querySelector('input[name="group"]:checked').value).toBe('online')
    expect(root.querySelector('[data-group="online"]').classList.contains('is-chosen')).toBe(true)
  })

  it('moves a lost choice to the one method left, folded into the head', () => {
    const root = page(
      grouped(
        [
          ['online', [apple, card]],
          ['receipt', [cash]],
        ],
        'apple',
      ),
    )
    const keys = said(root)

    checkAll()

    expect(keys).toEqual(['card'])
    expect(root.querySelector('input[name="group"]:checked').dataset.method).toBe('card')
  })

  it('drops the choice when its group has nobody left, and no group stays open', () => {
    const root = page(
      grouped(
        [
          ['wallets', [apple]],
          ['online', [card, google]],
          ['receipt', [cash]],
        ],
        'apple',
      ),
    )
    const keys = said(root)

    checkAll()

    expect(keys).toEqual([null])
    expect(root.querySelector('input:checked')).toBe(null)
    expect(root.querySelector('.is-chosen')).toBe(null)
  })

  it('chooses the one method left on the whole list, as a site with one method does', () => {
    const root = page(flat([apple, cash]))
    const keys = said(root)

    checkAll()

    expect(keys).toEqual(['cash'])
    expect(root.classList.contains('is-single')).toBe(true)
    expect(root.querySelector('input').checked).toBe(true)
  })

  it('leaves a choice that was not lost alone', () => {
    const root = page(
      grouped(
        [
          ['online', [apple, card]],
          ['receipt', [cash]],
        ],
        'cash',
      ),
    )
    const keys = said(root)

    checkAll()

    expect(keys).toEqual([])
    expect(root.querySelector('input:checked').value).toBe('receipt')
  })

  it('still chooses the first method left when a group is opened', () => {
    const root = page(
      grouped([
        ['online', [apple, card, google]],
        ['receipt', [cash]],
      ]),
    )
    const keys = said(root)
    checkAll()

    const head = root.querySelector('input[name="group"]')
    head.checked = true
    head.dispatchEvent(new Event('change', { bubbles: true }))

    expect(keys).toEqual(['card'])
  })

  it('sends the method of a folded group with a form that goes to the server', () => {
    const root = page(
      grouped([
        ['online', [apple, card]],
        ['transfer', [cash]],
      ]),
    )
    checkAll()

    root.querySelector('input[value="online"]').checked = true
    const form = root.closest('form')
    const event = new Event('formdata', { bubbles: true })
    event.formData = new Map()
    form.dispatchEvent(event)

    expect(event.formData.get('method')).toBe('card')
  })

  describe('with the step', () => {
    const answer = () =>
      vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({ summary: null }) })

    const lostChoice = () =>
      page(
        grouped(
          [
            ['wallets', [apple]],
            ['online', [card, google]],
            ['receipt', [cash]],
          ],
          'apple',
        ),
      )

    it('sends DELETE when the step listens first', async () => {
      const fetch = answer()
      vi.stubGlobal('fetch', fetch)
      lostChoice()

      startAll()
      checkAll()
      await Promise.resolve()

      expect(fetch).toHaveBeenCalledOnce()
      expect(fetch.mock.calls[0][1].method).toBe('DELETE')
      expect(fetch.mock.calls[0][1].body).toBeUndefined()
    })

    it('sends it once too when the list spoke before the step listened', async () => {
      const fetch = answer()
      vi.stubGlobal('fetch', fetch)
      const root = lostChoice()

      checkAll()
      startAll()
      await Promise.resolve()

      expect(fetch).toHaveBeenCalledOnce()
      expect(fetch.mock.calls[0][1].method).toBe('DELETE')
      expect(root.dataset.owed).toBeUndefined()
    })

    it('writes the method that took over with PUT', async () => {
      const fetch = answer()
      vi.stubGlobal('fetch', fetch)
      page(
        grouped(
          [
            ['online', [apple, card, google]],
            ['receipt', [cash]],
          ],
          'apple',
        ),
      )

      checkAll()
      startAll()
      await Promise.resolve()

      expect(fetch.mock.calls[0][1].method).toBe('PUT')
      expect(fetch.mock.calls[0][1].body).toBe(JSON.stringify({ method: 'card' }))
    })

    it('asks the checkout to redraw the step: a folded one still says the lost method', async () => {
      const heard = []
      const listen = (event) => heard.push(event.detail)
      document.addEventListener('webx:checkout:slot-changed', listen)
      lostChoice()
      document
        .querySelector('[data-webx-payments]')
        .setAttribute('data-checkout-event', 'webx:checkout:slot-changed')

      checkAll()
      startAll()
      await new Promise((resolve) => setTimeout(resolve))
      document.removeEventListener('webx:checkout:slot-changed', listen)

      expect(heard).toEqual([{ slot: 'payment', summary: null, redraw: true }])
    })

    it('leaves the step alone when the buyer chose', async () => {
      const heard = []
      const listen = (event) => heard.push(event.detail)
      document.addEventListener('webx:checkout:slot-changed', listen)
      const root = page(
        grouped([
          ['online', [card, google]],
          ['receipt', [cash]],
        ]),
      )
      document
        .querySelector('[data-webx-payments]')
        .setAttribute('data-checkout-event', 'webx:checkout:slot-changed')
      checkAll()
      startAll()

      const head = root.querySelector('input[name="group"]')
      head.checked = true
      head.dispatchEvent(new Event('change', { bubbles: true }))
      await new Promise((resolve) => setTimeout(resolve))
      document.removeEventListener('webx:checkout:slot-changed', listen)

      expect(heard).toEqual([{ slot: 'payment', summary: null, redraw: false }])
    })

    it('does not fall back on the form when a drop is refused', async () => {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({ ok: false, json: () => Promise.resolve({}) }),
      )
      const submit = vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {})
      lostChoice()

      startAll()
      checkAll()
      await new Promise((resolve) => setTimeout(resolve))

      expect(submit).not.toHaveBeenCalled()
      submit.mockRestore()
    })
  })
})
