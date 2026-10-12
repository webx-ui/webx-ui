/**
 * inbox.js since W6.4: only the captcha, on the events of the widgets' form engine. Sending, the
 * errors and the thank-you are form.js's (php/packages/widgets/resources/js/form.test.js).
 */
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

beforeAll(async () => {
  await import('./inbox.js')
})

afterEach(() => {
  document.body.innerHTML = ''
  delete window.grecaptcha
  delete window.turnstile
})

function page({ provider = 'turnstile', type = 'checkbox', widget = true } = {}) {
  document.body.innerHTML = `
    <form class="webx-form webx-inbox-form" data-webx-send data-webx-form="contact">
      <div class="webx-form__body">
        <input name="fields[name]" value="Ada">
        ${
          widget
            ? `<div class="webx-inbox-form__captcha" data-webx-field="captcha"
                data-webx-captcha="${provider}" data-webx-captcha-type="${type}"
                data-webx-captcha-key="site-key" data-webx-captcha-action="webx_form_contact"
                data-webx-captcha-message="Please confirm you are not a robot."
                data-webx-captcha-unavailable="The check could not run.">
                ${
                  type === 'v3'
                    ? '<input type="hidden" name="g-recaptcha-response" value="">'
                    : type === 'checkbox'
                      ? `<div class="${provider === 'turnstile' ? 'cf-turnstile' : 'g-recaptcha'}" data-sitekey="site-key"><input name="${provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response'}" value=""></div>`
                      : '<div class="webx-inbox-form__captcha-widget" data-sitekey="site-key"></div>'
                }
                <p class="webx-field__error" hidden></p>
              </div>`
            : ''
        }
        <button type="submit" class="webx-form__submit">Send</button>
      </div>
    </form>`
  const form = document.querySelector('form')
  form.requestSubmit = vi.fn()
  return {
    form,
    widget: form.querySelector('[data-webx-captcha]'),
    error: form.querySelector('.webx-field__error'),
    button: form.querySelector('button'),
  }
}

/** What form.js does before sending: `webx:form:submit`, cancelable. False — it was cancelled. */
const submit = (form, submitter = null) =>
  form.dispatchEvent(
    new CustomEvent('webx:form:submit', {
      bubbles: true,
      cancelable: true,
      detail: { data: new FormData(form), submitter },
    }),
  )

const answered = (form, name) =>
  form.dispatchEvent(new CustomEvent(`webx:form:${name}`, { bubbles: true, detail: {} }))

describe('inbox.js — the captcha on the form engine', () => {
  it('leaves a form without a captcha alone', () => {
    const { form } = page({ widget: false })
    expect(submit(form)).toBe(true)
    expect(form.requestSubmit).not.toHaveBeenCalled()
  })

  it('stops a checkbox nobody ticked with the intake’s sentence, in the captcha’s place', () => {
    const { form, widget, error } = page({ provider: 'turnstile' })
    expect(submit(form)).toBe(false)
    expect(error.hidden).toBe(false)
    expect(error.textContent).toBe('Please confirm you are not a robot.')
    expect(widget.classList.contains('is-invalid')).toBe(true)

    // Ticked: it goes.
    widget.querySelector('[name="cf-turnstile-response"]').value = 'token'
    expect(submit(form)).toBe(true)
  })

  it('asks reCAPTCHA v3 for a score for the form’s action and sends the same press again with it', async () => {
    const execute = vi.fn(() => Promise.resolve('v3-token'))
    window.grecaptcha = { ready: (run) => run(), execute }
    const { form, widget, button } = page({ provider: 'recaptcha', type: 'v3' })

    expect(submit(form, button)).toBe(false)
    await Promise.resolve()
    await Promise.resolve()
    expect(execute).toHaveBeenCalledWith('site-key', { action: 'webx_form_contact' })
    expect(widget.querySelector('[name="g-recaptcha-response"]').value).toBe('v3-token')
    expect(form.requestSubmit).toHaveBeenCalledWith(button)

    // The press sent again goes through, once; the answer is good once — gone after the reply.
    expect(submit(form, button)).toBe(true)
    answered(form, 'error')
    expect(widget.querySelector('[name="g-recaptcha-response"]').value).toBe('')
    expect(submit(form, button)).toBe(false)
  })

  it('draws an invisible Turnstile on the first press, runs it, and sends when it answers', () => {
    let settings
    window.turnstile = {
      render: vi.fn((box, given) => {
        settings = given
        return 'w1'
      }),
      execute: vi.fn(),
      reset: vi.fn(),
    }
    const { form, widget, error, button } = page({ provider: 'turnstile', type: 'invisible' })

    expect(submit(form, button)).toBe(false)
    expect(window.turnstile.render).toHaveBeenCalledTimes(1)
    expect(settings).toMatchObject({
      sitekey: 'site-key',
      appearance: 'interaction-only',
      execution: 'execute',
      retry: 'never',
    })
    expect(window.turnstile.execute).toHaveBeenCalledWith('w1')

    settings.callback('token')
    expect(form.requestSubmit).toHaveBeenCalledWith(button)
    expect(submit(form, button)).toBe(true)

    // After the reply the widget is reset by its id; the next press runs it again, not draws it.
    answered(form, 'success')
    expect(window.turnstile.reset).toHaveBeenCalledWith('w1')
    expect(submit(form, button)).toBe(false)
    expect(window.turnstile.render).toHaveBeenCalledTimes(1)
    expect(window.turnstile.execute).toHaveBeenCalledTimes(2)

    // A browser it could not vouch for: said, nothing sent, and the next press starts clean.
    expect(settings['error-callback']()).toBe(true)
    expect(error.textContent).toBe('The check could not run.')
    expect(widget.dataset.webxCaptchaState).toBe('error')
    expect(submit(form, button)).toBe(false)
    expect(window.turnstile.reset).toHaveBeenLastCalledWith('w1')
  })

  it('sizes an invisible reCAPTCHA as invisible and waits until it is ready', () => {
    let settings
    const ready = vi.fn((run) => run())
    window.grecaptcha = {
      ready,
      render: vi.fn((box, given) => {
        settings = given
        return 0
      }),
      execute: vi.fn(),
      reset: vi.fn(),
    }
    const { form } = page({ provider: 'recaptcha', type: 'invisible' })

    expect(submit(form)).toBe(false)
    expect(ready).toHaveBeenCalled()
    expect(settings.size).toBe('invisible')
    expect(window.grecaptcha.execute).toHaveBeenCalledWith(0)
  })

  it('does not wait for a provider whose script never came: the intake says what is missing', () => {
    const { form } = page({ provider: 'turnstile', type: 'invisible' })
    expect(submit(form)).toBe(true)
  })

  it('resets a reCAPTCHA checkbox by its place on the page after any reply', () => {
    window.grecaptcha = { reset: vi.fn() }
    const { form } = page({ provider: 'recaptcha' })
    answered(form, 'error')
    expect(window.grecaptcha.reset).toHaveBeenCalledWith(0)
  })
})
