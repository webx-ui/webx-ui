/*
 * The shell of a form (spec §17.3, §17.4). Its own file, claimed by `<x-webx-form>`.
 *
 * Everything here is on top of a form that works without it: a plain POST, a redirect back,
 * the errors under the fields. What this adds is the same in place:
 *
 *  - the submission as `fetch` with `Accept: application/json` — or `XMLHttpRequest` when a file
 *    is going, for the upload's progress;
 *  - the token from the XSRF-TOKEN cookie, not from the HTML: a page is cached with the token it
 *    was printed with (INBOX decision 8). A 419 gets a fresh one from the package's route and the
 *    request is sent again — once;
 *  - `is-sending` and `aria-busy` on the form and the buttons held back: a second press while the
 *    first is on its way sends nothing;
 *  - Laravel's 422 as it is: each error into the place its field already has (W6.1 printed it,
 *    hidden, and described the control by it), what belongs to no field into the form's alert,
 *    the focus on the first field wrong; an error goes as soon as its field is changed;
 *  - the answer of FormResponse: off to `redirect`, `html` in place of the fields, `message`
 *    under them in a live region — in a dialog in place of them, with a button that closes it;
 *  - `webx:form:submit` (cancelable), `webx:form:success`, `webx:form:error` on the form.
 */

import '../css/form.css'

const webx = (window.webx ??= {})

// Not core.js's: a module both import would be a chunk of its own, one more request on every page.
const listeners = () => {
  const added = []
  const on = (target, type, listener) => {
    target.addEventListener(type, listener)
    added.push(() => target.removeEventListener(type, listener))
  }
  on.off = () => added.splice(0).forEach((remove) => remove())
  return on
}

/** Where a redirect goes: the page itself, or a test's stand-in (jsdom cannot navigate). */
export const navigation = { go: (url) => window.location.assign(url) }

/** The token Laravel left in its cookie, as the header `X-XSRF-TOKEN` wants it. */
export function token() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : null
}

const own = (form, selector) =>
  Array.from(form.querySelectorAll(selector)).find((el) => el.closest('form') === form) ?? null

const fieldsOf = (form) =>
  Array.from(form.querySelectorAll('[data-webx-field]')).filter((el) => el.closest('form') === form)

const controlsOf = (field) =>
  Array.from(field.querySelectorAll('input, select, textarea')).filter((el) => el.type !== 'hidden')

const buttonsOf = (form) =>
  Array.from(
    form.querySelectorAll('button:not([type]), button[type=submit], input[type=submit]'),
  ).filter((el) => el.closest('form') === form)

/**
 * The field an error of the validator is about: `fields.email` is the field of that name,
 * `extras.1` an item of the group `extras`, `items.0.qty` a field of its own if the form has one.
 */
export function fieldFor(form, key) {
  const fields = new Map(fieldsOf(form).map((field) => [field.dataset.webxField, field]))
  for (
    let name = key;
    name;
    name = name.includes('.') ? name.slice(0, name.lastIndexOf('.')) : ''
  ) {
    if (fields.has(name)) return fields.get(name)
  }
  return null
}

function invalid(field, messages) {
  field.classList.add('is-invalid')
  const box = field.querySelector('.webx-field__error')
  if (box) {
    box.textContent = messages.join(' ')
    box.hidden = false
  }
  // Each control of a group: `aria-invalid` on a <fieldset> is read by nobody.
  for (const control of controlsOf(field)) control.setAttribute('aria-invalid', 'true')
}

function valid(field) {
  if (!field?.classList.contains('is-invalid')) return
  field.classList.remove('is-invalid')
  const box = field.querySelector('.webx-field__error')
  if (box) {
    box.textContent = ''
    box.hidden = true
  }
  for (const control of controlsOf(field)) control.removeAttribute('aria-invalid')
}

/** The form's own alert: what belongs to no field, or why nothing could be sent. */
function refuse(form, messages) {
  const box = own(form, '.webx-form__error')
  if (!box) return
  if (messages.length === 0) return box.replaceChildren()
  // A box inside the alert, so that an empty alert takes no room and the rule stays one class.
  const list = document.createElement('div')
  list.className = 'webx-form__refusals'
  for (const message of messages) {
    const line = document.createElement('p')
    line.className = 'webx-form__refusal'
    line.textContent = message
    list.append(line)
  }
  box.replaceChildren(list)
}

function say(form, heading, message) {
  const box = own(form, '.webx-form__message')
  if (!box) return
  const parts = []
  if (heading) {
    const line = document.createElement('p')
    line.className = 'webx-form__heading'
    line.textContent = heading
    parts.push(line)
  }
  if (message) {
    // The site's own HTML, written by the controller that answers: FormResponse::message().
    const text = document.createElement('div')
    text.className = 'webx-form__text'
    text.innerHTML = message
    parts.push(text)
  }
  if (parts.length === 0) return box.replaceChildren()
  const answer = document.createElement('div')
  answer.className = 'webx-form__answer'
  answer.append(...parts)
  box.replaceChildren(answer)
}

function clear(form) {
  for (const field of fieldsOf(form)) valid(field)
  refuse(form, [])
  say(form, null, null)
}

const parse = (text) => {
  try {
    return { data: JSON.parse(text), json: true }
  } catch {
    // A proxy's error page, a maintenance page, a controller that answered with HTML.
    return { data: {}, json: false }
  }
}

function headers() {
  const all = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const xsrf = token()
  if (xsrf) all['X-XSRF-TOKEN'] = xsrf
  return all
}

/** One request; `{ status, data, json, url }` whichever way it went. */
function request(action, body, progress) {
  const files = Array.from(body.values()).some((value) => value instanceof File && value.name)

  if (!files) {
    return fetch(action, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: headers(),
    }).then(async (response) => ({
      status: response.status,
      url: response.redirected ? response.url : null,
      ...parse(await response.text()),
    }))
  }

  // fetch says nothing of an upload's progress; XMLHttpRequest does.
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', action)
    xhr.withCredentials = true
    for (const [name, value] of Object.entries(headers())) xhr.setRequestHeader(name, value)
    xhr.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) progress(Math.round((event.loaded / event.total) * 100))
    })
    xhr.addEventListener('load', () =>
      resolve({
        status: xhr.status,
        url:
          xhr.responseURL && xhr.responseURL !== new URL(action, location.href).href
            ? xhr.responseURL
            : null,
        ...parse(xhr.responseText),
      }),
    )
    xhr.addEventListener('error', () => reject(new Error('network')))
    xhr.send(body)
  })
}

export function form(el) {
  if (!(el instanceof HTMLFormElement)) return undefined
  const on = listeners()
  const body = own(el, '.webx-form__body')
  const bar = own(el, '.webx-form__progress')
  const done = own(el, '.webx-form__done')
  const refresh = el.getAttribute('data-webx-csrf')
  const dialog = el.closest('dialog')
  // An `action` input would shadow the property: the attribute is the address.
  const action = el.getAttribute('action') || location.href
  let sending = false

  const busy = (state) => {
    sending = state
    el.classList.toggle('is-sending', state)
    if (state) el.setAttribute('aria-busy', 'true')
    else el.removeAttribute('aria-busy')
    // Held back, not disabled: a disabled button drops the focus of the one who pressed it.
    for (const button of buttonsOf(el)) {
      if (state) button.setAttribute('aria-disabled', 'true')
      else button.removeAttribute('aria-disabled')
    }
  }

  const progress = (percent) => {
    if (!bar) return
    bar.hidden = percent === null
    bar.value = percent ?? 0
  }

  // A dialog shows the thank-you in place of the fields and a button that closes it; closed, it
  // is a fresh form again. It never closes by itself: the visitor has to have time to read.
  const aside = () => {
    if (!dialog || !body || !done) return
    body.hidden = true
    done.hidden = false
    done.focus()
  }

  const fresh = () => {
    if (!el.classList.contains('is-sent')) return
    el.classList.remove('is-sent')
    if (body) body.hidden = false
    if (done) done.hidden = true
    say(el, null, null)
    el.reset()
  }

  const emit = (name, detail, cancelable = false) =>
    el.dispatchEvent(new CustomEvent(`webx:form:${name}`, { bubbles: true, cancelable, detail }))

  const focusFirst = () => {
    const first = fieldsOf(el).find((field) => field.classList.contains('is-invalid'))
    const control = first ? controlsOf(first)[0] : null
    if (!control) return
    control.focus({ preventScroll: true })
    control.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
  }

  const succeed = (answer) => {
    const data = answer.json ? answer.data : {}
    // A controller that answered a redirect rather than JSON: the browser followed it already.
    const redirect = data.redirect || (!answer.json && answer.url) || null
    emit('success', { status: answer.status, response: data })

    if (redirect) {
      navigation.go(redirect)
      return true
    }

    if (typeof data.html === 'string' && body) {
      webx.unmount?.(body)
      body.innerHTML = data.html
      webx.mount?.(body)
      el.classList.add('is-sent')
    } else if (data.heading || data.message) {
      say(el, data.heading, data.message)
      el.classList.add('is-sent')
      el.reset()
      aside()
      if (!dialog)
        own(el, '.webx-form__message')?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' })
    }
    return false
  }

  const reject = (answer) => {
    const data = answer.json ? answer.data : {}
    const errors =
      answer.status === 422 && data.errors && typeof data.errors === 'object' ? data.errors : {}
    const unplaced = []
    const byField = new Map()

    for (const [key, messages] of Object.entries(errors)) {
      const list = [].concat(messages).map(String)
      const field = fieldFor(el, key)
      if (field) byField.set(field, [...(byField.get(field) ?? []), ...list])
      else unplaced.push(...list)
    }
    for (const [field, messages] of byField) invalid(field, messages)

    const box = own(el, '.webx-form__error')
    if (answer.status === 422) {
      // Laravel's own sentence sums the errors up — said already where they are.
      if (unplaced.length === 0 && byField.size === 0 && data.message)
        unplaced.push(String(data.message))
    } else {
      unplaced.push(
        box?.getAttribute(answer.status === 419 ? 'data-webx-expired' : 'data-webx-failed') ?? '',
      )
    }
    refuse(el, unplaced.filter(Boolean))
    focusFirst()
    emit('error', { status: answer.status, errors, message: data.message ?? null })
  }

  on(el, 'submit', async (event) => {
    event.preventDefault()
    if (sending) return

    let data
    try {
      data = new FormData(el, event.submitter ?? undefined)
    } catch {
      data = new FormData(el)
    }

    if (!emit('submit', { data, submitter: event.submitter ?? null }, true)) return

    clear(el)
    busy(true)
    let leaving = false

    try {
      const send = () => {
        // The cookie's token is the fresh one; the HTML's may have been cached with the page,
        // and Laravel would read `_token` first.
        if (token()) data.delete('_token')
        return request(action, data, progress)
      }

      let answer = await send()

      if (answer.status === 419 && refresh) {
        await fetch(refresh, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json' },
        })
        answer = await send()
      }

      if (answer.status >= 200 && answer.status < 300) leaving = succeed(answer)
      else reject(answer)
    } catch {
      reject({ status: 0, data: {}, json: false })
    } finally {
      progress(null)
      // Off to another page: the form stays held until it is gone.
      if (!leaving) busy(false)
    }
  })

  // A corrected field loses its error as it is corrected.
  const changed = (event) =>
    valid(event.target instanceof Element ? event.target.closest('[data-webx-field]') : null)
  on(el, 'input', changed)
  on(el, 'change', changed)

  let watch = null
  if (dialog) {
    watch = new MutationObserver(() => {
      if (!dialog.open) fresh()
    })
    watch.observe(dialog, { attributes: true, attributeFilter: ['open'] })
  }

  // The page came back from this form without the script having sent it — a cached page, a
  // browser that had it switched off: a dialog it stands in opens again as a modal.
  if (el.hasAttribute('data-webx-returned')) {
    if (dialog?.open && typeof webx.openDialog === 'function') {
      dialog.close()
      webx.openDialog(dialog)
    }
    if (el.classList.contains('is-sent')) aside()
    else focusFirst()
  }

  return () => {
    on.off()
    watch?.disconnect()
    if (sending) busy(false)
  }
}

webx.widget?.('form', 'form[data-webx-send]', form)
