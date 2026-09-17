import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RowMenu from './RowMenu.vue'
import type { RowAction } from './types'

const actions: RowAction[] = [
  { key: 'delete', label: 'Delete', icon: 'trash', danger: true },
  { key: 'open', label: 'Open', icon: 'edit' },
  { key: 'copy', label: 'Copy link', icon: 'link' },
]

function menu(list: RowAction[] = actions) {
  return mount(RowMenu, { props: { actions: list, label: 'About us' } })
}

describe('WxRowMenu', () => {
  it('is a menu and not a row, whatever the width', () => {
    const wrapper = menu()

    expect(wrapper.find('.wx-actions__menu').exists()).toBe(true)
    expect(wrapper.find('.wx-actions__row').exists()).toBe(false)
  })

  it('is still a menu for a single action', () => {
    expect(menu([{ key: 'delete', label: 'Delete', danger: true }]).exists()).toBe(true)
  })

  it('draws nothing at all when a record offers nothing', () => {
    expect(menu([]).find('.wx-actions').exists()).toBe(false)
  })

  it('puts the destructive action last, behind a rule, wherever it was written', async () => {
    const wrapper = menu()

    await wrapper.get('.wx-actions__menu button').trigger('click')

    const items = document.querySelectorAll('.wx-dropdown-item')

    expect([...items].map((item) => item.textContent?.trim())).toEqual([
      'Open',
      'Copy link',
      'Delete',
    ])
    // The rule belongs to the destructive row, so the two above it read as one group.
    expect(document.querySelector('.wx-dropdown__divider')).not.toBeNull()

    wrapper.unmount()
  })

  it('leaves the rule out when the only action is the destructive one', async () => {
    const wrapper = menu([{ key: 'delete', label: 'Delete', danger: true }])

    await wrapper.get('.wx-actions__menu button').trigger('click')

    expect(document.querySelector('.wx-dropdown__divider')).toBeNull()

    wrapper.unmount()
  })
})
