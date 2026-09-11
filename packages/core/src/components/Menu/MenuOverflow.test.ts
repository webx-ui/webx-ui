import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxMenu from './Menu.vue'
import WxMenuItem from '../MenuItem/MenuItem.vue'
import WxSubmenu from '../Submenu/Submenu.vue'

const components = { WxMenu, WxMenuItem, WxSubmenu }

/** A stand-in for the browser's observer: the test decides how wide the bar is. */
let emit: ((width: number) => void) | undefined

enableAutoUnmount(afterEach)

beforeEach(() => {
  vi.stubGlobal(
    'ResizeObserver',
    class {
      constructor(private callback: ResizeObserverCallback) {
        emit = (width: number) => {
          this.callback(
            [{ contentRect: { width } } as ResizeObserverEntry],
            this as unknown as ResizeObserver,
          )
        }
      }
      observe() {}
      unobserve() {}
      disconnect() {}
    },
  )
})

afterEach(() => {
  emit = undefined
  vi.unstubAllGlobals()
})

const bar = `
  <wx-menu-item value="a" label="Alpha" />
  <wx-menu-item value="b" label="Bravo" />
  <wx-menu-item value="c" label="Charlie" />
  <wx-menu-item value="d" label="Delta" />
`

/**
 * jsdom lays nothing out, so the geometry the bar reads is supplied here: every
 * entry is 100px wide, the branch that holds the overflow is 60px, and the bar is
 * as wide as the test says.
 */
const ENTRY = 100
const BRANCH = 60

function layOut(wrapper: ReturnType<typeof mountBar>, width: number) {
  const list = wrapper.element as HTMLElement
  Object.defineProperty(list, 'clientWidth', { value: width, configurable: true })

  for (const child of [...list.children] as HTMLElement[]) {
    const isBranch = child.classList.contains('wx-menu__overflow')
    child.getBoundingClientRect = () => ({ width: isBranch ? BRANCH : ENTRY }) as unknown as DOMRect
  }
}

function mountBar(props: Record<string, unknown> = {}) {
  return mount(WxMenu, {
    props: { mode: 'horizontal', ...props },
    slots: { default: bar },
    global: { components },
  })
}

/** Lays the bar out at a width and lets the two-pass measurement settle. */
async function resize(wrapper: ReturnType<typeof mountBar>, width: number) {
  layOut(wrapper, width)
  emit?.(width)
  await nextTick()
  layOut(wrapper, width)
  await nextTick()
  await nextTick()
}

function labels(wrapper: ReturnType<typeof mountBar>) {
  return wrapper.findAll('.wx-menu > .wx-menu-item .wx-menu-row__label').map((el) => el.text())
}

function branch(wrapper: ReturnType<typeof mountBar>) {
  return wrapper.find('.wx-menu__overflow')
}

describe('WxMenu overflow', () => {
  it('keeps every entry in the bar when they all fit', async () => {
    const wrapper = mountBar()
    await resize(wrapper, 4 * ENTRY + BRANCH)

    expect(labels(wrapper)).toEqual(['Alpha', 'Bravo', 'Charlie', 'Delta'])
    expect(branch(wrapper).attributes('hidden')).toBeDefined()
  })

  it('moves what does not fit into the branch at the end', async () => {
    const wrapper = mountBar()
    /* Room for two entries once the branch has taken its own 60px out of 260. */
    await resize(wrapper, 260)

    expect(labels(wrapper)).toEqual(['Alpha', 'Bravo'])
    expect(branch(wrapper).attributes('hidden')).toBeUndefined()
  })

  it('renders a moved entry once, in the branch rather than the bar', async () => {
    const wrapper = mountBar()
    await resize(wrapper, 260)

    expect(wrapper.findAll('.wx-menu > .wx-menu-item')).toHaveLength(2)

    await branch(wrapper).get('button.wx-menu-row').trigger('click')
    await nextTick()

    /* The panel is teleported, so what it holds is read from the document. */
    const moved = [...document.querySelectorAll('.wx-menu-flyout .wx-menu-row__label')]
    expect(moved.map((el) => el.textContent)).toEqual(['Charlie', 'Delta'])
  })

  it('gives the entries back as the bar grows', async () => {
    const wrapper = mountBar()
    await resize(wrapper, 260)
    expect(labels(wrapper)).toHaveLength(2)

    await resize(wrapper, 4 * ENTRY)

    expect(labels(wrapper)).toEqual(['Alpha', 'Bravo', 'Charlie', 'Delta'])
  })

  it('scrolls instead, when asked to', async () => {
    const wrapper = mountBar({ overflow: 'scroll' })
    await resize(wrapper, 120)

    expect(branch(wrapper).exists()).toBe(false)
    expect(labels(wrapper)).toHaveLength(4)
  })

  it('leaves a vertical menu alone', async () => {
    const wrapper = mountBar({ mode: 'vertical' })
    await resize(wrapper, 120)

    expect(branch(wrapper).exists()).toBe(false)
    expect(labels(wrapper)).toHaveLength(4)
  })

  it('marks the branch as holding the page you are on', async () => {
    const wrapper = mountBar({ modelValue: 'd' })
    /* `Delta` moves into the branch, so the branch becomes the trail to it. */
    await resize(wrapper, 260)

    expect(branch(wrapper).find('.wx-menu-row').classes()).toContain('is-trail')
  })
})
