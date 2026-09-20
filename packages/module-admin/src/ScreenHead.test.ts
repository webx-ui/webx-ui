import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { WebxUI } from '@webx-ui/core'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import ScreenHead from './ScreenHead.vue'
import type { ScreenAction } from './types'

const actions: ScreenAction[] = [
  { key: 'save', label: 'Save', run: () => {} },
  { key: 'publish', label: 'Publish', primary: true, icon: 'check', run: () => {} },
  { key: 'delete', label: 'Delete', danger: true, run: () => {} },
]

function draw(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  const i18n = createI18n({ locale: 'en' })
  i18n.defaults('webx-admin', adminMessages)

  return mount(ScreenHead, {
    props: { title: 'About us', ...props },
    slots,
    global: {
      plugins: [WebxUI],
      provide: { [i18nKey as symbol]: i18n },
      stubs: { RouterLink: true },
    },
    attachTo: document.body,
  })
}

/**
 * jsdom has no layout, so the head measures 0 and reads that as "not measured yet" — which is
 * the roomy case. A width has to be handed to it for the narrow one, and only the split between
 * buttons and the menu is worth asserting there: how it looks at 375px is a question for a
 * browser (CLAUDE.md §4).
 */
function measure(width: number) {
  return vi.spyOn(Element.prototype, 'getBoundingClientRect').mockReturnValue({
    width,
    height: 0,
    top: 0,
    left: 0,
    right: width,
    bottom: 0,
    x: 0,
    y: 0,
    toJSON: () => ({}),
  } as DOMRect)
}

function labels(): string[] {
  return [...document.querySelectorAll('.wx-dropdown-item')].map(
    (item) => item.textContent?.trim() ?? '',
  )
}

async function openMenu(wrapper: ReturnType<typeof draw>): Promise<void> {
  await wrapper.get('.wx-actions__menu button').trigger('click')
  await nextTick()
}

afterEach(() => {
  vi.restoreAllMocks()
  document.body.innerHTML = ''
})

describe('WxScreenHead', () => {
  it('says the name and, under it, which record this is', () => {
    const wrapper = draw({ subtitle: 'about-us · 3 pages' })

    expect(wrapper.get('.wx-screen-head__title').text()).toBe('About us')
    expect(wrapper.get('.wx-screen-head__subtitle').text()).toBe('about-us · 3 pages')

    wrapper.unmount()
  })

  it('draws what it was given as buttons while there is room for them', () => {
    const wrapper = draw({ actions })

    expect(wrapper.findAll('.wx-screen-head__button').map((button) => button.text())).toEqual([
      'Save',
      'Publish',
    ])

    wrapper.unmount()
  })

  it('never makes a button of the destructive action', async () => {
    const wrapper = draw({ actions })

    await openMenu(wrapper)

    expect(labels()).toEqual(['Delete'])

    wrapper.unmount()
  })

  it('keeps an action asked for the menu out of the row at any width', async () => {
    const wrapper = draw({
      actions: [{ key: 'export', label: 'Export', menu: true }, ...actions],
    })

    await openMenu(wrapper)

    expect(labels()).toEqual(['Export', 'Delete'])

    wrapper.unmount()
  })

  it('leaves only the main action on a narrow screen and folds the rest behind the ···', async () => {
    measure(375)

    const wrapper = draw({ actions })

    await nextTick()

    expect(wrapper.findAll('.wx-screen-head__button').map((button) => button.text())).toEqual([
      'Publish',
    ])

    await openMenu(wrapper)

    expect(labels()).toEqual(['Save', 'Delete'])

    wrapper.unmount()
  })

  it('folds everything when no action is the one the screen exists for', async () => {
    measure(375)

    const wrapper = draw({ actions: [{ key: 'save', label: 'Save' }] })

    await nextTick()

    expect(wrapper.findAll('.wx-screen-head__button')).toHaveLength(0)
    expect(wrapper.find('.wx-actions__menu').exists()).toBe(true)

    wrapper.unmount()
  })

  it('draws an icon on the button rather than handing it to the element as an attribute', () => {
    const wrapper = draw({ actions: [{ key: 'new', label: 'New', icon: 'plus', primary: true }] })

    expect(wrapper.find('.wx-button__icon svg').exists()).toBe(true)

    wrapper.unmount()
  })

  it('runs the action it was handed', async () => {
    const run = vi.fn()
    const wrapper = draw({ actions: [{ key: 'new', label: 'New', primary: true, run }] })

    await wrapper.get('.wx-screen-head__button').trigger('click')

    expect(run).toHaveBeenCalledOnce()

    wrapper.unmount()
  })

  it('asks the screen to go back when the way out is an event rather than a route', async () => {
    // The class rides the button itself: `WxAction` carries a tooltip beside it, so its root
    // is a fragment and the class lands on the control rather than on a wrapper (CLAUDE.md §4).
    const wrapper = draw({ back: true })

    await wrapper.get('.wx-screen-head__back').trigger('click')

    expect(wrapper.emitted('back')).toHaveLength(1)

    wrapper.unmount()
  })

  it('gives the trail a line of its own above the name', () => {
    // Its own box because it scrolls sideways in it, which is a thing a browser has to be asked
    // about and jsdom cannot answer — what is checked here is that the box is there at all.
    const wrapper = draw({}, { trail: '<nav class="crumbs">Pages / About</nav>' })

    expect(wrapper.classes()).toContain('has-trail')
    expect(wrapper.get('.wx-screen-head__trail .crumbs').text()).toBe('Pages / About')

    wrapper.unmount()
  })

  it('draws no row of actions when a screen offers none', () => {
    const wrapper = draw()

    expect(wrapper.find('.wx-screen-head__actions').exists()).toBe(false)

    wrapper.unmount()
  })
})
