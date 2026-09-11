import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxHeading from './Heading.vue'

describe('WxHeading', () => {
  it('renders the level asked for', () => {
    expect(mount(WxHeading, { props: { level: 1 } }).element.tagName).toBe('H1')
    expect(mount(WxHeading).element.tagName).toBe('H2')
    expect(mount(WxHeading, { props: { level: 5 } }).element.tagName).toBe('H5')
  })

  it('sizes itself from the level', () => {
    expect(mount(WxHeading, { props: { level: 1 } }).classes()).toContain('wx-heading--3xl')
    expect(mount(WxHeading, { props: { level: 4 } }).classes()).toContain('wx-heading--lg')
  })

  it('lets size be set without changing the outline', () => {
    const wrapper = mount(WxHeading, { props: { level: 1, size: 'md' } })

    expect(wrapper.element.tagName).toBe('H1')
    expect(wrapper.classes()).toContain('wx-heading--md')
    expect(wrapper.classes()).not.toContain('wx-heading--3xl')
  })

  it('takes a tone, an alignment and truncation', () => {
    const wrapper = mount(WxHeading, {
      props: { tone: 'muted', align: 'center', truncate: true },
      slots: { default: 'Section' },
    })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining([
        'wx-heading--tone-muted',
        'wx-heading--align-center',
        'wx-heading--truncate',
      ]),
    )
    expect(wrapper.text()).toBe('Section')
  })
})
