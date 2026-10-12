/**
 * The inbox's half of sending a form (§10): the captcha. Everything else — sending in place, the
 * errors under the fields, the checks in the browser, the thank-you, a form in a dialog — is the
 * widgets' form engine (form.js, WIDGETS §17), and this only listens to its events:
 *
 *  - `webx:form:submit` — before the form goes, the captcha's answer: a checkbox nobody ticked
 *    stops it with the sentence the intake would send back; an invisible one (reCAPTCHA or
 *    Turnstile) is run now, and its answer sends the form again; reCAPTCHA v3 asks for a score
 *    for this form's action and sends the form with it;
 *  - `webx:form:success`, `webx:form:error` — a captcha answer is good once: the widget is reset,
 *    so a visitor told their e-mail is wrong is not refused by a captcha they already passed.
 *
 * Printed only by a form that draws a captcha. No dependencies, no build step: served from the
 * package as one file (`webx-inbox-assets` publishes it). A provider whose script never arrived —
 * blocked, offline — is not waited for: the form is sent, and the intake's refusal says what is
 * missing, under the widget (`data-webx-field="captcha"`).
 */
;(function () {
  'use strict'

  if (window.webxInboxCaptcha) return
  window.webxInboxCaptcha = true

  var CAPTCHA = '[data-webx-captcha]'

  function widgetOf(form) {
    if (!(form instanceof HTMLFormElement)) return null

    var widget = form.querySelector(CAPTCHA)

    return widget && widget.closest('form') === form ? widget : null
  }

  /** The intake's word, where form.js puts it too: the error of the captcha's place. */
  function say(widget, message) {
    var box = widget.querySelector('.webx-field__error')

    widget.classList.toggle('is-invalid', !!message)
    if (!box) return
    box.textContent = message || ''
    box.hidden = !message
  }

  /** The same press of the same button once more — now with the captcha's answer in the form. */
  function resend(form, submitter) {
    var button = submitter && submitter.form === form ? submitter : undefined

    if (typeof form.requestSubmit === 'function') form.requestSubmit(button)
    else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
  }

  /** reCAPTCHA numbers its widgets, Turnstile names them. */
  function widgetId(widget, provider) {
    var id = widget.dataset.webxWidget

    return provider === 'recaptcha' ? Number(id) : id
  }

  document.addEventListener('webx:form:submit', function (event) {
    var form = event.target
    var widget = widgetOf(form)

    if (!widget) return

    // Answered a moment ago and sent again by the answer itself: let it go.
    if (widget.dataset.webxCaptchaState === 'answered') return

    var provider = widget.getAttribute('data-webx-captcha')
    var type = widget.getAttribute('data-webx-captcha-type') || 'checkbox'
    var key = widget.getAttribute('data-webx-captcha-key') || ''
    var submitter = (event.detail && event.detail.submitter) || null
    var answered = function () {
      widget.dataset.webxCaptchaState = 'answered'
      resend(form, submitter)
    }

    if (type === 'checkbox') {
      var answer = widget.querySelector(
        '[name="' +
          (provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response') +
          '"]',
      )

      if (answer && !answer.value) {
        event.preventDefault()
        say(widget, widget.getAttribute('data-webx-captcha-message'))
      }

      return
    }

    if (provider === 'recaptcha' && type === 'v3') {
      var grecaptcha = window.grecaptcha

      if (!grecaptcha || typeof grecaptcha.ready !== 'function') return

      event.preventDefault()
      grecaptcha.ready(function () {
        grecaptcha
          .execute(key, { action: widget.getAttribute('data-webx-captcha-action') || 'submit' })
          .then(function (token) {
            var input = widget.querySelector('[name="g-recaptcha-response"]')

            if (input) input.value = token
            answered()
          }, answered)
      })

      return
    }

    // Invisible, either provider: one widget per form, drawn on the first submit and remembered
    // by its id, because the providers' execute and reset act on the first widget of the page
    // without one. Run on submit rather than on load, so the answer is fresh — a Turnstile token
    // made when the page opened is dead after five minutes of typing.
    var api = provider === 'turnstile' ? window.turnstile : window.grecaptcha
    var box = widget.querySelector('[data-sitekey]')

    if (!api || typeof api.render !== 'function' || !box) return

    event.preventDefault()
    widget.webxAnswered = answered

    // A provider that could not vouch for this browser leaves no answer, and posting without one
    // only earns a refusal that says less than this does. The next press tries once more.
    var failed = function () {
      widget.dataset.webxCaptchaState = 'error'
      widget.webxAnswered = null
      say(widget, widget.getAttribute('data-webx-captcha-unavailable'))

      // Handled: Turnstile throws an uncaught error for every one it is not told about.
      return true
    }

    var run = function () {
      if (widget.dataset.webxWidget === undefined) {
        var settings = {
          sitekey: key,
          callback: function () {
            var next = widget.webxAnswered

            widget.webxAnswered = null
            if (next) next()
          },
          'error-callback': failed,
          'expired-callback': function () {
            widget.dataset.webxCaptchaState = 'expired'
          },
        }

        if (provider === 'turnstile') {
          // Nothing on the page unless Cloudflare really needs the visitor, and no retrying on
          // its own: a browser it distrusts fails the same way every few seconds for as long as
          // the page is open. The visitor's next press of the button is the retry.
          settings.appearance = 'interaction-only'
          settings.execution = 'execute'
          settings.retry = 'never'
          settings['timeout-callback'] = failed
        } else {
          settings.size = 'invisible'
        }

        widget.dataset.webxWidget = String(api.render(box, settings))
      } else if (widget.dataset.webxCaptchaState === 'error') {
        api.reset(widgetId(widget, provider))
      }

      widget.dataset.webxCaptchaState = 'running'
      api.execute(widgetId(widget, provider))
    }

    // reCAPTCHA has to be asked whether it is ready; Turnstile is, once its script is here.
    if (provider === 'recaptcha' && typeof api.ready === 'function') api.ready(run)
    else run()
  })

  /**
   * Every reset names its widget: a page may carry two forms with a captcha each, and the
   * providers' reset without one resets the first widget on the page, not this form's.
   */
  function reset(event) {
    var widget = widgetOf(event.target)

    if (!widget) return

    var provider = widget.getAttribute('data-webx-captcha')
    var type = widget.getAttribute('data-webx-captcha-type') || 'checkbox'

    if (widget.dataset.webxCaptchaState === 'answered') delete widget.dataset.webxCaptchaState

    try {
      if (provider === 'recaptcha' && type === 'v3') {
        var input = widget.querySelector('[name="g-recaptcha-response"]')

        if (input) input.value = ''

        return
      }

      var api = provider === 'turnstile' ? window.turnstile : window.grecaptcha

      if (!api) return

      if (type === 'invisible') {
        if (widget.dataset.webxWidget !== undefined) api.reset(widgetId(widget, provider))

        return
      }

      if (provider === 'turnstile') {
        // Turnstile takes the container it drew in.
        api.reset(widget.querySelector('.cf-turnstile') || undefined)

        return
      }

      // A reCAPTCHA checkbox is drawn by the provider's script, which numbers the widgets it finds
      // in the order they stand on the page — so the place of this one is its id.
      var drawn = Array.prototype.indexOf.call(
        document.querySelectorAll('.g-recaptcha'),
        widget.querySelector('.g-recaptcha'),
      )

      if (drawn >= 0) api.reset(drawn)
    } catch {
      // A provider that is not loaded yet, or one with nothing to reset: neither is worth more.
    }
  }

  document.addEventListener('webx:form:success', reset)
  document.addEventListener('webx:form:error', reset)
})()
