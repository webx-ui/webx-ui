import { afterEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { WxAutocomplete, WxCheckbox, WxInput, WxSegmented, WxSelect } from '@webx-ui/core'
import { adminKey, type AdminContext } from './admin'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import LinkPicker from './LinkPicker.vue'
import { emptyLink, type LinkCandidate, type LinkValue } from './links'

/*
 * Every picker is unmounted after its test. `WxAutocomplete` debounces its `search` by 300 ms, and
 * a saved link arms that timer the moment its title is written into the field: a picker left
 * mounted runs a real search a third of a second later — by then usually in the next test, and
 * after the last one, against an environment that is already gone.
 */
enableAutoUnmount(afterEach)

const SOURCES = [
  { type: 'page', title: 'Pages', icon: 'file' },
  { type: 'article', title: 'Articles', icon: 'file-text' },
]

const PAGES: LinkCandidate[] = [
  { id: 1, title: 'About us', url: '/about', available: true, hint: 'Home' },
  { id: 2, title: 'Pricing', url: null, available: false, hint: null },
]

/**
 * The picker under a host that actually holds the model.
 *
 * `mount(props)` would not: the component emits `update:modelValue` and nothing sends it back, so
 * every change would be computed from the value it started with — and two changes in a row, which
 * is exactly what ticking two boxes is, would lose the first one.
 */
function picker(value: LinkValue | null = emptyLink(), props: Record<string, unknown> = {}) {
  const i18n = createI18n({ locale: 'en' })
  i18n.defaults('webx-admin', adminMessages)

  const get = vi.fn(async (path: string) => {
    if (path.endsWith('/links/sources')) return { data: SOURCES }
    if (path.includes('/links/search')) return { data: PAGES }

    return { data: [{ name: 'account', path: '/account' }] }
  })

  const post = vi.fn(async () => ({
    data: [
      { type: 'page', id: 1, title: 'About us', url: '/about', available: true, hint: 'Home' },
    ],
  }))

  const admin = { apiPath: '/api/cms', http: { get, post } } as unknown as AdminContext

  const model = ref<LinkValue | null>(value)

  const Host = defineComponent({
    setup: () => () =>
      h(LinkPicker, {
        ...props,
        modelValue: model.value,
        'onUpdate:modelValue': (next: LinkValue | null) => {
          model.value = next
        },
      }),
  })

  const wrapper = mount(Host, {
    global: { provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n } },
  })

  return { wrapper, model, get, post }
}

describe('WxLinkPicker', () => {
  it('offers the three kinds of target and keeps what was chosen when the kind changes', async () => {
    const { wrapper, model } = picker({ ...emptyLink(), entity_type: 'page', entity_id: 1 })
    await flushPromises()

    const segmented = wrapper.getComponent(WxSegmented)

    expect((segmented.props('options') as { value: string }[]).map((one) => one.value)).toEqual([
      'entity',
      'url',
      'none',
    ])

    segmented.vm.$emit('update:modelValue', 'url')
    await flushPromises()

    // Switching to an address and back has to find the page still chosen: only the kind changed.
    expect(model.value?.target).toBe('url')
    expect(model.value?.entity_id).toBe(1)
  })

  it('leaves “nowhere” out where a link has to go somewhere', async () => {
    const { wrapper } = picker(emptyLink(), { allowNone: false })
    await flushPromises()

    const options = wrapper.getComponent(WxSegmented).props('options') as { value: string }[]

    expect(options.map((one) => one.value)).toEqual(['entity', 'url'])
  })

  it('draws a section for every source the server offered', async () => {
    const { wrapper } = picker()
    await flushPromises()

    const options = wrapper.getComponent(WxSelect).props('options') as { value: string }[]

    expect(options.map((one) => one.value)).toEqual(['page', 'article'])
  })

  it('searches the chosen section and stores the morph pair, never the address', async () => {
    const { wrapper, model, get } = picker()
    await flushPromises()

    const search = wrapper.getComponent(WxAutocomplete)
    search.vm.$emit('search', 'abo')
    await flushPromises()

    expect(get).toHaveBeenCalledWith(
      '/api/cms/links/search',
      expect.objectContaining({ query: expect.objectContaining({ type: 'page', q: 'abo' }) }),
    )

    search.vm.$emit('select', { value: 'About us', id: 1 })
    await flushPromises()

    expect(model.value?.entity_type).toBe('page')
    expect(model.value?.entity_id).toBe(1)
    expect(model.value?.url).toBeNull()
  })

  /** A draft belongs in the list, marked: a menu is built before the pages in it are published. */
  it('says which candidates the site would not show', async () => {
    const { wrapper } = picker()
    await flushPromises()

    wrapper.getComponent(WxAutocomplete).vm.$emit('search', '')
    await flushPromises()

    const options = wrapper.getComponent(WxAutocomplete).props('options') as {
      description?: string
    }[]

    expect(options[0]?.description).toBe('Home · /about')
    expect(options[1]?.description).toBe('Not on the site yet')
  })

  /** A saved value holds a pair and nothing to draw; the title comes from the server. */
  it('asks what a saved link points at', async () => {
    const { wrapper, post } = picker({ ...emptyLink(), entity_type: 'page', entity_id: 1 })
    await flushPromises()

    expect(post).toHaveBeenCalledWith('/api/cms/links/resolve', {
      links: [{ type: 'page', id: 1 }],
      locale: 'en',
    })

    expect(wrapper.getComponent(WxAutocomplete).props('modelValue')).toBe('About us')
  })

  /** Writing the saved title into the field looks like typing it; searching for it is waste. */
  it('does not search for the name it just filled in itself', async () => {
    const { wrapper, get } = picker({ ...emptyLink(), entity_type: 'page', entity_id: 1 })
    await flushPromises()

    wrapper.getComponent(WxAutocomplete).vm.$emit('search', 'About us')
    await flushPromises()

    expect(get.mock.calls.filter(([path]) => path.includes('/links/search'))).toHaveLength(0)

    // And the next thing typed is searched for, including that same text again.
    wrapper.getComponent(WxAutocomplete).vm.$emit('search', 'About us')
    await flushPromises()

    expect(get.mock.calls.filter(([path]) => path.includes('/links/search'))).toHaveLength(1)
  })

  it('keeps a typed address as it was typed', async () => {
    const { wrapper, model } = picker({ ...emptyLink(), target: 'url' })
    await flushPromises()

    wrapper.getComponent(WxAutocomplete).vm.$emit('update:modelValue', '/account')
    await flushPromises()

    expect(model.value?.url).toBe('/account')
  })

  it('collects the new tab and the rel an editor ticked', async () => {
    const { wrapper, model } = picker({ ...emptyLink(), target: 'url', url: '/account' })
    await flushPromises()

    const boxes = wrapper.findAllComponents(WxCheckbox)

    boxes[0]!.vm.$emit('update:modelValue', true)
    await flushPromises()
    boxes[1]!.vm.$emit('update:modelValue', true)
    await flushPromises()

    expect(model.value?.new_tab).toBe(true)
    expect(model.value?.rel).toEqual(['nofollow'])
  })

  /** Nothing to ask about a link that goes nowhere. */
  it('hides the attributes when the target is nowhere', async () => {
    const { wrapper } = picker({ ...emptyLink(), target: 'none' })
    await flushPromises()

    expect(wrapper.findAllComponents(WxCheckbox)).toHaveLength(0)
  })

  /** A chosen page has nowhere to write an anchor, so the field is there for both targets. */
  it('takes an anchor whichever kind of target it is', async () => {
    for (const target of ['entity', 'url'] as const) {
      const { wrapper, model } = picker({ ...emptyLink(), target })
      await flushPromises()

      const anchor = wrapper.get('.wx-link-picker__hash').getComponent(WxInput)

      // The `#` is drawn beside the field, so what goes into the value is the name alone.
      anchor.vm.$emit('update:modelValue', '#team')
      await flushPromises()

      expect(model.value?.hash).toBe('team')
    }
  })

  it('has nowhere to put an anchor on a link that goes nowhere', async () => {
    const { wrapper } = picker({ ...emptyLink(), target: 'none' })
    await flushPromises()

    expect(wrapper.find('.wx-link-picker__hash').exists()).toBe(false)
  })
})
