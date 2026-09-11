import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxAlert from './Alert.vue'

describe('WxAlert', () => {
  it('renders the description and the type modifier', () => {
    const wrapper = mount(WxAlert, { props: { type: 'success', description: 'Saved' } })

    expect(wrapper.get('.wx-alert__body').text()).toBe('Saved')
    expect(wrapper.classes()).toContain('wx-alert--success')
    expect(wrapper.classes()).toContain('wx-alert--soft')
  })

  it('renders a title above the body', () => {
    const wrapper = mount(WxAlert, {
      props: { title: 'Import finished' },
      slots: { default: '12 rows added' },
    })

    expect(wrapper.get('.wx-alert__title').text()).toBe('Import finished')
    expect(wrapper.get('.wx-alert__body').text()).toBe('12 rows added')
    expect(wrapper.classes()).toContain('wx-alert--titled')
  })

  it('picks the icon from the type and lets a name override it', () => {
    const fromType = mount(WxAlert, { props: { type: 'danger' } })
    expect(fromType.findComponent({ name: 'WxIcon' }).props('name')).toBe('close-circle')

    const overridden = mount(WxAlert, { props: { type: 'danger', icon: 'bell' } })
    expect(overridden.findComponent({ name: 'WxIcon' }).props('name')).toBe('bell')
  })

  it('drops the icon when `icon` is false', () => {
    const wrapper = mount(WxAlert, { props: { icon: false } })

    expect(wrapper.find('.wx-alert__icon').exists()).toBe(false)
  })

  it('dismisses itself and emits close', async () => {
    const wrapper = mount(WxAlert, { props: { closable: true, description: 'Gone soon' } })

    await wrapper.get('.wx-alert__close').trigger('click')

    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(wrapper.find('.wx-alert').exists()).toBe(false)
  })

  it('stays visible when the caller controls it', async () => {
    const wrapper = mount(WxAlert, {
      props: { closable: true, visible: true, 'onUpdate:visible': () => {} },
    })

    await wrapper.get('.wx-alert__close').trigger('click')

    expect(wrapper.emitted('update:visible')).toEqual([[false]])
    expect(wrapper.find('.wx-alert').exists()).toBe(true)
  })

  it('announces itself only when asked', () => {
    expect(mount(WxAlert).attributes('role')).toBeUndefined()
    expect(mount(WxAlert, { props: { live: true } }).attributes('role')).toBe('alert')
  })
})
