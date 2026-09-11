import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WxMenu from './Menu.vue'
import WxMenuItem from '../MenuItem/MenuItem.vue'
import WxMenuGroup from '../MenuGroup/MenuGroup.vue'
import WxSubmenu from '../Submenu/Submenu.vue'

const components = { WxMenu, WxMenuItem, WxMenuGroup, WxSubmenu }

const tree = `
  <wx-menu-item value="dashboard" icon="home" label="Dashboard" href="#dashboard" />
  <wx-submenu value="content" title="Content">
    <wx-menu-item value="posts" label="Posts" href="#posts" />
    <wx-submenu value="taxonomy" title="Taxonomy">
      <wx-menu-item value="tags" label="Tags" href="#tags" />
    </wx-submenu>
  </wx-submenu>
  <wx-menu-item value="settings" label="Settings" disabled />
`

function mountMenu(props: Record<string, unknown> = {}, slot = tree) {
  return mount(WxMenu, { props, slots: { default: slot }, global: { components } })
}

describe('WxMenu', () => {
  it('renders a vertical list of entries', () => {
    const wrapper = mountMenu({ label: 'Main navigation' })

    expect(wrapper.element.tagName).toBe('UL')
    expect(wrapper.attributes('aria-label')).toBe('Main navigation')
    expect(wrapper.classes()).toContain('wx-menu--vertical')
    expect(wrapper.findAll('.wx-menu-item').length).toBeGreaterThan(0)
  })

  it('selects an entry and reports the value', async () => {
    const wrapper = mountMenu()

    await wrapper.get('a[href="#posts"]').trigger('click')

    expect(wrapper.emitted('select')?.[0]?.[0]).toBe('posts')
    expect(wrapper.emitted('update:modelValue')).toEqual([['posts']])
  })

  it('marks the active entry and the branch above it', async () => {
    const wrapper = mountMenu({ modelValue: 'tags' })
    await wrapper.vm.$nextTick()

    expect(wrapper.get('a[href="#tags"]').classes()).toContain('is-active')
    expect(wrapper.get('a[href="#tags"]').attributes('aria-current')).toBe('page')

    // Both submenus above it were opened by `autoExpand`.
    expect(wrapper.emitted('update:open')?.at(-1)?.[0]).toEqual(['content', 'taxonomy'])
  })

  it('leaves the branch shut when autoExpand is off', async () => {
    const wrapper = mountMenu({ modelValue: 'tags', autoExpand: false })
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:open')).toBeUndefined()
  })

  it('opens and closes a branch from its trigger', async () => {
    const wrapper = mountMenu({ open: [] })
    const trigger = wrapper.get('button.wx-menu-row')

    expect(trigger.attributes('aria-expanded')).toBe('false')

    await trigger.trigger('click')
    expect(wrapper.emitted('update:open')?.at(-1)?.[0]).toEqual(['content'])
  })

  it('keeps one branch open at a time in accordion mode', async () => {
    const wrapper = mountMenu({ accordion: true, open: ['other'] })

    await wrapper.get('button.wx-menu-row').trigger('click')

    expect(wrapper.emitted('update:open')?.at(-1)?.[0]).toEqual(['content'])
  })

  it('ignores clicks on a disabled entry', async () => {
    const wrapper = mountMenu()

    await wrapper.get('button[disabled]').trigger('click')

    expect(wrapper.emitted('select')).toBeUndefined()
  })

  it('collapses to an icon rail only when vertical', () => {
    expect(mountMenu({ collapsed: true }).classes()).toContain('wx-menu--collapsed')
    expect(mountMenu({ collapsed: true, mode: 'horizontal' }).classes()).not.toContain(
      'wx-menu--collapsed',
    )
  })

  it('labels a group and indents nothing under it', () => {
    const wrapper = mountMenu(
      {},
      `<wx-menu-group title="Library">
         <wx-menu-item value="media" label="Media" />
       </wx-menu-group>`,
    )

    expect(wrapper.get('.wx-menu-group__title').text()).toBe('Library')
    expect(wrapper.get('.wx-menu-row').attributes('style')).toContain('--wx-menu-depth: 0')
  })

  it('indents an entry by the submenus above it', async () => {
    const wrapper = mountMenu({ open: ['content', 'taxonomy'] })

    expect(wrapper.get('a[href="#posts"]').attributes('style')).toContain('--wx-menu-depth: 1')
    expect(wrapper.get('a[href="#tags"]').attributes('style')).toContain('--wx-menu-depth: 2')
  })
})

describe('WxSubmenu', () => {
  it('opens inline in a sidebar', () => {
    const wrapper = mountMenu({ open: ['content'] })

    expect(wrapper.get('.wx-submenu__panel').classes()).toContain('is-open')
    expect(wrapper.get('button.wx-menu-row').attributes('aria-expanded')).toBe('true')
  })

  it('holds the closed branch out of the tab order', () => {
    const wrapper = mountMenu({ open: [] })

    expect(wrapper.get('.wx-submenu__panel').attributes('inert')).toBeDefined()
  })

  it('opens as a flyout in a bar', () => {
    const wrapper = mountMenu({ mode: 'horizontal' })

    expect(wrapper.find('.wx-submenu__panel').exists()).toBe(false)
    expect(wrapper.findComponent({ name: 'WxDropdown' }).props('side')).toBe('bottom')
  })

  it('keeps a flyout out of the open model', async () => {
    const wrapper = mountMenu({ mode: 'horizontal', open: [] })

    await wrapper.get('button.wx-menu-row').trigger('click')

    expect(wrapper.findComponent({ name: 'WxDropdown' }).props('open')).toBe(true)
    // A flyout belongs to the pointer, so it never joins `v-model:open`.
    expect(wrapper.emitted('update:open')).toBeUndefined()
  })

  it('opens the flyout beside the rail when collapsed', () => {
    const wrapper = mountMenu({ collapsed: true })

    expect(wrapper.findComponent({ name: 'WxDropdown' }).props('side')).toBe('right')
  })
})
