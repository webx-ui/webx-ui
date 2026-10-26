import { afterEach, describe, expect, it, vi } from 'vitest'

/*
 * The widget over the markup the server prints (§12.2, decision 9): it only switches what is
 * there — the hidden steps and sizes, the selection, the price and its lines, the struck-out price
 * of a discount, the picture — and puts the recap over the request form when its opener is pressed.
 */

const schema = {
  version: 1,
  currency: 'UAH',
  pricing: { round: 100 },
  initial: { construction: 'corner' },
  discount: { percent: 10, until: '2999-12-31', label: 'Autumn −10%' },
  steps: [
    {
      key: 'construction',
      type: 'cards',
      label: 'Construction',
      options: [
        {
          value: 'corner',
          label: 'Corner',
          image: 'media/corner.svg',
          area: '(w+d)*h/1e6',
          price: { fixed: 8000, perM2: 4420 },
          defaults: { h: 2000, w: 900, d: 900 },
        },
        {
          value: 'niche',
          label: 'Niche',
          image: 'media/niche.svg',
          area: 'w*h/1e6',
          price: { fixed: 5000, perM2: 4000 },
          defaults: { h: 2000, w: 900 },
        },
      ],
    },
    {
      key: 'size',
      type: 'dimensions',
      title: 'Sizes',
      fields: [
        { key: 'h', label: 'Height', unit: 'mm', min: 1300, max: 2400, step: 50 },
        { key: 'w', label: 'Width', unit: 'mm', min: 600, max: 1600, step: 50 },
        {
          key: 'd',
          label: 'Depth',
          unit: 'mm',
          min: 600,
          max: 1600,
          step: 50,
          when: { construction: ['corner'] },
        },
      ],
    },
  ],
}

const data = {
  key: 'shower',
  locale: 'en',
  currency: 'UAH',
  initial: { construction: 'corner' },
  schema,
  images: {
    'media/corner.svg': { src: '/corner.svg', srcset: null, alt: '' },
    'media/niche.svg': { src: '/niche.svg', srcset: null, alt: '' },
  },
  words: {
    line: {
      fixed: ':step: :option',
      perM2: ':step: :option — :rate × :area m²',
      discount: ':label, −:rate%',
    },
    discount: 'Discount',
  },
}

const option = (step, value, checked) => `
  <label class="webx-configurator__option${checked ? ' is-selected' : ''}" data-webx-configurator-option="${value}">
    <input class="webx-configurator__radio" type="radio" name="configurator[values][${step}]" value="${value}" form="webx-inbox-request"${checked ? ' checked' : ''}>
  </label>`

const field = (key, value) => `
  <div class="webx-configurator__field" data-webx-configurator-field="${key}">
    <button type="button" data-webx-configurator-by="-1" hidden>−</button>
    <input type="number" name="configurator[values][${key}]" value="${value}" step="50" form="webx-inbox-request">
    <button type="button" data-webx-configurator-by="1" hidden>+</button>
    <span data-webx-configurator-min></span><span data-webx-configurator-max></span>
  </div>`

const page = `
  <section class="webx-configurator" data-webx-configurator="shower" data-webx-configurator-form="request">
    <figure data-webx-configurator-preview><img src="/corner.svg" alt=""></figure>
    <fieldset data-webx-configurator-step="construction">${option('construction', 'corner', true)}${option('construction', 'niche', false)}</fieldset>
    <fieldset data-webx-configurator-step="size">${field('h', 2000)}${field('w', 900)}${field('d', 900)}</fieldset>
    <span data-webx-configurator-discount hidden></span>
    <s data-webx-configurator-before hidden><span data-webx-configurator-before-value></span></s>
    <span data-webx-configurator-total>₴23,900</span>
    <ul data-webx-configurator-lines><li class="webx-configurator__line"><span class="webx-configurator__line-text"></span> <span class="webx-configurator__line-amount"></span></li></ul>
    <span data-webx-configurator-area><span data-webx-configurator-area-value></span></span>
    <section data-webx-configurator-live="construction:niche" hidden><a href="/niche-1">Niche product</a></section>
    <a class="webx-configurator__submit" href="#webx-form-request" data-webx-form="request">Get a quote</a>
    <input type="hidden" name="webx_enclosure[]" value="configurator" form="webx-inbox-request">
    <template data-webx-configurator-recap><div class="webx-configurator__recap"><p></p><ul class="webx-configurator__recap-list"></ul><p class="webx-configurator__recap-total"></p></div></template>
    <script type="application/json" data-webx-configurator-data>${JSON.stringify(data)}</script>
  </section>
  <dialog id="webx-form-request"><form id="webx-inbox-request-2"></form></dialog>`

afterEach(() => {
  document.body.innerHTML = ''
  delete window.webx
  vi.resetModules()
})

async function start() {
  document.body.innerHTML = page
  delete window.webx
  vi.resetModules()
  await import('./configurator.js')
  return document.querySelector('[data-webx-configurator]')
}

const text = (root, selector) =>
  root.querySelector(selector).textContent.replace(/\s+/g, ' ').trim()
const choose = (root, value) => {
  const radio = root.querySelector(`input[value="${value}"]`)
  radio.checked = true
  radio.dispatchEvent(new Event('change', { bubbles: true }))
}

describe('the configurator widget', () => {
  it('prices the start with the discount in force, line by line', async () => {
    const root = await start()

    // 23 912 − 2 391.20 = 21 520.80 → 21 500; before it 23 900, struck out beside the label.
    expect(text(root, '[data-webx-configurator-total]')).toBe('₴21,500')
    expect(root.querySelector('[data-webx-configurator-before]').hidden).toBe(false)
    expect(text(root, '[data-webx-configurator-before-value]')).toBe('₴23,900')
    expect(text(root, '[data-webx-configurator-discount]')).toBe('Autumn −10%')
    expect(
      [...root.querySelectorAll('.webx-configurator__line-text')].map((one) => one.textContent),
    ).toEqual([
      'Construction: Corner',
      'Construction: Corner — 4,420 × 3.6 m²',
      'Autumn −10%, −10%',
    ])
    expect(
      root.querySelector('.webx-configurator__line:last-child').classList.contains('is-discount'),
    ).toBe(true)

    // The steppers were printed hidden for a page without the script.
    expect(
      [...root.querySelectorAll('[data-webx-configurator-by]')].every((one) => !one.hidden),
    ).toBe(true)
  })

  it('hides what the choice hides, so it is not sent, and swaps the picture', async () => {
    const root = await start()

    choose(root, 'niche')

    const depth = root.querySelector('[data-webx-configurator-field="d"]')
    expect(depth.hidden).toBe(true)
    expect(depth.querySelector('input').disabled).toBe(true)
    expect(
      root
        .querySelector('[data-webx-configurator-option="niche"]')
        .classList.contains('is-selected'),
    ).toBe(true)
    expect(
      root
        .querySelector('[data-webx-configurator-option="corner"]')
        .classList.contains('is-selected'),
    ).toBe(false)
    expect(root.querySelector('[data-webx-configurator-preview] img').getAttribute('src')).toBe(
      '/niche.svg',
    )

    // 5000 + 4000 × 1.8 = 12 200; −10% = 10 980 → 11 000.
    expect(text(root, '[data-webx-configurator-total]')).toBe('₴11,000')
  })

  it('shows a catalogue product in real life while its option is chosen', async () => {
    const root = await start()
    const live = root.querySelector('[data-webx-configurator-live="construction:niche"]')

    expect(live.hidden).toBe(true)
    choose(root, 'niche')
    expect(live.hidden).toBe(false)
    choose(root, 'corner')
    expect(live.hidden).toBe(true)
  })

  it('pulls a size into its limits when it is left, and steps it by its step', async () => {
    const root = await start()
    const height = root.querySelector('[data-webx-configurator-field="h"] input')

    height.value = '9000'
    height.dispatchEvent(new Event('input', { bubbles: true }))
    expect(text(root, '[data-webx-configurator-total]')).toBe('₴21,500')

    height.dispatchEvent(new Event('change', { bubbles: true }))
    expect(height.value).toBe('2400')

    root
      .querySelector('[data-webx-configurator-field="h"] [data-webx-configurator-by="-1"]')
      .click()
    expect(height.value).toBe('2350')
  })

  it('follows the form of the dialog and puts the recap over its fields', async () => {
    const root = await start()

    // The inbox counted this form as the second of its slug on the page.
    expect(root.querySelector('input[name="webx_enclosure[]"]').getAttribute('form')).toBe(
      'webx-inbox-request-2',
    )

    root
      .querySelector('[data-webx-form]')
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))

    const recap = document.querySelector('#webx-inbox-request-2 .webx-configurator__recap')
    expect(recap).not.toBeNull()
    expect([...recap.querySelectorAll('li')].map((one) => one.textContent)).toEqual([
      'Construction: Corner',
      'Height: 2,000 mm',
      'Width: 900 mm',
      'Depth: 900 mm',
    ])
    expect(text(recap, '.webx-configurator__recap-total')).toBe('₴21,500')
  })
})
