import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxSpace from './Space.vue'

describe('WxSpace', () => {
  it('is a wrapping row by default', () => {
    const wrapper = mount(WxSpace, { slots: { default: '<button>One</button>' } })

    expect(wrapper.classes()).toContain('wx-space--horizontal')
    expect(wrapper.classes()).toContain('wx-space--wrap')
    expect(wrapper.attributes('style')).toContain('--wx-space-gap: var(--wx-space-12)')
  })

  it('never marks a column as wrapping', () => {
    const wrapper = mount(WxSpace, { props: { direction: 'vertical' } })

    expect(wrapper.classes()).toContain('wx-space--vertical')
    expect(wrapper.classes()).not.toContain('wx-space--wrap')
  })

  it('takes a keyword, a number or a length as the gap', () => {
    expect(mount(WxSpace, { props: { size: 'xs' } }).attributes('style')).toContain(
      'var(--wx-space-4)',
    )
    expect(mount(WxSpace, { props: { size: 20 } }).attributes('style')).toContain('20px')
    expect(mount(WxSpace, { props: { size: '2rem' } }).attributes('style')).toContain('2rem')
  })

  it('applies alignment and distribution only when asked', () => {
    const plain = mount(WxSpace)
    expect(plain.classes().some((name) => name.startsWith('wx-space--align'))).toBe(false)

    const arranged = mount(WxSpace, { props: { align: 'baseline', justify: 'between' } })
    expect(arranged.classes()).toContain('wx-space--align-baseline')
    expect(arranged.classes()).toContain('wx-space--justify-between')
  })

  it('renders through another element and can go inline', () => {
    const wrapper = mount(WxSpace, { props: { as: 'nav', inline: true, fill: true } })

    expect(wrapper.element.tagName).toBe('NAV')
    expect(wrapper.classes()).toContain('wx-space--inline')
    expect(wrapper.classes()).toContain('wx-space--fill')
  })
})
