import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AuditLinkAttrs from './AuditLinkAttrs.vue'

describe('AuditLinkAttrs', () => {
  it('says target and each rel', () => {
    const wrapper = mount(AuditLinkAttrs, {
      props: { target: '_blank', rel: 'nofollow  noopener' },
    })

    expect(wrapper.findAll('.wx-badge').map((badge) => badge.text())).toEqual([
      '_blank',
      'nofollow',
      'noopener',
    ])
    expect(wrapper.find('.wx-badge--warning').exists()).toBe(false)
  })

  it('marks _blank without noopener or noreferrer', () => {
    const wrapper = mount(AuditLinkAttrs, { props: { target: '_blank', rel: 'nofollow' } })

    expect(wrapper.find('.wx-badge--warning').text()).toBe('_blank')
  })

  it('draws nothing for a plain link', () => {
    const wrapper = mount(AuditLinkAttrs, { props: { target: null, rel: null } })

    expect(wrapper.find('.wx-audit-attrs').exists()).toBe(false)
  })
})
