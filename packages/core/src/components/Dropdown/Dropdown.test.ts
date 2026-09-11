import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxButton from '../Button/Button.vue'
import WxDropdownItem from '../DropdownItem/DropdownItem.vue'
import WxDropdown from './Dropdown.vue'

// The panel is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

function factory(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(WxDropdown, {
    props,
    slots: {
      trigger: '<wx-button>Actions</wx-button>',
      default:
        '<wx-dropdown-item icon="edit">Edit</wx-dropdown-item>' +
        '<wx-dropdown-item tone="danger" icon="trash">Delete</wx-dropdown-item>',
      ...slots,
    },
    global: { components: { WxButton, WxDropdownItem } },
    attachTo: document.body,
  })
}

function panel() {
  return document.body.querySelector('.wx-dropdown')
}

describe('WxDropdown', () => {
  it('shows nothing until the trigger is clicked', async () => {
    const wrapper = factory()

    expect(panel()).toBeNull()

    await wrapper.get('button').trigger('click')
    await nextTick()

    expect(panel()).not.toBeNull()
    expect(panel()?.textContent).toContain('Edit')
    expect(wrapper.emitted('open')).toHaveLength(1)
  })

  it('is controllable through v-model:open', async () => {
    const wrapper = factory({ open: true })
    await nextTick()

    expect(panel()).not.toBeNull()

    await wrapper.setProps({ open: false })
    await nextTick()

    // The panel leaves through a closing state before it is unmounted.
    expect(panel()?.getAttribute('data-state')).toBe('closed')
  })

  it('marks its trigger as the thing that opens the panel', async () => {
    const wrapper = factory()
    const trigger = wrapper.get('button')

    expect(trigger.attributes('aria-expanded')).toBe('false')

    await trigger.trigger('click')
    await nextTick()

    expect(trigger.attributes('aria-expanded')).toBe('true')
  })

  it('closes when an item is clicked', async () => {
    const wrapper = factory({ open: true })
    await nextTick()

    const item = panel()?.querySelector('.wx-dropdown-item') as HTMLElement
    item.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('stays open while the panel is worked in, when asked', async () => {
    const wrapper = factory(
      { open: true, closeOnClick: false },
      { default: '<label><input type="checkbox" /> Only published</label>' },
    )
    await nextTick()

    const checkbox = panel()?.querySelector('input') as HTMLElement
    checkbox.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')).toBeUndefined()
  })

  it('hands close to the content slot', async () => {
    const wrapper = mount(WxDropdown, {
      props: { open: true },
      slots: {
        trigger: '<button>Open</button>',
        default:
          '<template #default="{ close }"><button class="done" @click="close">Done</button></template>',
      },
      attachTo: document.body,
    })
    await nextTick()

    const done = panel()?.querySelector('.done') as HTMLElement
    done.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('places the panel where it is told', async () => {
    factory({ open: true, side: 'top', align: 'end' })
    await nextTick()

    expect(panel()?.getAttribute('data-side')).toBe('top')
    expect(panel()?.getAttribute('data-align')).toBe('end')
  })

  it('can be made at least as wide as its trigger', async () => {
    factory({ open: true, matchTriggerWidth: true })
    await nextTick()

    expect(panel()?.classList.contains('wx-dropdown--match-width')).toBe(true)
  })

  it('does not open while disabled', async () => {
    const wrapper = factory({ disabled: true })

    await wrapper.get('button').trigger('click')
    await nextTick()

    expect(panel()).toBeNull()
  })
})

describe('WxDropdownItem', () => {
  it('is a button that carries an icon and a label', () => {
    const wrapper = mount(WxDropdownItem, {
      props: { icon: 'edit' },
      slots: { default: 'Edit' },
    })

    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.attributes('type')).toBe('button')
    expect(wrapper.get('.wx-dropdown-item__label').text()).toBe('Edit')
    expect(wrapper.find('.wx-dropdown-item__before svg.wx-icon').exists()).toBe(true)
  })

  it('renders a link when it has an href', () => {
    const wrapper = mount(WxDropdownItem, { props: { href: '/pages/12' } })

    expect(wrapper.element.tagName).toBe('A')
    expect(wrapper.attributes('href')).toBe('/pages/12')
  })

  it('takes a tone and an active state', () => {
    const wrapper = mount(WxDropdownItem, { props: { tone: 'danger', active: true } })

    expect(wrapper.classes()).toContain('wx-dropdown-item--danger')
    expect(wrapper.classes()).toContain('is-active')
  })

  it('swallows clicks while disabled', async () => {
    const wrapper = mount(WxDropdownItem, { props: { disabled: true } })

    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('emits click when it is live', async () => {
    const wrapper = mount(WxDropdownItem)

    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })
})
