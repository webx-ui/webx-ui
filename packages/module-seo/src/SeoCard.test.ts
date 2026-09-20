import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SeoCard from './SeoCard.vue'
import type { SeoValue } from './types'

/**
 * What the card puts in the value.
 *
 * Layout is not tested here and cannot be — jsdom computes none of it, so anything about how
 * this looks is checked in a browser. What is worth pinning down is the arithmetic between the
 * controls and the one object the field stores: a checkbox that turns into a word in a
 * comma-separated line, and a code editor that turns into parsed JSON.
 */
function card(value: SeoValue | null = null) {
  return mount(SeoCard, { props: { modelValue: value, 'onUpdate:modelValue': () => {} } })
}

/** The last value the field emitted. */
function emitted(wrapper: ReturnType<typeof card>): SeoValue | null {
  const updates = wrapper.emitted('update:modelValue') as [SeoValue | null][] | undefined

  return updates ? updates[updates.length - 1]![0] : null
}

describe('WxSeo', () => {
  it('mounts with nothing in it', () => {
    expect(card().find('.wx-seo').exists()).toBe(true)
  })

  it('turns the indexing boxes into a line of directives', async () => {
    const wrapper = card()
    const group = wrapper.findComponent({ name: 'WxCheckboxGroup' })

    await group.setValue(['noindex', 'nofollow'])

    expect(emitted(wrapper)?.robots).toBe('noindex, nofollow')
  })

  it('keeps a directive it has no box for', async () => {
    // `max-snippet:20` has no checkbox and never will have one. Dropping what somebody typed
    // because the form has no control for it is worse than not offering the control.
    const wrapper = card({ robots: 'noindex, max-snippet:20' })
    const group = wrapper.findComponent({ name: 'WxCheckboxGroup' })

    expect(group.props('modelValue')).toEqual(['noindex'])

    await group.setValue(['nofollow'])

    expect(emitted(wrapper)?.robots).toBe('nofollow, max-snippet:20')
  })

  it('clears the line when the last box is unticked', async () => {
    const wrapper = card({ robots: 'noindex' })

    await wrapper.findComponent({ name: 'WxCheckboxGroup' }).setValue([])

    expect(emitted(wrapper)?.robots).toBeNull()
  })

  it('stores structured data parsed, and nothing at all when it is emptied', async () => {
    const wrapper = card()
    const editor = wrapper.findComponent({ name: 'WxCodeEditor' })

    await editor.setValue('{"@type":"FAQPage"}')
    expect(emitted(wrapper)?.json_ld).toEqual({ '@type': 'FAQPage' })

    await editor.setValue('   ')
    expect(emitted(wrapper)?.json_ld).toBeNull()
  })

  it('leaves the value alone while the JSON is half-typed', async () => {
    const wrapper = card({ json_ld: { '@type': 'FAQPage' } })

    await wrapper.findComponent({ name: 'WxCodeEditor' }).setValue('{"@type":')

    // Nothing new was emitted: what is in the box is not JSON yet, and overwriting a good
    // value with a broken one on every keystroke is how a block gets lost.
    expect(emitted(wrapper)).toBeNull()
  })

  it('writes an empty canonical address as nothing, not as an empty string', async () => {
    const wrapper = card({ canonical: 'https://example.test/a' })
    const inputs = wrapper.findAllComponents({ name: 'WxInput' })
    const canonical = inputs.find((one) => one.props('modelValue') === 'https://example.test/a')

    await canonical!.setValue('')

    expect(emitted(wrapper)?.canonical).toBeNull()
  })

  /*
   * What the share preview says, which is the one thing about it jsdom can check: how it looks
   * is measured in a browser, but which words end up in it is arithmetic between four fields.
   */
  describe('the share preview', () => {
    const share = (wrapper: ReturnType<typeof card>) => wrapper.find('.wx-seo__share')

    it('falls back on what the page says when the share fields are empty', () => {
      const wrapper = card({
        title: { en: 'A panel nobody has to explain' },
        description: { en: 'How we rewrote it.' },
      })

      expect(share(wrapper).find('.wx-seo__share-title').text()).toBe(
        'A panel nobody has to explain',
      )
      expect(share(wrapper).find('.wx-seo__share-text').text()).toBe('How we rewrote it.')
    })

    it('prefers the share fields when they are written', () => {
      const wrapper = card({
        title: { en: 'A panel nobody has to explain' },
        og_title: { en: 'We rewrote the panel' },
      })

      expect(share(wrapper).find('.wx-seo__share-title').text()).toBe('We rewrote the panel')
    })

    /* The address the server worked out beside the key — never the key itself, which is not a
       thing a browser can load. */
    it('draws the picture by the address that travelled with the value', () => {
      const wrapper = card({
        title: { en: 'Something' },
        og_image: { path: 'blog/cover.jpg', url: '/files/blog/cover.jpg' },
      })

      expect(share(wrapper).find('img').attributes('src')).toBe('/files/blog/cover.jpg')
    })

    /* A page whose picture is left to the site is not a page with no picture, and saying
       nothing there would read as "nothing will be shown". */
    it('says the site fills the picture in when none is chosen', () => {
      const wrapper = card({ title: { en: 'Something' } })

      expect(share(wrapper).find('img').exists()).toBe(false)
      // The key rather than the sentence: mounted outside a panel there is no dictionary, and
      // `useTranslate` hands back what it was asked for.
      expect(share(wrapper).text()).toContain('card.share-auto')
    })

    it('is not drawn at all for a record that says nothing about itself', () => {
      expect(share(card()).exists()).toBe(false)
    })
  })
})
