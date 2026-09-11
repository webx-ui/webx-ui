import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxRow from './Row.vue'

describe('WxRow', () => {
  it('publishes the gutter as a variable the columns inherit', () => {
    const wrapper = mount(WxRow)

    expect(wrapper.attributes('style')).toContain('--wx-row-gutter: 16px')
    expect(wrapper.attributes('style')).toContain('--wx-row-gutter-y: 16px')
  })

  it('takes a separate vertical gutter and CSS lengths', () => {
    const wrapper = mount(WxRow, { props: { gutter: '1rem', gutterY: 32 } })

    expect(wrapper.attributes('style')).toContain('--wx-row-gutter: 1rem')
    expect(wrapper.attributes('style')).toContain('--wx-row-gutter-y: 32px')
  })

  it('applies justify, align and nowrap', () => {
    const wrapper = mount(WxRow, {
      props: { justify: 'between', align: 'center', wrap: false },
    })

    expect(wrapper.classes()).toContain('wx-row--justify-between')
    expect(wrapper.classes()).toContain('wx-row--align-center')
    expect(wrapper.classes()).toContain('wx-row--nowrap')
  })
})
