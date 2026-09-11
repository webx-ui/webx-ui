import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick, ref } from 'vue'
import WxTab from '../Tab/Tab.vue'
import WxTabs from './Tabs.vue'

const panes = `
  <wx-tab value="general" label="General">General settings</wx-tab>
  <wx-tab value="seo" label="SEO">Meta tags</wx-tab>
  <wx-tab value="access" label="Access" disabled>Roles</wx-tab>
`

function factory(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
  return mount(WxTabs, {
    props,
    slots: { default: panes, ...slots },
    global: { components: { WxTab } },
  })
}

function labels(wrapper: ReturnType<typeof factory>) {
  return wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())
}

/** Reka keeps a `<div role="tabpanel">` per tab; only the open one holds its content. */
function openPanel(wrapper: ReturnType<typeof factory>) {
  return wrapper.get('[role="tabpanel"][data-state="active"]')
}

describe('WxTabs', () => {
  it('builds the strip from the tabs in its slot', async () => {
    const wrapper = factory()
    await nextTick()

    expect(labels(wrapper)).toEqual(['General', 'SEO', 'Access'])
    expect(wrapper.findAll('[role="tab"]')).toHaveLength(3)
  })

  it('opens the first tab when nothing was chosen', async () => {
    const wrapper = factory()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['general'])
    expect(openPanel(wrapper).text()).toBe('General settings')
  })

  it('opens a tab once the tabs themselves arrive', async () => {
    const names = ref<string[]>([])
    const wrapper = mount({
      components: { WxTabs, WxTab },
      setup: () => ({ names }),
      template: `
        <wx-tabs>
          <wx-tab v-for="name in names" :key="name" :value="name" :label="name">{{ name }}</wx-tab>
        </wx-tabs>
      `,
    })
    await flushPromises()

    expect(wrapper.find('.wx-tabs__tab').exists()).toBe(false)

    names.value = ['seo', 'access']
    await flushPromises()

    expect(wrapper.get('.wx-tabs__tab').attributes('data-state')).toBe('active')
    expect(wrapper.get('[role="tabpanel"][data-state="active"]').text()).toBe('seo')
  })

  it('skips over a disabled tab when it picks the first one', async () => {
    const wrapper = mount(WxTabs, {
      slots: {
        default:
          '<wx-tab value="a" label="A" disabled>First</wx-tab><wx-tab value="b" label="B">Second</wx-tab>',
      },
      global: { components: { WxTab } },
    })
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['b'])
  })

  it('shows the panel of the tab that is clicked', async () => {
    const wrapper = factory({ modelValue: 'general' })
    await nextTick()

    await wrapper.findAll('.wx-tabs__tab')[1].trigger('mousedown')
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['seo'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['seo'])
  })

  it('follows v-model from the outside', async () => {
    const wrapper = factory({ modelValue: 'general' })
    await nextTick()

    await wrapper.setProps({ modelValue: 'seo' })
    await flushPromises()

    expect(openPanel(wrapper).text()).toBe('Meta tags')
  })

  it('disables the tab that says so', async () => {
    const wrapper = factory()
    await nextTick()

    const access = wrapper.findAll('.wx-tabs__tab')[2]
    expect(access.attributes('disabled')).toBeDefined()
  })

  it('empties a hidden panel, and keeps its contents when asked', async () => {
    const lazy = factory({ modelValue: 'general' })
    await flushPromises()

    const dropped = lazy.findAll('[role="tabpanel"]')[1]
    expect(dropped.attributes('hidden')).toBeDefined()
    expect(dropped.text()).toBe('')

    const kept = factory({ modelValue: 'general', keepAlive: true })
    await flushPromises()

    const survivor = kept.findAll('[role="tabpanel"]')[1]
    expect(survivor.attributes('hidden')).toBeDefined()
    expect(survivor.text()).toBe('Meta tags')
  })

  it('takes an icon, a badge and a label of its own for a tab', async () => {
    const wrapper = mount(WxTabs, {
      slots: {
        default: `
          <wx-tab value="a" label="Drafts" icon="edit" :badge="3">Drafts</wx-tab>
          <wx-tab value="b">
            <template #label><span class="custom">Live</span></template>
            Live
          </wx-tab>
        `,
      },
      global: { components: { WxTab } },
    })
    await nextTick()

    const first = wrapper.findAll('.wx-tabs__tab')[0]
    expect(first.find('svg.wx-icon').exists()).toBe(true)
    expect(first.get('.wx-badge').text()).toBe('3')
    expect(wrapper.get('.wx-tabs__tab .custom').text()).toBe('Live')
  })

  it('carries the variant, the size and the orientation on the root', async () => {
    const wrapper = factory({ variant: 'pill', size: 'sm', orientation: 'vertical' })
    await nextTick()

    expect(wrapper.classes()).toEqual(
      expect.arrayContaining([
        'wx-tabs--pill',
        'wx-tabs--sm',
        'wx-tabs--vertical',
        'wx-tabs--align-start',
      ]),
    )
  })

  it('renders the extra slot beside the strip', async () => {
    const wrapper = factory({}, { extra: '<button class="new">New</button>' })
    await nextTick()

    expect(wrapper.get('.wx-tabs__extra').text()).toBe('New')
  })

  /*
   * jsdom has no layout, so the strip is told how wide it is and how wide its content
   * is — the two numbers the component actually reads.
   */
  it('offers arrows and fades the end once the strip overflows', async () => {
    const wrapper = factory()
    await nextTick()

    expect(wrapper.find('.wx-tabs__arrow').exists()).toBe(false)

    const scroller = wrapper.get('.wx-tabs__scroller').element as HTMLElement
    Object.defineProperty(scroller, 'clientWidth', { value: 200, configurable: true })
    Object.defineProperty(scroller, 'scrollWidth', { value: 600, configurable: true })

    wrapper.vm.measure()
    await nextTick()

    expect(wrapper.get('.wx-tabs__scroller').classes()).toContain('is-overflow-end')
    expect(wrapper.get('.wx-tabs__scroller').classes()).not.toContain('is-overflow-start')

    const arrows = wrapper.findAll('.wx-tabs__arrow')
    expect(arrows).toHaveLength(2)
    // Nothing to the left yet, so that arrow is there but dead.
    expect(arrows[0].attributes('disabled')).toBeDefined()
    expect(arrows[1].attributes('disabled')).toBeUndefined()

    await arrows[1].trigger('click')

    expect(scroller.scrollLeft).toBeGreaterThan(0)
    expect(wrapper.get('.wx-tabs__scroller').classes()).toContain('is-overflow-start')
  })

  it('leaves the arrows out of the tab order — arrow keys already move between tabs', async () => {
    const wrapper = factory()
    const scroller = wrapper.get('.wx-tabs__scroller').element as HTMLElement
    Object.defineProperty(scroller, 'clientWidth', { value: 200, configurable: true })
    Object.defineProperty(scroller, 'scrollWidth', { value: 600, configurable: true })

    wrapper.vm.measure()
    await nextTick()

    const arrow = wrapper.get('.wx-tabs__arrow')
    expect(arrow.attributes('tabindex')).toBe('-1')
    expect(arrow.attributes('aria-hidden')).toBe('true')
  })

  it('opens with the active tab already in view, without sliding there', async () => {
    const wrapper = factory({ modelValue: 'access' })

    const scroller = wrapper.get('.wx-tabs__scroller').element as HTMLElement
    Object.defineProperty(scroller, 'clientWidth', { value: 200, configurable: true })
    Object.defineProperty(scroller, 'scrollWidth', { value: 600, configurable: true })

    const third = wrapper.findAll('.wx-tabs__tab')[2]
    Object.defineProperty(third.element, 'offsetLeft', { value: 400, configurable: true })
    Object.defineProperty(third.element, 'offsetWidth', { value: 100, configurable: true })

    wrapper.vm.revealActive(true)

    expect(scroller.scrollLeft).toBe(350)
    // The inline override is handed back, so later moves animate as they should.
    expect(scroller.style.scrollBehavior).toBe('')
  })

  it('scrolls the tab that becomes active into view', async () => {
    const wrapper = factory({ modelValue: 'general' })
    await nextTick()

    const scroller = wrapper.get('.wx-tabs__scroller').element as HTMLElement
    Object.defineProperty(scroller, 'clientWidth', { value: 200, configurable: true })
    Object.defineProperty(scroller, 'scrollWidth', { value: 600, configurable: true })

    const [, second] = wrapper.findAll('.wx-tabs__tab')
    Object.defineProperty(second.element, 'offsetLeft', { value: 300, configurable: true })
    Object.defineProperty(second.element, 'offsetWidth', { value: 100, configurable: true })

    await wrapper.setProps({ modelValue: 'seo' })
    await nextTick()
    await nextTick()

    // Centred: 300 - (200 - 100) / 2.
    expect(scroller.scrollLeft).toBe(250)
  })
})
