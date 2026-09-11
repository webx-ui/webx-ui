import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxCol from './Col.vue'

describe('WxCol', () => {
  it('spans the full row by default', () => {
    const wrapper = mount(WxCol, { slots: { default: 'Cell' } })

    expect(wrapper.attributes('style')).toContain('--wx-col-span: 24')
    expect(wrapper.text()).toBe('Cell')
  })

  it('writes a variable per breakpoint', () => {
    const wrapper = mount(WxCol, { props: { span: 24, md: 12, lg: 8 } })
    const style = wrapper.attributes('style')

    expect(style).toContain('--wx-col-span: 24')
    expect(style).toContain('--wx-col-span-md: 12')
    expect(style).toContain('--wx-col-span-lg: 8')
  })

  it('takes a span and an offset together at one breakpoint', () => {
    const wrapper = mount(WxCol, { props: { span: 12, offset: 6, lg: { span: 8, offset: 2 } } })
    const style = wrapper.attributes('style')

    expect(style).toContain('--wx-col-offset: 6')
    expect(style).toContain('--wx-col-span-lg: 8')
    expect(style).toContain('--wx-col-offset-lg: 2')
  })

  it('leaves untouched breakpoints out of the style', () => {
    const style = mount(WxCol, { props: { span: 6 } }).attributes('style')

    expect(style).not.toContain('--wx-col-span-sm')
    expect(style).not.toContain('--wx-col-offset')
    expect(style).not.toContain('--wx-col-order')
  })

  it('reorders without touching the markup', () => {
    const wrapper = mount(WxCol, { props: { order: 2, as: 'section' } })

    expect(wrapper.attributes('style')).toContain('--wx-col-order: 2')
    expect(wrapper.element.tagName).toBe('SECTION')
  })
})
