import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxButton from '../Button/Button.vue'
import WxDialog from './Dialog.vue'

// The panel is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

beforeEach(() => localStorage.clear())

function factory(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(WxDialog, {
    props,
    slots: {
      trigger: '<wx-button>Edit</wx-button>',
      default: '<label>Title <input class="field" /></label>',
      ...slots,
    },
    global: { components: { WxButton } },
    attachTo: document.body,
  })
}

function panel() {
  return document.body.querySelector<HTMLElement>('.wx-dialog')
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

describe('WxDialog', () => {
  it('shows nothing until the trigger is clicked', async () => {
    const wrapper = factory({ title: 'Edit page' })

    expect(panel()).toBeNull()

    await wrapper.get('button').trigger('click')
    await nextTick()

    expect(panel()).not.toBeNull()
    expect(panel()?.querySelector('.field')).not.toBeNull()
    expect(panel()?.querySelector('.wx-dialog__title')?.textContent).toContain('Edit page')
    expect(wrapper.emitted('open')).toHaveLength(1)
  })

  it('is controllable through v-model:open', async () => {
    const wrapper = factory({ open: true, title: 'Edit page' })
    await nextTick()

    expect(panel()).not.toBeNull()

    await wrapper.setProps({ open: false })
    await nextTick()

    // The panel leaves through a closing state before it is unmounted.
    expect(document.querySelector('.wx-dialog__viewport')?.getAttribute('data-state')).toBe(
      'closed',
    )
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('closes through the × and through the footer', async () => {
    const wrapper = factory(
      { open: true, title: 'Edit page' },
      {
        footer:
          '<template #footer="{ close }"><button class="done" @click="close">Done</button></template>',
      },
    )
    await nextTick()

    panel()?.querySelector<HTMLElement>('.wx-dialog__close')?.click()
    await nextTick()
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])

    await wrapper.setProps({ open: true })
    await nextTick()

    panel()?.querySelector<HTMLElement>('.done')?.click()
    await nextTick()
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  /* A number is pixels; anything else is a CSS length, handed over untouched. */
  it('takes a size in pixels or per cent', async () => {
    const wrapper = factory({ open: true, width: 720, height: '80%' })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-width')).toBe('720px')
    expect(panel()?.style.getPropertyValue('--wx-dialog-height')).toBe('80%')

    await wrapper.setProps({ width: '60%' })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-width')).toBe('60%')
  })

  it('keeps the heading and the buttons out of the scrolling part', async () => {
    factory(
      { open: true, title: 'Edit page' },
      {
        extra: '<template #extra><span class="badge">Draft</span></template>',
        footer: '<template #footer>Save</template>',
      },
    )
    await nextTick()

    expect(panel()?.querySelector('.wx-dialog__head .badge')).not.toBeNull()
    expect(panel()?.querySelector('.wx-dialog__foot')).not.toBeNull()
    // The body is the one part with its own overflow; head and foot sit outside it.
    expect(panel()?.querySelector('.wx-dialog__body .wx-dialog__head')).toBeNull()
  })

  it('splits the body when there is a sidebar', async () => {
    factory(
      { open: true, title: 'Edit page', sidebarWidth: 240 },
      { sidebar: '<nav class="nav" />' },
    )
    await nextTick()

    expect(panel()?.classList.contains('wx-dialog--split')).toBe(true)
    expect(panel()?.querySelector('.wx-dialog__sidebar .nav')).not.toBeNull()
    expect(panel()?.style.getPropertyValue('--wx-dialog-sidebar-width')).toBe('240px')
  })

  /*
   * The long kind: too much to fit, so the panel grows past the screen and the wrapper
   * around it is what scrolls. The footer stays against the bottom unless asked not to.
   */
  it('hands the scrolling to the wrapper when the content is long', async () => {
    const wrapper = factory(
      { open: true, title: 'Brands', scroll: 'panel' },
      { footer: '<template #footer>Pick</template>' },
    )
    await nextTick()

    const viewport = document.querySelector('.wx-dialog__viewport')
    expect(viewport?.classList.contains('wx-dialog__viewport--scrolls')).toBe(true)
    expect(panel()?.classList.contains('wx-dialog--long')).toBe(true)
    expect(panel()?.classList.contains('wx-dialog--sticky-foot')).toBe(true)

    await wrapper.setProps({ stickyFooter: false })
    await nextTick()

    expect(panel()?.classList.contains('wx-dialog--sticky-foot')).toBe(false)
  })

  /* Nothing to drag into and nothing to stretch: the gesture would fight the scroll. */
  it('ignores dragging and resizing while it is the long kind', async () => {
    factory({ open: true, title: 'Brands', scroll: 'panel', draggable: true, resizable: true })
    await nextTick()

    expect(panel()?.querySelector('.wx-dialog__grip')).toBeNull()
    expect(panel()?.classList.contains('wx-dialog--draggable')).toBe(false)

    const head = panel()?.querySelector('.wx-dialog__head') as HTMLElement
    press(head, 'pointerdown', 100, 100)
    press(window, 'pointermove', 150, 130)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-x')).toBe('')
  })

  it('is named for screen readers even without a visible heading', async () => {
    factory({ open: true, ariaLabel: 'Edit page', closable: false })
    await nextTick()

    expect(document.querySelector('.wx-sr-only')?.textContent).toBe('Edit page')
  })

  it('is dragged by its heading, and remembers where it was left', async () => {
    const wrapper = factory({ open: true, title: 'Edit page', draggable: true, persist: 'page' })
    await nextTick()

    const head = panel()?.querySelector('.wx-dialog__head') as HTMLElement
    press(head, 'pointerdown', 100, 100)
    press(window, 'pointermove', 150, 130)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-x')).toBe('50px')
    expect(panel()?.style.getPropertyValue('--wx-dialog-y')).toBe('30px')

    press(window, 'pointerup', 150, 130)
    await nextTick()

    expect(wrapper.emitted('layout')?.at(-1)).toEqual([{ x: 50, y: 30 }])
    expect(JSON.parse(localStorage.getItem('wx-dialog:page') ?? '{}')).toEqual({ x: 50, y: 30 })
  })

  it('does not drag from a control in the heading', async () => {
    factory(
      { open: true, title: 'Edit page', draggable: true },
      { extra: '<template #extra><button class="act">Publish</button></template>' },
    )
    await nextTick()

    const action = panel()?.querySelector('.act') as HTMLElement
    press(action, 'pointerdown', 100, 100)
    press(window, 'pointermove', 150, 130)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-x')).toBe('')
  })

  it('resizes from the grip, growing towards the corner under the pointer', async () => {
    factory({ open: true, title: 'Edit page', resizable: true, width: 520, height: 200 })
    await nextTick()

    const grip = panel()?.querySelector('.wx-dialog__grip') as HTMLElement
    press(grip, 'pointerdown', 0, 0)
    press(window, 'pointermove', 60, 40)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-width')).toBe('580px')
    expect(panel()?.style.getPropertyValue('--wx-dialog-height')).toBe('240px')
    // Half the growth would go the other way; the offset cancels it out.
    expect(panel()?.style.getPropertyValue('--wx-dialog-x')).toBe('30px')
  })

  it('will not be resized below its minimum', async () => {
    factory({ open: true, resizable: true, width: 520, height: 300, minWidth: 400, minHeight: 250 })
    await nextTick()

    const grip = panel()?.querySelector('.wx-dialog__grip') as HTMLElement
    press(grip, 'pointerdown', 0, 0)
    press(window, 'pointermove', -400, -400)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-width')).toBe('400px')
    expect(panel()?.style.getPropertyValue('--wx-dialog-height')).toBe('250px')
  })

  it('restores a remembered size, and forgets it on reset', async () => {
    localStorage.setItem('wx-dialog:page', JSON.stringify({ width: 640, x: 20 }))

    const wrapper = factory({ open: true, resizable: true, persist: 'page' })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-width')).toBe('640px')
    expect(panel()?.style.getPropertyValue('--wx-dialog-x')).toBe('20px')

    wrapper.vm.reset()
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-width')).toBe('520px')
    expect(localStorage.getItem('wx-dialog:page')).toBeNull()
  })

  it('ignores a stored layout that is not one', async () => {
    localStorage.setItem('wx-dialog:page', '{ not json')

    factory({ open: true, persist: 'page' })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-width')).toBe('520px')
  })

  /* Without a key to remember it by, every opening starts from the declared size. */
  it('forgets where it was dragged when it closes unpersisted', async () => {
    const wrapper = factory({ open: true, title: 'Edit page', draggable: true })
    await nextTick()

    const head = panel()?.querySelector('.wx-dialog__head') as HTMLElement
    press(head, 'pointerdown', 100, 100)
    press(window, 'pointermove', 150, 130)
    press(window, 'pointerup', 150, 130)
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-x')).toBe('50px')

    await wrapper.setProps({ open: false })
    await wrapper.setProps({ open: true })
    await nextTick()

    expect(panel()?.style.getPropertyValue('--wx-dialog-x')).toBe('')
    expect(localStorage.length).toBe(0)
  })

  /*
   * The wrapper around the panel covers the screen, so the space beside the panel is the
   * wrapper itself rather than anything Reka would call outside.
   */
  it('closes when the space beside the panel is clicked', async () => {
    const wrapper = factory({ open: true, title: 'Edit page' })
    await nextTick()

    const viewport = document.querySelector('.wx-dialog__viewport') as HTMLElement
    viewport.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('stays open when the click was inside the panel', async () => {
    const wrapper = factory({ open: true, title: 'Edit page' })
    await nextTick()

    panel()?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:open')).toBeUndefined()
  })

  it('can be made to ignore Escape and clicks outside', async () => {
    const wrapper = factory({ open: true, closeOnEscape: false, closeOnOverlay: false })
    await nextTick()

    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
    )
    const viewport = document.querySelector('.wx-dialog__viewport') as HTMLElement
    viewport.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
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
