import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxText from './Text.vue'

describe('WxText', () => {
  it('is a span with the body scale by default', () => {
    const wrapper = mount(WxText, { slots: { default: 'Hello' } })

    expect(wrapper.element.tagName).toBe('SPAN')
    expect(wrapper.text()).toBe('Hello')
    expect(wrapper.classes()).toEqual(
      expect.arrayContaining(['wx-text--md', 'wx-text--regular', 'wx-text--tone-default']),
    )
  })

  it('renders any element asked for', () => {
    expect(mount(WxText, { props: { as: 'p' } }).element.tagName).toBe('P')
    expect(mount(WxText, { props: { as: 'label' } }).element.tagName).toBe('LABEL')
  })

  it('applies size, weight, tone and alignment', () => {
    const wrapper = mount(WxText, {
      props: { size: 'sm', weight: 'semibold', tone: 'muted', align: 'center' },
    })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining([
        'wx-text--sm',
        'wx-text--semibold',
        'wx-text--tone-muted',
        'wx-text--align-center',
      ]),
    )
  })

  it('truncates on one line, or clamps to a number of lines', () => {
    const single = mount(WxText, { props: { truncate: true } })
    expect(single.classes()).toContain('wx-text--truncate')
    expect(single.classes()).not.toContain('wx-text--clamp')

    const clamped = mount(WxText, { props: { truncate: 3 } })
    expect(clamped.classes()).toContain('wx-text--clamp')
    expect(clamped.attributes('style')).toContain('--wx-text-lines: 3')
  })

  it('switches to the mono family for tabular data', () => {
    expect(mount(WxText, { props: { mono: true } }).classes()).toContain('wx-text--mono')
  })
})
