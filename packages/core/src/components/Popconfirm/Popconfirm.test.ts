import { afterAll, afterEach, describe, expect, it } from 'vitest'
import { disableAutoUnmount, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxPopconfirm from './Popconfirm.vue'

/* The panel is teleported; unmounting between tests takes it with it. */
enableAutoUnmount(afterEach)
afterAll(disableAutoUnmount)

describe('WxPopconfirm', () => {
  function open() {
    return mount(WxPopconfirm, {
      props: { title: 'Delete this order?', open: true },
      slots: { trigger: '<button class="trigger">Delete</button>' },
    })
  }

  it('asks the question where the answer will land', async () => {
    open()
    await nextTick()

    expect(document.querySelector('.wx-popconfirm__title')?.textContent).toBe('Delete this order?')
  })

  it('reports a yes', async () => {
    const wrapper = open()
    await nextTick()

    const buttons = document.querySelectorAll<HTMLElement>('.wx-popconfirm__actions button')
    buttons[1].click()
    await nextTick()

    expect(wrapper.emitted('confirm')).toHaveLength(1)
  })

  it('reports a no', async () => {
    const wrapper = open()
    await nextTick()

    const buttons = document.querySelectorAll<HTMLElement>('.wx-popconfirm__actions button')
    buttons[0].click()
    await nextTick()

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('treats being dismissed as a no', async () => {
    const wrapper = open()
    await nextTick()

    await wrapper.setProps({ open: false })
    await nextTick()

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('is a dialog named by its trigger that reads out the question', async () => {
    mount(WxPopconfirm, {
      props: { title: 'Delete this order?', description: 'It cannot be brought back.', open: true },
      slots: { trigger: '<button class="trigger">Delete</button>' },
      attachTo: document.body,
    })
    await nextTick()

    const panel = document.querySelector<HTMLElement>('.wx-popconfirm')!
    expect(panel.getAttribute('role')).toBe('dialog')
    expect(document.getElementById(panel.getAttribute('aria-labelledby')!)?.textContent).toBe(
      'Delete',
    )
    const described = panel.getAttribute('aria-describedby')!.split(' ')
    expect(described.map((id) => document.getElementById(id)?.textContent)).toEqual([
      'Delete this order?',
      'It cannot be brought back.',
    ])
  })

  it('puts the focus on the button that keeps things as they were', async () => {
    const wrapper = mount(WxPopconfirm, {
      props: { title: 'Delete this order?', cancelText: 'Keep it' },
      slots: { trigger: '<button class="trigger">Delete</button>' },
      attachTo: document.body,
    })
    await wrapper.get('.trigger').trigger('click')
    await flushPromises()

    expect(document.activeElement?.textContent?.trim()).toBe('Keep it')
  })

  it('goes ahead on Enter, even from the focused Cancel', async () => {
    const wrapper = open()
    await flushPromises()

    const cancel = document.querySelector<HTMLElement>('.wx-popconfirm__actions button')!
    const enter = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true })
    cancel.dispatchEvent(enter)
    await nextTick()

    expect(enter.defaultPrevented).toBe(true)
    expect(wrapper.emitted('confirm')).toHaveLength(1)
    expect(wrapper.emitted('cancel')).toBeUndefined()
  })
})
