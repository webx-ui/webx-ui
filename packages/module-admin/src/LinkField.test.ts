import { afterEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, ref } from 'vue'
import { adminKey, type AdminContext } from './admin'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import LinkField from './LinkField.vue'
import LinkPicker from './LinkPicker.vue'
import { emptyLink, type LinkValue, type ResolvedLink } from './links'

// The panel is teleported into `<body>` and the picker in it debounces a search: neither may
// outlive its test.
enableAutoUnmount(afterEach)

const SOURCES = [
  { type: 'page', title: 'Pages', icon: 'file' },
  { type: 'article', title: 'Articles', icon: 'file-text' },
]

function field(
  value: LinkValue | null,
  props: Record<string, unknown> = {},
  answer: Partial<ResolvedLink> = {},
) {
  const i18n = createI18n({ locale: 'en' })
  i18n.defaults('webx-admin', adminMessages)

  const get = vi.fn(async (path: string) => {
    if (path.endsWith('/links/sources')) return { data: SOURCES }

    return { data: [] }
  })

  const post = vi.fn(async () => ({
    data: [
      {
        type: 'page',
        id: 4,
        title: 'Installation and setup',
        url: '/installation',
        available: true,
        hint: null,
        ...answer,
      },
    ],
  }))

  const admin = {
    apiPath: '/api/cms',
    http: { get, post },
    describeFile: async (path: string) =>
      path === 'media/ab/price-list.pdf'
        ? { path, url: '/storage/price-list.pdf', name: 'price-list.pdf', size: 62_464 }
        : null,
  } as unknown as AdminContext
  const model = ref<LinkValue | null>(value)

  const Host = defineComponent({
    setup: () => () =>
      h(LinkField, {
        ...props,
        modelValue: model.value,
        'onUpdate:modelValue': (next: LinkValue | null) => {
          model.value = next
        },
      }),
  })

  const wrapper = mount(Host, {
    attachTo: document.body,
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, model, get, post }
}

const row = (wrapper: ReturnType<typeof field>['wrapper']) => wrapper.get('button.wx-link-field')
const text = (wrapper: ReturnType<typeof field>['wrapper'], part: string) =>
  wrapper.find(`.wx-field-row__${part}`).exists()
    ? wrapper.get(`.wx-field-row__${part}`).text()
    : null
const panel = () => document.body.querySelector<HTMLElement>('.wx-link-field__panel')

describe('WxLinkField', () => {
  it('asks for a target when nothing is chosen yet', async () => {
    const { wrapper, post } = field(emptyLink())
    await flushPromises()

    expect(row(wrapper).classes()).toContain('is-empty')
    expect(text(wrapper, 'title')).toBe('Choose a page or enter an address')
    expect(text(wrapper, 'detail')).toBeNull()
    // Nothing to resolve: no pair, no question to the server.
    expect(post).not.toHaveBeenCalled()
  })

  it('names the chosen entity, its section and the address it has today, anchor included', async () => {
    const { wrapper, post } = field({
      ...emptyLink(),
      entity_type: 'page',
      entity_id: 4,
      hash: 'prices',
    })
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/links/resolve', {
      links: [{ type: 'page', id: 4 }],
      locale: 'en',
    })
    expect(row(wrapper).classes()).toContain('is-entity')
    expect(text(wrapper, 'title')).toBe('Installation and setup')
    expect(text(wrapper, 'detail')).toBe('Pages · /installation#prices')
  })

  it('draws an address of the panel’s own site as a path', async () => {
    const { wrapper } = field(
      { ...emptyLink(), entity_type: 'page', entity_id: 4 },
      {},
      { title: 'Home', url: window.location.origin + '/' },
    )
    await flushPromises()

    expect(text(wrapper, 'detail')).toBe('Pages · /')
  })

  it('shows an address by its host, and the whole of it below', async () => {
    const { wrapper } = field({
      ...emptyLink(),
      target: 'url',
      url: 'https://www.example.com/book?slot=3',
      hash: 'form',
    })
    await flushPromises()

    expect(row(wrapper).classes()).toContain('is-url')
    expect(text(wrapper, 'title')).toBe('example.com')
    expect(text(wrapper, 'detail')).toBe('https://www.example.com/book?slot=3#form')
  })

  it('shows a path of this site as it is', async () => {
    const { wrapper } = field({ ...emptyLink(), target: 'url', url: '/account' })
    await flushPromises()

    expect(text(wrapper, 'title')).toBe('/account')
    expect(text(wrapper, 'detail')).toBe('/account')
  })

  it('names a file by the name it was uploaded under, with its kind and size', async () => {
    const { wrapper } = field({
      ...emptyLink(),
      target: 'file',
      path: 'media/ab/price-list.pdf',
      download: true,
    })
    await flushPromises()

    expect(row(wrapper).classes()).toContain('is-file')
    expect(text(wrapper, 'title')).toBe('price-list.pdf')
    expect(text(wrapper, 'detail')).toBe('PDF · 61 kB')
  })

  it('says so when the library no longer has the file', async () => {
    const { wrapper } = field({ ...emptyLink(), target: 'file', path: 'media/zz/gone.pdf' })
    await flushPromises()

    expect(row(wrapper).classes()).toContain('is-accent-danger')
    expect(text(wrapper, 'title')).toBe('gone.pdf')
    expect(text(wrapper, 'detail')).toBe('This file is no longer in the library')
  })

  it('says plainly that a link goes nowhere, flags and all', async () => {
    const { wrapper } = field({ ...emptyLink(), target: 'none', new_tab: true, rel: ['ugc'] })
    await flushPromises()

    expect(row(wrapper).classes()).toContain('is-none')
    expect(text(wrapper, 'title')).toBe('No link')
    // The flags of a link that goes nowhere are leftovers, not anything the site will do.
    expect(wrapper.findAll('.wx-link-field__flag')).toHaveLength(0)
  })

  it('badges only the switches that are on, each with its meaning as a tooltip', async () => {
    const { wrapper } = field({
      ...emptyLink(),
      target: 'url',
      url: 'https://example.com',
      new_tab: true,
      rel: ['nofollow', 'sponsored'],
    })
    await flushPromises()

    const flags = wrapper.findAll('.wx-link-field__flag')

    expect(flags.map((flag) => flag.attributes('title'))).toEqual([
      'Open in a new tab',
      'Pass no link weight',
      'Paid placement',
    ])
    expect(flags[1]!.text()).toContain('nofollow')
    expect(wrapper.find('.wx-link-field__flag.is-ugc').exists()).toBe(false)
  })

  it('opens the picker in a dialog panel and hands back what it changes, unchanged', async () => {
    const { wrapper, model } = field({ ...emptyLink(), target: 'url', url: '/account' })
    await flushPromises()

    expect(panel()).toBeNull()
    expect(row(wrapper).attributes('aria-haspopup')).toBe('dialog')
    expect(row(wrapper).attributes('aria-expanded')).toBe('false')

    await row(wrapper).trigger('click')
    await flushPromises()

    expect(panel()).not.toBeNull()
    expect(panel()!.getAttribute('role')).toBe('dialog')
    expect(row(wrapper).attributes('aria-expanded')).toBe('true')
    expect(row(wrapper).classes()).toContain('is-open')

    const picker = wrapper.getComponent(LinkPicker)

    picker.vm.$emit('update:modelValue', { ...model.value!, new_tab: true })
    await flushPromises()

    // The value is the picker's, key for key: the field adds nothing to it and takes nothing out.
    expect(model.value).toEqual({ ...emptyLink(), target: 'url', url: '/account', new_tab: true })
    // And the row already says so while the panel is still open.
    expect(wrapper.find('.wx-link-field__flag.is-new-tab').exists()).toBe(true)
  })

  it('closes from “Done” and gives focus back to the row', async () => {
    const { wrapper } = field({ ...emptyLink(), target: 'url', url: '/account' })
    await flushPromises()

    await row(wrapper).trigger('click')
    await flushPromises()

    const done = [...panel()!.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Done',
    )

    done!.click()
    await flushPromises()
    await nextTick()

    expect(panel()).toBeNull()
    expect(document.activeElement).toBe(row(wrapper).element)
  })

  it('closes on Escape', async () => {
    const { wrapper } = field({ ...emptyLink(), target: 'url', url: '/account' })
    await flushPromises()

    await row(wrapper).trigger('click')
    await flushPromises()

    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
    )
    await flushPromises()

    expect(panel()).toBeNull()
    expect(row(wrapper).attributes('aria-expanded')).toBe('false')
  })

  it('does not open while disabled', async () => {
    const { wrapper } = field(
      { ...emptyLink(), target: 'url', url: '/account' },
      { disabled: true },
    )
    await flushPromises()

    await row(wrapper).trigger('click')
    await flushPromises()

    expect(panel()).toBeNull()
  })
})
