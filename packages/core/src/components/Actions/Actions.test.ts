import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxAction from '../Action/Action.vue'
import WxActions from './Actions.vue'

// The collapsed menu is teleported, so it would outlive its wrapper.
enableAutoUnmount(afterEach)

/** jsdom has no ResizeObserver worth the name; this one can be fired by hand. */
class TestResizeObserver {
  static instances: TestResizeObserver[] = []

  constructor(private callback: () => void) {
    TestResizeObserver.instances.push(this)
  }

  observe() {}
  unobserve() {}
  disconnect() {}

  static fire() {
    for (const instance of TestResizeObserver.instances) instance.callback()
  }
}

const originalObserver = globalThis.ResizeObserver

/** jsdom lays nothing out, so the widths the measuring reads are set here. */
function setWidths({ row, available }: { row: number; available: number }) {
  Object.defineProperty(HTMLElement.prototype, 'scrollWidth', {
    configurable: true,
    get() {
      return this.classList.contains('wx-actions__row') ? row : 0
    },
  })
  Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
    configurable: true,
    get() {
      return available
    },
  })
}

function actions(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(WxActions, {
    props,
    slots: {
      default: '<wx-action type="add" /><wx-action type="edit" /><wx-action type="remove" />',
      ...slots,
    },
    global: { components: { WxAction } },
    attachTo: document.body,
  })
}

describe('WxActions', () => {
  beforeEach(() => {
    TestResizeObserver.instances = []
    globalThis.ResizeObserver = TestResizeObserver as unknown as typeof ResizeObserver
  })

  afterEach(() => {
    globalThis.ResizeObserver = originalObserver
    // @ts-expect-error — putting the real (missing) implementations back
    delete HTMLElement.prototype.scrollWidth
    // @ts-expect-error — same
    delete HTMLElement.prototype.clientWidth
  })

  it('is a labelled row of actions', () => {
    const wrapper = actions({ ariaLabel: 'Row actions' })

    expect(wrapper.attributes('role')).toBe('group')
    expect(wrapper.attributes('aria-label')).toBe('Row actions')
    expect(wrapper.findAll('.wx-actions__row .wx-action')).toHaveLength(3)
  })

  it('hands its size down to the actions', () => {
    const wrapper = actions({ size: 'sm' })

    for (const action of wrapper.findAll('.wx-action')) {
      expect(action.classes()).toContain('wx-action--sm')
    }
  })

  it("lets an action's own size win", () => {
    const wrapper = actions(
      { size: 'sm' },
      { default: '<wx-action type="add" size="lg" /><wx-action type="edit" />' },
    )
    const [first, second] = wrapper.findAll('.wx-action')

    expect(first.classes()).toContain('wx-action--lg')
    expect(second.classes()).toContain('wx-action--sm')
  })

  it('aligns the row', () => {
    expect(actions({ align: 'end' }).classes()).toContain('wx-actions--end')
  })

  it('has no menu at all unless collapsing is asked for', () => {
    expect(actions().find('.wx-actions__menu').exists()).toBe(false)
  })

  it('collapses into the menu when the row outgrows its container', async () => {
    setWidths({ row: 200, available: 120 })
    const wrapper = actions({ collapse: true })

    TestResizeObserver.fire()
    await nextTick()

    expect(wrapper.classes()).toContain('is-collapsed')
    expect(wrapper.emitted('collapse')?.at(-1)).toEqual([true])
    // The row stays mounted — hidden, but still measurable.
    expect(wrapper.findAll('.wx-actions__row .wx-action')).toHaveLength(3)
    expect(wrapper.find('.wx-actions__menu .wx-action').attributes('aria-label')).toBe('More')
  })

  it('comes back out of the menu when there is room again', async () => {
    setWidths({ row: 200, available: 120 })
    const wrapper = actions({ collapse: true })

    TestResizeObserver.fire()
    await nextTick()
    expect(wrapper.classes()).toContain('is-collapsed')

    setWidths({ row: 200, available: 400 })
    TestResizeObserver.fire()
    await nextTick()

    expect(wrapper.classes()).not.toContain('is-collapsed')
    expect(wrapper.emitted('collapse')?.at(-1)).toEqual([false])
  })

  it('ignores a container that has not been laid out', async () => {
    setWidths({ row: 200, available: 0 })
    const wrapper = actions({ collapse: true })

    TestResizeObserver.fire()
    await nextTick()

    expect(wrapper.classes()).not.toContain('is-collapsed')
  })

  it('never collapses when collapsing is off', async () => {
    setWidths({ row: 900, available: 50 })
    const wrapper = actions()

    TestResizeObserver.fire()
    await nextTick()

    expect(wrapper.classes()).not.toContain('is-collapsed')
  })
})
