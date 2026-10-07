/**
 * The form on the site, answering without reloading the page (§10).
 *
 * Progressive enhancement, in the strict sense: everything below is optional. Without this
 * file the form posts, the intake answers with a redirect, and the page comes back with the
 * errors or the thank-you in the session — which is the same two boxes filled by the same two
 * names. What this adds is that it happens in place.
 *
 * No dependencies, no build step, no framework. It is served from the package as one file, so
 * a site that never rebuilds anything still gets it; a site that would rather bundle it
 * publishes it (`webx-inbox-assets`) and switches `webx-inbox.script` off.
 */
;(function () {
  'use strict'

  var FORMS = 'form[data-webx-form]'

  /** Everything the script touches, as one list: rename a hook here and in the views. */
  var HOOK = {
    submit: '[data-webx-submit]',
    field: '[data-webx-field]',
    error: '[data-webx-error]',
    formError: '[data-webx-form-error]',
    message: '[data-webx-message]',
    heading: '[data-webx-message-heading]',
    text: '[data-webx-message-text]',
    captcha: '[data-webx-captcha]',
    captchaError: '[data-webx-captcha-error]',
  }

  function init(form) {
    if (!form || form.dataset.webxReady === '1') return

    form.dataset.webxReady = '1'
    form.addEventListener('submit', function (event) {
      event.preventDefault()
      clear(form)
      withCaptcha(form, function () {
        send(form)
      })
    })
  }

  function send(form) {
    var submit = form.querySelector(HOOK.submit)

    busy(form, submit, true)

    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (response) {
        // An answer that is not JSON at all — a proxy's error page, a site behind maintenance
        // — must not land in the console as a parse error with nothing on the page.
        return response
          .json()
          .catch(function () {
            return {}
          })
          .then(function (data) {
            return { status: response.status, ok: response.ok, data: data }
          })
      })
      .then(function (answer) {
        busy(form, submit, false)
        resetCaptcha(form)

        if (answer.ok) return accepted(form, answer.data)
        if (answer.status === 422) return refused(form, answer.data)

        // 429 and everything else: one sentence, and the server's own if it sent one.
        say(form, answer.data.message)
      })
      .catch(function () {
        busy(form, submit, false)
        resetCaptcha(form)
        say(form, null)
      })
  }

  function accepted(form, data) {
    if (data.redirect) {
      window.location.assign(data.redirect)

      return
    }

    form.reset()

    var box = form.querySelector(HOOK.message)

    if (!box) return

    var heading = box.querySelector(HOOK.heading)
    var text = box.querySelector(HOOK.text)
    var fallback = box.getAttribute('data-webx-fallback') || ''

    if (heading) heading.textContent = data.heading || (data.message ? '' : fallback)

    // The thank-you is written in the panel's editor and is meant to be HTML — the same trust
    // the page's own content is printed with, by the same people.
    if (text) text.innerHTML = data.message || ''

    box.hidden = false
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
  }

  /**
   * The errors of a 422, each under the input that caused it.
   *
   * They arrive named `fields.email` — which is `fields[email]` written the way the validator
   * writes it — and `fields.extras.1` for the second tick of one field, which belongs under
   * that field along with the rest.
   */
  function refused(form, data) {
    var errors = data.errors || {}
    var first = null

    Object.keys(errors).forEach(function (name) {
      var key = name.replace(/^fields\./, '').replace(/\.\d+$/, '')
      var messages = [].concat(errors[name]).join(' ')

      if (name === 'captcha') {
        sayCaptcha(form, messages)

        return
      }

      if (name === 'form' || key === name) {
        say(form, messages)

        return
      }

      var wrapper = form.querySelector('[data-webx-field="' + cssEscape(key) + '"]')

      if (!wrapper) {
        say(form, messages)

        return
      }

      wrapper.classList.add('is-invalid')

      var box = wrapper.querySelector(HOOK.error)

      if (box) {
        box.textContent = messages
        box.hidden = false
      }

      var control = wrapper.querySelector('input, select, textarea')

      if (control) control.setAttribute('aria-invalid', 'true')
      if (!first) first = control || wrapper
    })

    if (first && typeof first.focus === 'function') first.focus({ preventScroll: true })
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }

  /** The one refusal that belongs to no field: a rate limit, an antispam layer, a dead server. */
  function say(form, message) {
    var box = form.querySelector(HOOK.formError)

    if (!box) return

    box.textContent =
      message || box.getAttribute('data-webx-failed') || form.dataset.webxFailed || ''
    box.hidden = box.textContent === ''
  }

  function clear(form) {
    Array.prototype.forEach.call(
      form.querySelectorAll(HOOK.field + '.is-invalid'),
      function (wrapper) {
        wrapper.classList.remove('is-invalid')
      },
    )

    Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid]'), function (control) {
      control.removeAttribute('aria-invalid')
    })

    Array.prototype.forEach.call(
      form.querySelectorAll(HOOK.error + ', ' + HOOK.formError + ', ' + HOOK.captchaError),
      function (box) {
        box.textContent = ''
        box.hidden = true
      },
    )

    var message = form.querySelector(HOOK.message)

    if (message) message.hidden = true
  }

  function busy(form, submit, on) {
    if (!submit) return

    submit.disabled = on

    var busyText = submit.getAttribute('data-busy')

    if (!busyText) return

    if (on) {
      submit.setAttribute('data-idle', submit.textContent)
      submit.textContent = busyText

      return
    }

    var idle = submit.getAttribute('data-idle')

    if (idle !== null) submit.textContent = idle
  }

  /**
   * The captcha's answer, then the submission.
   *
   * Three kinds behave three ways (`webx-inbox.captcha.recaptcha.type` and
   * `webx-inbox.captcha.turnstile.mode`). A checkbox — reCAPTCHA v2, or Turnstile drawn on load —
   * is answered by the visitor before they press the button, so an empty answer is stopped here
   * with the sentence the intake would have sent back. An invisible one, of either provider, is
   * drawn on the first submit and run then; its callback sends the form, and a visitor who
   * closes a challenge simply presses the button again. reCAPTCHA v3 asks for a score for this
   * form's action and sends the form with it.
   *
   * A provider whose script never arrived — blocked, offline — is not waited for: the form is
   * sent, and the intake's refusal says what is missing.
   */
  function withCaptcha(form, proceed) {
    var widget = form.querySelector(HOOK.captcha)

    if (!widget) return proceed()

    var provider = widget.getAttribute('data-webx-captcha')
    var type = widget.getAttribute('data-webx-captcha-type') || 'checkbox'
    var key = widget.getAttribute('data-webx-captcha-key') || ''

    if (type === 'checkbox') {
      var answer = widget.querySelector(
        '[name="' +
          (provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response') +
          '"]',
      )

      if (answer && !answer.value) {
        sayCaptcha(form, widget.getAttribute('data-webx-captcha-message'))

        return
      }

      return proceed()
    }

    if (provider === 'recaptcha' && type === 'v3') {
      var grecaptcha = window.grecaptcha

      if (!grecaptcha || typeof grecaptcha.ready !== 'function') return proceed()

      grecaptcha.ready(function () {
        grecaptcha
          .execute(key, { action: widget.getAttribute('data-webx-captcha-action') || 'submit' })
          .then(
            function (token) {
              var input = widget.querySelector('[name="g-recaptcha-response"]')

              if (input) input.value = token
              proceed()
            },
            function () {
              proceed()
            },
          )
      })

      return
    }

    // Invisible, either provider: one widget per form, drawn on the first submit and
    // remembered by its id, because the providers' execute and reset act on the first widget
    // of the page without one. Run on submit rather than on load, so the token is fresh — a
    // Turnstile token made when the page opened is dead after five minutes of typing.
    var api = provider === 'turnstile' ? window.turnstile : window.grecaptcha
    var box = widget.querySelector('[data-sitekey]')

    if (!api || typeof api.render !== 'function' || !box) return proceed()

    widget.webxProceed = proceed

    // A provider that could not vouch for this browser leaves no token, and posting without
    // one only earns a refusal that says less than this does. The visitor is told now; the
    // next press of the button tries once more from a clean widget.
    var failed = function () {
      widget.dataset.webxCaptchaState = 'error'
      widget.webxProceed = null
      sayCaptcha(form, widget.getAttribute('data-webx-captcha-unavailable'))

      // Handled: Turnstile throws an uncaught error for every one it is not told about.
      return true
    }

    var run = function () {
      if (widget.dataset.webxWidget === undefined) {
        var settings = {
          sitekey: key,
          callback: function () {
            widget.dataset.webxCaptchaState = 'ready'

            var next = widget.webxProceed

            widget.webxProceed = null
            if (next) next()
          },
          'error-callback': failed,
          'expired-callback': function () {
            widget.dataset.webxCaptchaState = 'expired'
          },
        }

        if (provider === 'turnstile') {
          // Nothing on the page unless Cloudflare really needs the visitor to do something,
          // and no retrying on its own: a browser Cloudflare distrusts fails the same way
          // every few seconds, for as long as the page is open. The visitor's next press of
          // the button is the retry.
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
  }

  /** reCAPTCHA numbers its widgets, Turnstile names them. */
  function widgetId(widget, provider) {
    var id = widget.dataset.webxWidget

    return provider === 'recaptcha' ? Number(id) : id
  }

  /** What the intake said about the captcha, beside the widget it is about. */
  function sayCaptcha(form, message) {
    var box = form.querySelector(HOOK.captchaError)

    if (!box) return say(form, message)

    box.textContent = message || ''
    box.hidden = box.textContent === ''
  }

  /**
   * A captcha token is good once. Without this, a visitor who is told their e-mail is wrong
   * corrects it, sends again, and is refused by a captcha they already passed — which reads
   * as a form that simply does not work.
   *
   * Every reset names its widget: a page may carry two forms with a captcha each, and the
   * providers' reset without one resets the first widget on the page, not this form's.
   */
  function resetCaptcha(form) {
    var widget = form.querySelector(HOOK.captcha)

    if (!widget) return

    var provider = widget.getAttribute('data-webx-captcha')
    var type = widget.getAttribute('data-webx-captcha-type') || 'checkbox'

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

      // A reCAPTCHA checkbox is drawn by the provider's script, which numbers the widgets it
      // finds in the order they stand on the page — so the place of this one is its id.
      var drawn = Array.prototype.indexOf.call(
        document.querySelectorAll('.g-recaptcha'),
        widget.querySelector('.g-recaptcha'),
      )

      if (drawn >= 0) api.reset(drawn)
    } catch {
      // A provider that is not loaded yet, or one that has nothing to reset. Neither is worth
      // taking the submission down over.
    }
  }

  /** A field name goes into a selector, and a machine name is not guaranteed to be tame. */
  function cssEscape(value) {
    return window.CSS && window.CSS.escape
      ? window.CSS.escape(value)
      : value.replace(/["\\]/g, '\\$&')
  }

  function scan(root) {
    Array.prototype.forEach.call((root || document).querySelectorAll(FORMS), init)
  }

  // A form that arrives later — in a dialog, from a fragment the site fetched — is somebody
  // else's to announce, so the door is left open.
  window.webxInbox = { init: init, scan: scan }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      scan()
    })
  } else {
    scan()
  }
})()
