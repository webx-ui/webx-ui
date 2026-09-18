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
(function () {
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
  }

  function init(form) {
    if (!form || form.dataset.webxReady === '1') return

    form.dataset.webxReady = '1'
    form.addEventListener('submit', function (event) {
      event.preventDefault()
      send(form)
    })
  }

  function send(form) {
    var submit = form.querySelector(HOOK.submit)

    clear(form)
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

    box.textContent = message || box.getAttribute('data-webx-failed') || form.dataset.webxFailed || ''
    box.hidden = box.textContent === ''
  }

  function clear(form) {
    Array.prototype.forEach.call(form.querySelectorAll(HOOK.field + '.is-invalid'), function (wrapper) {
      wrapper.classList.remove('is-invalid')
    })

    Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid]'), function (control) {
      control.removeAttribute('aria-invalid')
    })

    Array.prototype.forEach.call(form.querySelectorAll(HOOK.error + ', ' + HOOK.formError), function (box) {
      box.textContent = ''
      box.hidden = true
    })

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
   * A captcha token is good once. Without this, a visitor who is told their e-mail is wrong
   * corrects it, sends again, and is refused by a captcha they already passed — which reads
   * as a form that simply does not work.
   */
  function resetCaptcha(form) {
    var widget = form.querySelector('[data-webx-captcha]')

    if (!widget) return

    var provider = widget.getAttribute('data-webx-captcha')

    try {
      if (provider === 'turnstile' && window.turnstile) window.turnstile.reset()
      if (provider === 'recaptcha' && window.grecaptcha) window.grecaptcha.reset()
    } catch (error) {
      // A provider that is not loaded yet, or one that has nothing to reset. Neither is worth
      // taking the submission down over.
    }
  }

  /** A field name goes into a selector, and a machine name is not guaranteed to be tame. */
  function cssEscape(value) {
    return window.CSS && window.CSS.escape ? window.CSS.escape(value) : value.replace(/["\\]/g, '\\$&')
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
