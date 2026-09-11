import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxIcon from './Icon.vue'
import { builtinIcons, iconNames, registerIcons, resolveIcon } from './icons'

describe('WxIcon', () => {
  it('draws the named icon', () => {
    const wrapper = mount(WxIcon, { props: { name: 'check' } })

    expect(wrapper.element.tagName).toBe('svg')
    expect(wrapper.html()).toContain('<path')
    expect(wrapper.attributes('viewBox')).toBe('0 0 24 24')
  })

  it('renders nothing for an unknown name', () => {
    const wrapper = mount(WxIcon, { props: { name: 'no-such-icon' } })

    expect(wrapper.find('svg').exists()).toBe(false)
  })

  it('hides itself from screen readers unless it carries a label', () => {
    const silent = mount(WxIcon, { props: { name: 'trash' } })
    expect(silent.attributes('aria-hidden')).toBe('true')
    expect(silent.attributes('role')).toBeUndefined()

    const labelled = mount(WxIcon, { props: { name: 'trash', label: 'Delete' } })
    expect(labelled.attributes('aria-hidden')).toBeUndefined()
    expect(labelled.attributes('role')).toBe('img')
    expect(labelled.attributes('aria-label')).toBe('Delete')
  })

  it('matches the surrounding text by default and takes pixels as a number', () => {
    expect(mount(WxIcon, { props: { name: 'user' } }).attributes('style')).toContain(
      '--wx-icon-size: 1em',
    )
    expect(mount(WxIcon, { props: { name: 'user', size: 20 } }).attributes('style')).toContain(
      '--wx-icon-size: 20px',
    )
    expect(mount(WxIcon, { props: { name: 'user', size: '2rem' } }).attributes('style')).toContain(
      '--wx-icon-size: 2rem',
    )
  })

  it('spins on request', () => {
    const wrapper = mount(WxIcon, { props: { name: 'loader', spin: true } })

    expect(wrapper.classes()).toContain('wx-icon--spin')
  })
})

describe('icon registry', () => {
  it('resolves built-in icons and registered ones', () => {
    expect(resolveIcon('close')).toBe(builtinIcons.close)
    expect(resolveIcon('brand-logo')).toBeUndefined()

    registerIcons({ 'brand-logo': '<path d="M4 4h16v16H4z"/>' })

    expect(resolveIcon('brand-logo')).toBe('<path d="M4 4h16v16H4z"/>')
    expect(iconNames()).toContain('brand-logo')
  })

  it('renders a registered icon through the component', () => {
    registerIcons({ 'brand-mark': '<rect x="4" y="4" width="16" height="16"/>' })
    const wrapper = mount(WxIcon, { props: { name: 'brand-mark' } })

    expect(wrapper.html()).toContain('<rect')
  })

  it('ships every icon as non-empty markup', () => {
    for (const [name, markup] of Object.entries(builtinIcons)) {
      expect(markup, name).toMatch(/^<(path|circle|rect)/)
    }
  })
})
