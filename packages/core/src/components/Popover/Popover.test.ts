import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxButton from '../Button/Button.vue'
import WxPopover from './Popover.vue'

// The panel is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

function factory(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(WxPopover, {
    props,
    slots: {
      trigger: '<wx-button>Rename</wx-button>',
      default: '<label>Title <input class="field" /></label>',
      ...slots,
    },
    global: { components: { WxButton } },
    attachTo: document.body,
  })
}

function panel() {
  return document.body.querySelector('.wx-popover')
}

describe('WxPopover', () => {
  it('shows nothing until the trigger is clicked', async () => {
    const wrapper = factory()

    expect(panel()).toBeNull()

    await wrapper.get('button').trigger('click')
    await nextTick()

    expect(panel()).not.toBeNull()
    expect(panel()?.querySelector('.field')).not.toBeNull()
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

  /* The point of the component: a click in the panel is work, not a choice. */
  it('stays open while the panel is worked in', async () => {
    const wrapper = factory({ open: true })
    await nextTick()

    const field = panel()?.querySelector('.field') as HTMLElement
    field.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')).toBeUndefined()
    expect(panel()?.getAttribute('data-state')).toBe('open')
  })

  it('hands close to the panel and to the footer', async () => {
    const wrapper = mount(WxPopover, {
      props: { open: true },
      slots: {
        trigger: '<button>Open</button>',
        default:
          '<template #default="{ close }"><button class="done" @click="close">Done</button></template>',
        footer:
          '<template #footer="{ close }"><button class="cancel" @click="close">Cancel</button></template>',
      },
      attachTo: document.body,
    })
    await nextTick()

    expect(panel()?.querySelector('.wx-popover__footer .cancel')).not.toBeNull()

    const done = panel()?.querySelector('.done') as HTMLElement
    done.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('has a heading only when it is given one', async () => {
    const plain = factory({ open: true })
    await nextTick()
    expect(panel()?.querySelector('.wx-popover__header')).toBeNull()

    /* Unmounted rather than wiped: the panel lives in the body, and Vue has to take
       it back itself. */
    plain.unmount()
    await nextTick()

    factory({ open: true, title: 'Rename tab', closable: true })
    await nextTick()

    expect(panel()?.querySelector('.wx-popover__title')?.textContent?.trim()).toBe('Rename tab')
    expect(panel()?.querySelector('.wx-popover__close')).not.toBeNull()
  })

  it('closes from the × in the heading', async () => {
    const wrapper = factory({ open: true, title: 'Rename tab', closable: true })
    await nextTick()

    const close = panel()?.querySelector('.wx-popover__close') as HTMLElement
    close.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('places the panel where it is told and points at the trigger', async () => {
    factory({ open: true, side: 'right', align: 'start' })
    await nextTick()

    expect(panel()?.getAttribute('data-side')).toBe('right')
    expect(panel()?.getAttribute('data-align')).toBe('start')
    expect(panel()?.querySelector('.wx-popover__arrow')).not.toBeNull()
  })

  it('can go without the arrow and take a width', async () => {
    const numeric = factory({ open: true, arrow: false, width: 320 })
    await nextTick()

    expect(panel()?.querySelector('.wx-popover__arrow')).toBeNull()
    expect((panel() as HTMLElement).style.width).toBe('320px')

    /* `width="320"` in a template is a string, and "320" alone is not a CSS length. */
    numeric.unmount()
    await nextTick()
    factory({ open: true, width: '320' })
    await nextTick()
    expect((panel() as HTMLElement).style.width).toBe('320px')
  })

  it('takes a width in any unit it is given', async () => {
    factory({ open: true, width: '24rem' })
    await nextTick()

    expect((panel() as HTMLElement).style.width).toBe('24rem')
  })

  it('marks its trigger as the thing that opens the panel', async () => {
    const wrapper = factory()
    const trigger = wrapper.get('button')

    expect(trigger.attributes('aria-expanded')).toBe('false')

    await trigger.trigger('click')
    await nextTick()

    expect(trigger.attributes('aria-expanded')).toBe('true')
  })

  it('does not open while disabled', async () => {
    const wrapper = factory({ disabled: true })

    await wrapper.get('button').trigger('click')
    await nextTick()

    expect(panel()).toBeNull()
  })
})
