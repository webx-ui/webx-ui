import { afterEach, describe, expect, it, vi } from 'vitest'
import { fold, fromAnswer } from './select.js'

afterEach(() => {
  window.webx?.unmount?.()
  document.body.innerHTML = ''
  vi.useRealTimers()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

const words = JSON.stringify({
  nothing: 'Nothing found',
  more: 'Show more',
  count: 'Options: :count',
  failed: 'Failed',
  loading: 'Loading',
}).replace(/"/g, '&quot;')

// What `<x-webx-select>` prints.
const strictBox = ({ source = '/api/points', value = '' } = {}) => `
  <form>
    <div class="webx-select webx-select--searchable" data-webx-select="${source}" data-param="q" data-min="1" data-delay="0" data-strict="true" data-words="${words}">
      <label class="webx-select__label" for="select-1">Branch</label>
      <select class="webx-select__native" id="select-1" name="point">
        <option value="">Choose a branch</option>
        <option value="1" data-hint="1 Example street" ${value === '1' ? 'selected' : ''}>Branch 1</option>
        <option value="2">Branch 2</option>
        <option value="3">Locker 3</option>
      </select>
      <p class="webx-select__status" role="status"></p>
    </div>
  </form>`

const freeBox = () => `
  <div class="webx-select webx-select--free" data-webx-select="/api/streets" data-param="q" data-min="1" data-delay="0" data-strict="false" data-words="${words}">
    <label class="webx-select__label" for="select-2">Street</label>
    <input class="webx-select__native" type="text" id="select-2" name="street" value="">
    <p class="webx-select__status" role="status"></p>
  </div>`

async function start(html, respond) {
  vi.stubGlobal(
    'fetch',
    respond ??
      vi.fn(async (url) => {
        const q = new URL(url).searchParams.get('q')
        const page = Number(new URL(url).searchParams.get('page') || 1)
        return {
          ok: true,
          status: 200,
          json: async () => ({
            options: [{ value: `${page}0`, label: `${q} ${page}`, hint: 'far away' }],
            more: page === 1,
          }),
        }
      }),
  )
  document.body.innerHTML = html
  delete window.webx
  vi.resetModules()
  await import('./runtime.js')
  await import('./select.js')
  const root = document.querySelector('[data-webx-select]')
  return {
    root,
    input: root.querySelector('.webx-select__input'),
    native: root.querySelector('.webx-select__native'),
    list: root.querySelector('.webx-select__list'),
  }
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 5))
const type = (input, text) => {
  input.value = text
  input.dispatchEvent(new Event('input', { bubbles: true }))
}
const key = (input, name) =>
  input.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }))

describe('select', () => {
  it('stands a combobox over the select, which keeps the value', async () => {
    const { input, native, list, root } = await start(strictBox({ value: '1' }))

    expect(native.hidden).toBe(true)
    expect(native.id).toBe('')
    expect(input.id).toBe('select-1')
    expect(root.querySelector('label').htmlFor).toBe('select-1')
    expect(input.getAttribute('role')).toBe('combobox')
    expect(input.getAttribute('aria-controls')).toBe(list.id)
    expect(input.value).toBe('Branch 1')
    expect(input.placeholder).toBe('Choose a branch')
  })

  it('filters the loaded options at once and asks the server for the rest', async () => {
    const { input, list, root } = await start(strictBox())

    type(input, 'lock')
    const local = Array.from(list.querySelectorAll('[role="option"]')).map((o) => o.textContent)
    expect(local).toEqual(['Locker 3'])
    expect(root.classList.contains('is-open')).toBe(true)

    await settle()
    expect(fetch).toHaveBeenCalledTimes(1)
    expect(String(fetch.mock.calls[0][0])).toContain('/api/points?q=lock')
    const options = Array.from(list.querySelectorAll('.webx-select__option')).map(
      (o) => o.textContent,
    )
    expect(options).toEqual(['lock 1far away'])
    expect(list.querySelector('.webx-select__more')?.textContent).toBe('Show more')
    expect(root.querySelector('.webx-select__status').textContent).toBe('Options: 1')
  })

  it('chooses by keys, sets the select and fires its change', async () => {
    const { input, native } = await start(strictBox({ source: '' }))
    const changed = vi.fn()
    native.addEventListener('change', changed)

    key(input, 'ArrowDown')
    key(input, 'ArrowDown')
    expect(input.getAttribute('aria-activedescendant')).toMatch(/-1$/)
    key(input, 'Enter')

    expect(native.value).toBe('2')
    expect(input.value).toBe('Branch 2')
    expect(changed).toHaveBeenCalledTimes(1)
    expect(input.getAttribute('aria-expanded')).toBe('false')
  })

  it('keeps an option from the server in the select once chosen, and pages on «Show more»', async () => {
    const { input, native, list } = await start(strictBox())

    type(input, 'far')
    await settle()
    list.querySelector('.webx-select__more').click()
    await settle()
    expect(fetch.mock.calls[1][0].toString()).toContain('page=2')
    const options = Array.from(list.querySelectorAll('.webx-select__option')).map(
      (o) => o.firstChild.textContent,
    )
    expect(options).toEqual(['far 1', 'far 2'])

    list.querySelectorAll('.webx-select__option')[1].click()
    expect(native.value).toBe('20')
    expect(native.querySelector('option[value="20"]').getAttribute('data-hint')).toBe('far away')
  })

  it('strict: leaving the field puts the chosen label back; Escape closes', async () => {
    const { input } = await start(strictBox({ value: '1', source: '' }))

    type(input, 'nothing like it')
    expect(input.closest('.webx-select').querySelector('.webx-select__empty').textContent).toBe(
      'Nothing found',
    )
    key(input, 'Escape')
    expect(input.value).toBe('Branch 1')

    type(input, 'xyz')
    input.dispatchEvent(new Event('blur'))
    expect(input.value).toBe('Branch 1')
  })

  it('not strict: the text field is the combobox and an option only fills it', async () => {
    const { native, root } = await start(freeBox())
    const input = native

    expect(input.getAttribute('role')).toBe('combobox')
    type(input, 'Exa')
    await settle()
    root.querySelector('.webx-select__option').click()
    expect(input.value).toBe('Exa 1')

    type(input, 'Anything typed')
    input.dispatchEvent(new Event('blur'))
    expect(input.value).toBe('Anything typed')
  })

  it('says when the server failed, and lets go on unmount', async () => {
    const { input, root, native } = await start(
      strictBox(),
      vi.fn(async () => ({ ok: false, status: 500, json: async () => ({}) })),
    )

    type(input, 'br')
    await settle()
    expect(root.querySelector('.webx-select__status').textContent).toBe('Failed')

    window.webx.unmount(document)
    expect(root.querySelector('.webx-select__input')).toBeNull()
    expect(native.hidden).toBe(false)
    expect(native.id).toBe('select-1')
  })

  it('folds accents and apostrophes as the server does, and takes only safe answers', () => {
    expect(fold(' Ó’Town ')).toBe("o'town")
    expect(
      fromAnswer({ options: [{ value: 1, label: 'A' }, { label: 'no value' }, null], more: 1 }),
    ).toEqual({
      options: [{ value: '1', label: 'A', hint: null, disabled: false }],
      more: true,
    })
    expect(fromAnswer([{ value: 'x', label: 'X', hint: 'h' }]).options[0].hint).toBe('h')
  })
})
