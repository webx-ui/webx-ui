import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxPopconfirm from './Popconfirm.vue'

/* The panel is teleported; unmounting between tests takes it with it. */
enableAutoUnmount(afterEach)

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
})
