import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { WxScreenRenderer, type ScreenNode } from '@webx-ui/schema'
import { adminKey, type AdminContext } from './admin'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import { adminTypes } from './screenTypes'
import { takesShortcodes } from './shortcodes'

const answer = {
  data: [
    {
      name: 'phone',
      description: 'The main line',
      html: '<a href="tel:+80012345678">+800 1234 5678</a>',
      plain: '+800 1234 5678',
      origin: 'settings',
    },
  ],
}

/** The core fields stand in for themselves: what is under test is what they are handed. */
function stub(name: string) {
  return defineComponent({
    name,
    inheritAttrs: false,
    props: {
      tokens: { type: Array, default: undefined },
      tokensTitle: { type: String, default: undefined },
      tokensLabel: { type: String, default: undefined },
    },
    setup(_props, { attrs }) {
      return () => h('div', { class: name, 'data-attrs': Object.keys(attrs).join(',') })
    },
  })
}

const Input = stub('WxInput')
const Textarea = stub('WxTextarea')
const Editor = stub('WxRichText')

function screen(nodes: ScreenNode[], get = vi.fn(() => Promise.resolve(answer))) {
  const i18n = createI18n({ locale: 'en' })
  i18n.defaults('webx-admin', adminMessages)
  const admin = { apiPath: '/api/cms', http: { get }, pickImage: null } as unknown as AdminContext

  const wrapper = mount(WxScreenRenderer, {
    props: { root: nodes, modelValue: {}, types: adminTypes },
    global: {
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      stubs: { WxInput: Input, WxTextarea: Textarea, WxRichText: Editor },
    },
  })

  return { wrapper, get }
}

describe('shortcodes in a screen’s text fields', () => {
  it('reads the flag the way a screen writes it', () => {
    expect(takesShortcodes('wx-rich-text', undefined)).toBe(true)
    expect(takesShortcodes('wx-rich-text', false)).toBe(false)
    expect(takesShortcodes('wx-textarea', undefined)).toBe(false)
    expect(takesShortcodes('wx-textarea', true)).toBe(true)
    expect(takesShortcodes('wx-input', 'yes')).toBe(false)
  })

  it('offers them in a paragraph the node marks, under the panel’s words, and not elsewhere', async () => {
    const { wrapper, get } = screen([
      { id: 'title', type: 'wx-input', name: 'title' },
      { id: 'summary', type: 'wx-textarea', name: 'summary', props: { shortcodes: true } },
      { id: 'note', type: 'wx-textarea', name: 'note' },
    ])
    await flushPromises()

    const [summary, note] = wrapper.findAllComponents(Textarea)

    expect(summary!.props('tokens')).toEqual([
      { name: 'phone', value: '+800 1234 5678', description: 'The main line' },
    ])
    expect(summary!.props('tokensTitle')).toBe('Shortcodes')
    expect(summary!.props('tokensLabel')).toBe('Insert a shortcode')
    // The flag is the node's: it does not land on the core field as an attribute.
    expect(summary!.get('.WxTextarea').attributes('data-attrs')).not.toContain('shortcodes')

    expect(note!.props('tokens')).toBeUndefined()
    expect(wrapper.getComponent(Input).props('tokens')).toBeUndefined()
    // One request for the whole form.
    expect(get).toHaveBeenCalledTimes(1)
  })

  it('asks for nothing when no field takes them', async () => {
    const { get } = screen([{ id: 'title', type: 'wx-input', name: 'title' }])
    await flushPromises()

    expect(get).not.toHaveBeenCalled()
  })

  it('offers them in a rich text unless the node says no', async () => {
    const { wrapper } = screen([
      { id: 'description', type: 'wx-rich-text', name: 'description' },
      { id: 'raw', type: 'wx-rich-text', name: 'raw', props: { shortcodes: false } },
    ])
    await flushPromises()

    const [description, raw] = wrapper.findAllComponents(Editor)

    expect(description!.props('tokens')).toHaveLength(1)
    expect(description!.props('tokensTitle')).toBe('Shortcodes')
    expect(raw!.props('tokens')).toBeUndefined()
  })

  it('leaves a field that brought its own tokens with them', async () => {
    const own = [{ name: 'first_name', value: 'Ann' }]
    const { wrapper } = screen([
      { id: 'text', type: 'wx-textarea', name: 'text', props: { shortcodes: true, tokens: own } },
    ])
    await flushPromises()

    expect(wrapper.getComponent(Textarea).props('tokens')).toEqual(own)
  })
})
