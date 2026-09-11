import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxButton from '../Button/Button.vue'
import WxDrawer from './Drawer.vue'

// The panel is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

beforeEach(() => localStorage.clear())

function factory(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(WxDrawer, {
    props,
    slots: {
      trigger: '<wx-button>Open</wx-button>',
      default: '<label>Title <input class="field" /></label>',
      ...slots,
    },
    global: { components: { WxButton } },
    attachTo: document.body,
  })
}

function panel() {
  return document.body.querySelector<HTMLElement>('.wx-drawer')
}

function press(target: EventTarget, type: string, x: number, y: number) {
  target.dispatchEvent(
    new PointerEvent(type, {
      bubbles: true,
      clientX: x,
      clientY: y,
      button: 0,
      pointerType: 'mouse',
    }),
  )
}

describe('WxDrawer', () => {
  it('shows nothing until the trigger is clicked', async () => {
    const wrapper = factory({ title: 'Order' })

    expect(panel()).toBeNull()

    await wrapper.get('button').trigger('click')
    await nextTick()

    expect(panel()).not.toBeNull()
    expect(panel()?.querySelector('.field')).not.toBeNull()
    expect(wrapper.emitted('open')).toHaveLength(1)
  })

  it('slides in from the side it was given', async () => {
    const wrapper = factory({ open: true, side: 'left', title: 'Order' })
    await nextTick()

    expect(panel()?.classList.contains('wx-drawer--left')).toBe(true)

    await wrapper.setProps({ side: 'bottom' })
    await nextTick()

    expect(panel()?.classList.contains('wx-drawer--bottom')).toBe(true)
  })

  /* A number is pixels; anything else is a CSS length, handed over untouched. */
  it('takes a size in pixels or per cent', async () => {
    const wrapper = factory({ open: true, size: 480 })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('480px')

    await wrapper.setProps({ size: '40%' })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('40%')
  })

  it('keeps the heading and the buttons out of the scrolling part', async () => {
    factory(
      { open: true, title: 'Order' },
      {
        extra: '<template #extra><span class="badge">Paid</span></template>',
        footer: '<template #footer>Save</template>',
      },
    )
    await nextTick()

    expect(panel()?.querySelector('.wx-drawer__head .badge')).not.toBeNull()
    expect(panel()?.querySelector('.wx-drawer__foot')).not.toBeNull()
    // The body is the one part with its own overflow; head and foot sit outside it.
    expect(panel()?.querySelector('.wx-drawer__body .wx-drawer__head')).toBeNull()
    expect(panel()?.querySelector('.wx-drawer__body .wx-drawer__content')).not.toBeNull()
  })

  it('splits the body when there is a sidebar', async () => {
    factory({ open: true, title: 'Order', sidebarWidth: '30%' }, { sidebar: '<nav class="nav" />' })
    await nextTick()

    expect(panel()?.classList.contains('wx-drawer--split')).toBe(true)
    expect(panel()?.querySelector('.wx-drawer__sidebar .nav')).not.toBeNull()
    expect(panel()?.style.getPropertyValue('--wx-drawer-sidebar-width')).toBe('30%')
  })

  it('closes through the × and through the footer', async () => {
    const wrapper = factory(
      { open: true, title: 'Order' },
      {
        footer:
          '<template #footer="{ close }"><button class="done" @click="close">Done</button></template>',
      },
    )
    await nextTick()

    panel()?.querySelector<HTMLElement>('.wx-drawer__close')?.click()
    await nextTick()
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])

    await wrapper.setProps({ open: true })
    await nextTick()

    panel()?.querySelector<HTMLElement>('.done')?.click()
    await nextTick()
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  /* Every side grows towards the middle of the screen, whichever way the pointer went. */
  it('is resized by the edge it faces the page with', async () => {
    const wrapper = factory({ open: true, title: 'Order', resizable: true, persist: 'order' })
    await nextTick()

    const handle = panel()?.querySelector('.wx-drawer__handle') as HTMLElement
    press(handle, 'pointerdown', 500, 300)
    press(window, 'pointermove', 400, 300)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('480px')

    press(window, 'pointerup', 400, 300)
    await nextTick()

    expect(wrapper.emitted('layout')?.at(-1)).toEqual([{ size: 480 }])
    expect(JSON.parse(localStorage.getItem('wx-drawer:order') ?? '{}')).toEqual({ size: 480 })
  })

  it('resizes a bottom panel along the other axis', async () => {
    factory({ open: true, title: 'Order', side: 'bottom', resizable: true, size: 300 })
    await nextTick()

    const handle = panel()?.querySelector('.wx-drawer__handle') as HTMLElement
    press(handle, 'pointerdown', 300, 400)
    press(window, 'pointermove', 300, 350)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('350px')
  })

  it('will not be resized below its minimum', async () => {
    factory({ open: true, resizable: true, size: 380, minSize: 320 })
    await nextTick()

    const handle = panel()?.querySelector('.wx-drawer__handle') as HTMLElement
    press(handle, 'pointerdown', 500, 300)
    press(window, 'pointermove', 900, 300)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('320px')
  })

  /* The handle is the one part that cannot be reached any other way than by key. */
  it('is resized from the keyboard too', async () => {
    factory({ open: true, resizable: true, size: 380 })
    await nextTick()

    const handle = panel()?.querySelector('.wx-drawer__handle') as HTMLElement
    expect(handle.getAttribute('role')).toBe('separator')
    expect(handle.getAttribute('aria-orientation')).toBe('vertical')

    handle.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }))
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('404px')
  })

  it('restores a remembered size, and forgets it on reset', async () => {
    localStorage.setItem('wx-drawer:order', JSON.stringify({ size: 640 }))

    const wrapper = factory({ open: true, resizable: true, persist: 'order' })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('640px')

    wrapper.vm.reset()
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('380px')
    expect(localStorage.getItem('wx-drawer:order')).toBeNull()
  })

  it('ignores a stored size that is not one', async () => {
    localStorage.setItem('wx-drawer:order', JSON.stringify({ size: 'wide' }))

    factory({ open: true, persist: 'order' })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-drawer-size')).toBe('380px')
  })

  it('can be made to ignore Escape and clicks outside', async () => {
    const wrapper = factory({ open: true, closeOnEscape: false, closeOnOverlay: false })
    await nextTick()

    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
    )
    await nextTick()

    expect(wrapper.emitted('update:open')).toBeUndefined()
  })

  it('closes on Escape by default', async () => {
    const wrapper = factory({ open: true })
    await nextTick()

    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
    )
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })
})
