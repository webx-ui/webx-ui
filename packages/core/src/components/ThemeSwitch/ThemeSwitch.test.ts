import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxThemeSwitch from './ThemeSwitch.vue'

describe('WxThemeSwitch', () => {
  it('offers the three choices as a radio group', () => {
    const wrapper = mount(WxThemeSwitch, { props: { modelValue: 'system' } })
    const options = wrapper.findAll('[role="radio"]')

    expect(wrapper.attributes('role')).toBe('radiogroup')
    expect(options).toHaveLength(3)
    expect(options.map((option) => option.attributes('aria-label'))).toEqual([
      'Light',
      'Follow the system',
      'Dark',
    ])
  })

  it('reports the choice', async () => {
    const wrapper = mount(WxThemeSwitch, { props: { modelValue: 'system' } })

    await wrapper.findAll('[role="radio"]')[2]!.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['dark'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['dark'])
  })

  it('says nothing when the choice is already the one made', async () => {
    const wrapper = mount(WxThemeSwitch, { props: { modelValue: 'dark' } })

    await wrapper.findAll('[role="radio"]')[2]!.trigger('click')

    expect(wrapper.emitted('change')).toBeUndefined()
  })

  // The thumb is positioned from this, so it is the only thing that says which cell is lit.
  it('publishes the chosen position on the root', async () => {
    const wrapper = mount(WxThemeSwitch, { props: { modelValue: 'light' } })

    expect(wrapper.attributes('style')).toContain('--wx-theme-switch-index: 0')

    await wrapper.setProps({ modelValue: 'dark' })

    expect(wrapper.attributes('style')).toContain('--wx-theme-switch-index: 2')
  })

  it('moves the choice with the arrow keys', async () => {
    const wrapper = mount(WxThemeSwitch, { props: { modelValue: 'system' } })

    await wrapper.trigger('keydown', { key: 'ArrowLeft' })

    expect(wrapper.emitted('change')?.at(-1)).toEqual(['light'])
  })

  it('leaves only the chosen option in the tab order', () => {
    const wrapper = mount(WxThemeSwitch, { props: { modelValue: 'dark' } })

    expect(
      wrapper.findAll('[role="radio"]').map((option) => option.attributes('tabindex')),
    ).toEqual(['-1', '-1', '0'])
  })

  it('does not answer a disabled switch', async () => {
    const wrapper = mount(WxThemeSwitch, { props: { modelValue: 'system', disabled: true } })

    await wrapper.findAll('[role="radio"]')[0]!.trigger('click')

    expect(wrapper.emitted('change')).toBeUndefined()
  })
})
