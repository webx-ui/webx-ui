import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxBadge from './Badge.vue'

describe('WxBadge', () => {
  it('renders its label', () => {
    const wrapper = mount(WxBadge, { slots: { default: 'Draft' } })

    expect(wrapper.get('.wx-badge__label').text()).toBe('Draft')
    expect(wrapper.classes()).toContain('wx-badge--default')
    expect(wrapper.classes()).toContain('wx-badge--soft')
  })

  it('applies type, variant, size and shape', () => {
    const wrapper = mount(WxBadge, {
      props: { type: 'success', variant: 'solid', size: 'lg', round: true },
    })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining([
        'wx-badge--success',
        'wx-badge--solid',
        'wx-badge--lg',
        'wx-badge--round',
      ]),
    )
  })

  it('shows a dot only when asked', () => {
    expect(mount(WxBadge).find('.wx-badge__dot').exists()).toBe(false)
    expect(
      mount(WxBadge, { props: { dot: true } })
        .find('.wx-badge__dot')
        .exists(),
    ).toBe(true)
  })

  it('emits close from the close button', async () => {
    const wrapper = mount(WxBadge, {
      props: { closable: true, closeLabel: 'Remove tag' },
      slots: { default: 'News' },
    })

    const close = wrapper.get('.wx-badge__close')
    expect(close.attributes('aria-label')).toBe('Remove tag')

    await close.trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('has no close button unless closable', () => {
    expect(mount(WxBadge).find('.wx-badge__close').exists()).toBe(false)
  })

  it('renders the icon slot', () => {
    const wrapper = mount(WxBadge, { slots: { icon: '<i class="marker" />' } })

    expect(wrapper.find('.wx-badge__icon .marker').exists()).toBe(true)
  })
})
