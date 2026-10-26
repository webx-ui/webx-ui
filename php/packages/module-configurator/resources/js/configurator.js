/*
 * The configurator on the site (§12.4): the widget `configurator` on `[data-webx-configurator]`.
 *
 * The server printed every step, option and field, the price of the start and its breakdown; this
 * only switches (decision 9) — `hidden` and `disabled` of what the choice hides, `is-selected`, the
 * sizes in their limits, the price, the struck-out price of a discount, the lines and the picture.
 * There is no second markup here: a line of the breakdown is a copy of the server's.
 *
 * The engine is the npm package's own source, built in (`packages/module-configurator/src/engine`):
 * the same text the parity cases hold to the server's. What it counts is only shown — the request
 * is priced again by the server from the radios and the sizes, which belong to the request form by
 * `form=`; no price is ever sent.
 *
 * It mounts through `webx.widget` of the widgets' runtime when the page has it — on the page, in a
 * block the panel swapped in a preview, in a piece loaded later — and on its own otherwise. The
 * runtime is a module script printed after this one, so it is waited for until the document is
 * parsed.
 */

import {
  chosenOptions,
  mainStep,
  price,
  resolve,
  select,
  set,
  steps,
  today,
  visible,
} from '../../../../../packages/module-configurator/src/engine/engine'

const SELECTOR = '[data-webx-configurator]'

/** A sum as the server prints it: `Intl` with the narrow symbol, no fraction when whole. */
function money(value, currency, locale) {
  const whole = Math.abs(value - Math.round(value)) < 0.005
  const digits = whole ? 0 : 2

  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      currencyDisplay: 'narrowSymbol',
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
    }).format(value)
  } catch {
    return `${number(value, locale)} ${currency}`.trim()
  }
}

/** A rate or an area: up to two digits, as needed. */
function number(value, locale) {
  try {
    return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(value)
  } catch {
    return String(value)
  }
}

/** `:name` of the server's words, the way Laravel's `trans()` fills them. */
function fill(template, values) {
  return String(template ?? '').replace(/:([a-z]+)/g, (all, name) =>
    name in values ? String(values[name]) : all,
  )
}

/** The words of every step and option, by key — what a line of the breakdown names. */
function labels(schema) {
  const out = { steps: {}, options: {} }

  for (const step of steps(schema)) {
    out.steps[step.key] = step.label || step.title || step.key
    out.options[step.key] = {}

    for (const option of Array.isArray(step.options) ? step.options : []) {
      if (option && option.value !== undefined) out.options[step.key][option.value] = option.label
    }
  }

  return out
}

/** The picture of a state (§10) — the same order the server's `previewKey()` walks. */
function previewKey(schema, state) {
  for (const rule of Array.isArray(schema.previews) ? schema.previews : []) {
    if (rule && typeof rule.image === 'string' && rule.when && typeof rule.when === 'object') {
      if (visible({ when: rule.when }, state.values, state.options)) return rule.image
    }
  }

  const main = mainStep(schema)
  const chosen = chosenOptions(schema, state)

  if (main !== null) {
    const found = chosen.find(([step]) => step === main)
    return found && typeof found[1].image === 'string' ? found[1].image : null
  }

  const types = Object.fromEntries(steps(schema).map((step) => [step.key, step.type]))
  const card = chosen.find(([step]) => types[step] === 'cards')

  return card && typeof card[1].image === 'string' ? card[1].image : null
}

function setup(root) {
  const source = root.querySelector('[data-webx-configurator-data]')
  if (!source) return

  let data
  try {
    data = JSON.parse(source.textContent || '{}')
  } catch {
    return
  }

  const { schema, initial, images = {}, words = {}, locale, currency } = data
  const names = labels(schema)
  const controller = new AbortController()
  const on = (target, type, handler) =>
    target.addEventListener(type, handler, { signal: controller.signal })

  let state = resolve(schema, {}, initial)

  // Keys are slugs — the check of the schema holds them to a-z, 0-9, `_` and `-`.
  const stepOf = (key) => root.querySelector(`[data-webx-configurator-step="${key}"]`)
  const fieldOf = (key) => root.querySelector(`[data-webx-configurator-field="${key}"]`)

  // The steppers are printed hidden: without this script they would be buttons that do nothing,
  // and the number input has arrows of its own.
  for (const button of root.querySelectorAll('[data-webx-configurator-by]')) button.hidden = false

  // The dialog's form gets the id the inbox counted for it; another form of the same slug earlier
  // on the page makes it `-2`, and the inputs follow it there.
  const slug = root.getAttribute('data-webx-configurator-form')
  const dialog = slug ? document.getElementById(`webx-form-${slug}`) : null
  const form = dialog ? dialog.querySelector('form') : null

  if (form && form.id) {
    for (const input of root.querySelectorAll('input[form]')) input.setAttribute('form', form.id)
  }

  function lines(quote) {
    const list = root.querySelector('[data-webx-configurator-lines]')
    if (!list) return

    const sample = list.querySelector('.webx-configurator__line')
    const discountLabel = schema.discount && schema.discount.label
    list.replaceChildren()

    for (const line of quote.lines) {
      const item = sample
        ? sample.cloneNode(true)
        : Object.assign(document.createElement('li'), { className: 'webx-configurator__line' })
      const text = item.querySelector('.webx-configurator__line-text') ?? item
      const amount = item.querySelector('.webx-configurator__line-amount')

      item.classList.toggle('is-discount', line.kind === 'discount')
      text.textContent = fill(words.line?.[line.kind], {
        step: names.steps[line.step] ?? line.step ?? '',
        option: names.options[line.step]?.[line.option] ?? line.option ?? '',
        rate: number(line.rate ?? 0, locale),
        area: number(line.area ?? 0, locale),
        label: discountLabel || words.discount || '',
      })
      if (amount) amount.textContent = money(line.amount, currency, locale)
      list.append(item)
    }
  }

  /** Everything from the state; `typing` is the size being typed into, left as the visitor has it. */
  function draw(typing = null) {
    for (const step of steps(schema)) {
      const box = stepOf(step.key)
      if (!box) continue

      if (step.type === 'dimensions') {
        let shown = 0

        for (const field of Array.isArray(step.fields) ? step.fields : []) {
          const holder = fieldOf(field.key)
          const limits = state.limits[field.key]
          if (!holder) continue

          holder.hidden = !limits
          const input = holder.querySelector('input')
          if (input) {
            input.disabled = !limits
            if (limits) {
              shown++
              input.min = String(limits[0])
              input.max = String(limits[1])
              if (input !== typing) input.value = String(state.values[field.key])
              const min = holder.querySelector('[data-webx-configurator-min]')
              const max = holder.querySelector('[data-webx-configurator-max]')
              if (min) min.textContent = String(limits[0])
              if (max) max.textContent = String(limits[1])
            }
          }
        }

        box.hidden = shown === 0
        continue
      }

      const offered = state.options[step.key]
      box.hidden = !offered

      for (const label of box.querySelectorAll('[data-webx-configurator-option]')) {
        const value = label.getAttribute('data-webx-configurator-option')
        const on_ = !!offered && offered.includes(value)
        const chosen = on_ && state.values[step.key] === value
        const radio = label.querySelector('input')

        label.hidden = !on_
        label.classList.toggle('is-selected', chosen)
        if (radio) {
          radio.disabled = !on_
          radio.checked = chosen
        }
      }
    }

    const quote = price(schema, state, today())
    const discount = quote.lines.find((line) => line.kind === 'discount')
    const total = root.querySelector('[data-webx-configurator-total]')
    const before = root.querySelector('[data-webx-configurator-before]')
    const badge = root.querySelector('[data-webx-configurator-discount]')
    const area = root.querySelector('[data-webx-configurator-area]')

    if (total) total.textContent = money(quote.total, currency, locale)

    // The struck-out price and the label only while the discount is in force — after `until` the
    // visitor's calendar takes them away, even on a page cached before that day.
    const struck = !!discount && quote.total_before_discount !== quote.total
    if (before) {
      before.hidden = !struck
      const value = before.querySelector('[data-webx-configurator-before-value]')
      if (value)
        value.textContent = struck ? money(quote.total_before_discount, currency, locale) : ''
    }
    if (badge) {
      badge.hidden = !discount
      if (discount)
        badge.textContent =
          (schema.discount && schema.discount.label) || `−${number(discount.rate, locale)}%`
    }
    if (area) {
      area.hidden = !(quote.area > 0)
      const value = area.querySelector('[data-webx-configurator-area-value]')
      if (value) value.textContent = number(quote.area, locale)
    }

    lines(quote)

    const figure = root.querySelector('[data-webx-configurator-preview]')
    const image = figure ? figure.querySelector('img') : null
    const picture = images[previewKey(schema, state)] ?? null

    if (figure && image) {
      figure.hidden = !picture
      if (picture && image.getAttribute('src') !== picture.src) {
        image.src = picture.src
        if (picture.srcset) image.srcset = picture.srcset
        else image.removeAttribute('srcset')
        image.alt = picture.alt ?? ''
        if (picture.width) {
          image.width = picture.width
          image.height = picture.height
        }
      }
    }

    return quote
  }

  on(root, 'change', (event) => {
    const input = event.target
    if (!(input instanceof HTMLInputElement) || !root.contains(input)) return

    const step = input
      .closest('[data-webx-configurator-step]')
      ?.getAttribute('data-webx-configurator-step')
    const field = input
      .closest('[data-webx-configurator-field]')
      ?.getAttribute('data-webx-configurator-field')

    if (input.type === 'radio' && step) {
      state = select(schema, state, step, input.value, initial)
    } else if (field) {
      const value = Number(input.value)
      // Emptied or not a number: the size stays where it was.
      if (input.value.trim() !== '' && Number.isFinite(value))
        state = set(schema, state, field, value, initial)
    }

    draw()
  })

  // Typing a size counts as soon as it is a size inside the limits; outside them it waits for the
  // field to be left (the browser marks it :invalid meanwhile) — pulling it in on every key would
  // fight the typing.
  on(root, 'input', (event) => {
    const input = event.target
    const field =
      input instanceof HTMLInputElement && input.type === 'number'
        ? input
            .closest('[data-webx-configurator-field]')
            ?.getAttribute('data-webx-configurator-field')
        : null
    if (!field) return

    const limits = state.limits[field]
    const value = Number(input.value)
    const inside =
      input.value.trim() !== '' &&
      Number.isFinite(value) &&
      limits &&
      value >= limits[0] &&
      value <= limits[1]

    if (inside) {
      state = set(schema, state, field, value, initial)
      draw(input)
    }
  })

  on(root, 'click', (event) => {
    const button =
      event.target instanceof Element ? event.target.closest('[data-webx-configurator-by]') : null
    if (!button || !root.contains(button)) return

    const holder = button.closest('[data-webx-configurator-field]')
    const field = holder?.getAttribute('data-webx-configurator-field')
    const input = holder?.querySelector('input')
    if (!field || !input) return

    const by = Number(button.getAttribute('data-webx-configurator-by')) || 0
    const stepBy = Number(input.step) || 1
    state = set(schema, state, field, Number(state.values[field] ?? 0) + by * stepBy, initial)
    draw()
  })

  // A button fixed in the corner of the screen — the quick contact of the widgets — would sit on
  // the end of the price stuck to the bottom: the room it takes is kept free there.
  const corner = document.querySelector('[data-webx-contact-button]')
  const reserve = () => {
    const box = corner ? corner.getBoundingClientRect() : null
    const room =
      box && box.width > 0 ? Math.max(0, document.documentElement.clientWidth - box.left) : 0
    root.style.setProperty('--webx-configurator-corner', `${Math.round(room)}px`)
  }
  if (corner) {
    reserve()
    on(window, 'resize', reserve)
  }

  // What was chosen and what it costs, over the fields of the dialog when this configurator opens
  // it — only to read; what is sent are the radios and the sizes themselves.
  const recap = root.querySelector('[data-webx-configurator-recap]')
  const opener = root.querySelector('[data-webx-form]')

  if (recap && opener && form) {
    on(opener, 'click', () => {
      const quote = price(schema, state, today())
      const piece = recap.content.firstElementChild.cloneNode(true)
      const list = piece.querySelector('.webx-configurator__recap-list')
      const sum = piece.querySelector('.webx-configurator__recap-total')

      for (const [step, option] of chosenOptions(schema, state)) {
        const row = document.createElement('li')
        row.textContent = `${names.steps[step]}: ${option.label ?? option.value}`
        list?.append(row)
      }

      for (const step of steps(schema)) {
        for (const field of step.type === 'dimensions' && Array.isArray(step.fields)
          ? step.fields
          : []) {
          if (!state.limits[field.key]) continue
          const row = document.createElement('li')
          row.textContent = `${field.label || field.key}: ${number(Number(state.values[field.key]), locale)}${field.unit ? ` ${field.unit}` : ''}`
          list?.append(row)
        }
      }

      if (sum) sum.textContent = money(quote.total, currency, locale)

      form.querySelector('.webx-configurator__recap')?.remove()
      form.prepend(piece)
    })
  }

  draw()

  return () => {
    controller.abort()
    root.style.removeProperty('--webx-configurator-corner')
    form?.querySelector('.webx-configurator__recap')?.remove()
  }
}

function start() {
  const webx = window.webx

  if (webx && typeof webx.widget === 'function') {
    webx.widget('configurator', SELECTOR, setup)
    return
  }

  for (const root of document.querySelectorAll(SELECTOR)) {
    if (root.dataset.webxConfiguratorMounted) continue
    root.dataset.webxConfiguratorMounted = '1'
    setup(root)
  }
}

if (window.webx && typeof window.webx.widget === 'function') {
  start()
} else {
  // The runtime comes after this script in the page: by the end of parsing it is there, or the
  // page has none. `load` covers a script that arrived after the document was parsed.
  let started = false
  const once = () => {
    if (started) return
    started = true
    start()
  }
  if (document.readyState === 'complete') once()
  else {
    document.addEventListener('DOMContentLoaded', once, { once: true })
    window.addEventListener('load', once, { once: true })
  }
}
