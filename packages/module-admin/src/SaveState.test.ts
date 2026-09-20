import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SaveState from './SaveState.vue'

/**
 * The mark is about the save that just happened, not about the state of the work.
 *
 * Which is the whole difference between this and the word it replaces: a screen opens on saved
 * work every time, and a tick shown for that is a tick nobody asked for. So what is pinned down
 * here is the timing — that it lights on the way out of `saving` and goes out on its own.
 */
function mark(wrapper: ReturnType<typeof mount>): string | null {
  const icon = wrapper.find('svg')

  return icon.exists() ? (icon.attributes('aria-label') ?? '') : null
}

describe('WxSaveState', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('shows nothing for work that is simply saved, or simply not', async () => {
    const wrapper = mount(SaveState, { props: { state: 'saved' } })

    expect(mark(wrapper)).toBeNull()

    await wrapper.setProps({ state: 'unsaved' })
    expect(mark(wrapper)).toBeNull()
  })

  it('turns a wheel while the save is in flight and ticks when it lands', async () => {
    const wrapper = mount(SaveState, { props: { state: 'unsaved', linger: 2000 } })

    await wrapper.setProps({ state: 'saving' })
    expect(wrapper.find('svg').classes()).toContain('wx-icon--spin')

    await wrapper.setProps({ state: 'saved' })
    expect(wrapper.find('svg').classes()).toContain('is-saved')

    /* And goes out by itself: nothing on this screen will come back to put it away. */
    vi.advanceTimersByTime(2000)
    await wrapper.vm.$nextTick()
    expect(mark(wrapper)).toBeNull()
  })

  it('keeps its place while it is empty', () => {
    const wrapper = mount(SaveState, { props: { state: 'saved' } })

    /* The bar lays the state out beside the buttons; a box that comes and goes moves them. */
    expect(wrapper.find('.wx-save-state').exists()).toBe(true)
  })
})
