import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxAvatar from './Avatar.vue'
import WxAvatarGroup from '../AvatarGroup/AvatarGroup.vue'

describe('WxAvatar', () => {
  it('takes initials from a name', () => {
    expect(mount(WxAvatar, { props: { name: 'Ada Lovelace' } }).text()).toBe('AL')
    expect(mount(WxAvatar, { props: { name: 'Ганна Роут' } }).text()).toBe('ГР')
  })

  it('gives a single word two letters rather than one', () => {
    expect(mount(WxAvatar, { props: { name: 'Nova' } }).text()).toBe('NO')
  })

  it('reads a name of three words as its first and last', () => {
    expect(mount(WxAvatar, { props: { name: 'Anna Maria Roth' } }).text()).toBe('AR')
  })

  it('falls back to an icon with no name at all', () => {
    const wrapper = mount(WxAvatar)

    expect(wrapper.text()).toBe('')
    expect(wrapper.find('.wx-icon').exists()).toBe(true)
  })

  it('gives the same name the same colour every time', () => {
    const once = mount(WxAvatar, { props: { name: 'Grace Hopper' } }).classes()
    const again = mount(WxAvatar, { props: { name: 'Grace Hopper' } }).classes()

    expect(once).toEqual(again)
    // And a tone asked for by name is the tone that is used.
    expect(mount(WxAvatar, { props: { name: 'Grace', tone: 'danger' } }).classes()).toContain(
      'wx-avatar--danger',
    )
  })

  it('is neutral when there is no name to colour by', () => {
    expect(mount(WxAvatar).classes()).toContain('wx-avatar--neutral')
  })

  it('applies size and shape', () => {
    const wrapper = mount(WxAvatar, { props: { size: 'xl', shape: 'square' } })

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining(['wx-avatar--xl', 'wx-avatar--square']),
    )
  })

  it('prefers the slot over anything it would have worked out', () => {
    const wrapper = mount(WxAvatar, { props: { name: 'Ada Lovelace' }, slots: { default: '7' } })

    expect(wrapper.text()).toBe('7')
  })
})

describe('WxAvatarGroup', () => {
  const four = {
    default: `
      <wx-avatar name="Ada Lovelace" />
      <wx-avatar name="Grace Hopper" />
      <wx-avatar name="Alan Turing" />
      <wx-avatar name="Barbara Liskov" />
    `,
  }

  const global = { components: { WxAvatar } }

  it('shows every avatar when there is no limit', () => {
    const wrapper = mount(WxAvatarGroup, { slots: four, global })

    expect(wrapper.findAllComponents(WxAvatar)).toHaveLength(4)
  })

  it('counts the ones over the limit instead of rendering them', () => {
    const wrapper = mount(WxAvatarGroup, { props: { max: 2 }, slots: four, global })

    // Two avatars plus the one carrying the count — the other two are not mounted.
    expect(wrapper.findAllComponents(WxAvatar)).toHaveLength(3)
    expect(wrapper.get('.wx-avatar-group__rest').text()).toBe('+2')
  })

  it('has nothing to count when the limit is not reached', () => {
    const wrapper = mount(WxAvatarGroup, { props: { max: 9 }, slots: four, global })

    expect(wrapper.find('.wx-avatar-group__rest').exists()).toBe(false)
  })
})
