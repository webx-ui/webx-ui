import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxTooltip from './Tooltip.vue'

/* The tip is teleported; unmounting between tests takes it with it. */
enableAutoUnmount(afterEach)

describe('WxTooltip', () => {
  it('leaves the trigger as it was given it', () => {
    const wrapper = mount(WxTooltip, {
      props: { content: 'Refresh' },
      slots: { default: '<button class="trigger">R</button>' },
    })

    // `as-child`: no wrapper around the control, so the tip is on the thing focused.
    expect(wrapper.get('.trigger').element.tagName).toBe('BUTTON')
  })

  it('shows nothing until it is opened', () => {
    mount(WxTooltip, {
      props: { content: 'Refresh' },
      slots: { default: '<button>R</button>' },
    })

    expect(document.querySelector('.wx-tooltip')).toBeNull()
  })

  it('opens when told to', async () => {
    mount(WxTooltip, {
      props: { content: 'Refresh', open: true },
      slots: { default: '<button>R</button>' },
    })
    await nextTick()

    expect(document.querySelector('.wx-tooltip')?.textContent).toContain('Refresh')
  })
})
