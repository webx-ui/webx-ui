import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxImage from './Image.vue'

describe('WxImage', () => {
  it('holds the place before the picture arrives', () => {
    const wrapper = mount(WxImage, { props: { src: '/a.png', width: 200, height: 120 } })

    expect(wrapper.find('.wx-image__placeholder').exists()).toBe(true)
    expect(wrapper.attributes('style')).toContain('width: 200px')
  })

  it('shows the picture once it has loaded', async () => {
    const wrapper = mount(WxImage, { props: { src: '/a.png' } })

    await wrapper.get('img').trigger('load')

    expect(wrapper.classes()).toContain('is-loaded')
    expect(wrapper.find('.wx-image__placeholder').exists()).toBe(false)
    expect(wrapper.emitted('load')).toHaveLength(1)
  })

  it('gives up on a picture that will not load', async () => {
    const wrapper = mount(WxImage, { props: { src: '/missing.png' } })

    await wrapper.get('img').trigger('error')

    expect(wrapper.find('.wx-image__failed').exists()).toBe(true)
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.emitted('error')).toHaveLength(1)
  })

  it('waits for the screen unless told otherwise', () => {
    expect(
      mount(WxImage, { props: { src: '/a.png' } })
        .get('img')
        .attributes('loading'),
    ).toBe('lazy')
    expect(
      mount(WxImage, { props: { src: '/a.png', lazy: false } })
        .get('img')
        .attributes('loading'),
    ).toBe('eager')
  })

  it('starts over when the picture changes', async () => {
    const wrapper = mount(WxImage, { props: { src: '/missing.png' } })
    await wrapper.get('img').trigger('error')
    expect(wrapper.find('.wx-image__failed').exists()).toBe(true)

    await wrapper.setProps({ src: '/other.png' })

    // The last picture's failure says nothing about this one.
    expect(wrapper.find('.wx-image__failed').exists()).toBe(false)
    expect(wrapper.find('img').exists()).toBe(true)
  })

  it('offers no preview unless asked', async () => {
    const wrapper = mount(WxImage, { props: { src: '/a.png' } })
    await wrapper.get('img').trigger('load')

    expect(wrapper.find('.wx-image__preview').exists()).toBe(false)
  })
})
