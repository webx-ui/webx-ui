import { afterEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxCascader from './Cascader.vue'
import type { CascaderOption } from './types'

// The panel is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

const options: CascaderOption[] = [
  {
    label: 'Guide',
    value: 'guide',
    children: [
      { label: 'Disciplines', value: 'disciplines' },
      {
        label: 'Navigation',
        value: 'navigation',
        children: [
          { label: 'Side Navigation', value: 'side' },
          { label: 'Top Navigation', value: 'top' },
        ],
      },
    ],
  },
  { label: 'Component', value: 'component', children: [{ label: 'Input', value: 'input' }] },
  { label: 'Resource', value: 'resource', disabled: true },
]

function factory(props: Record<string, unknown> = {}) {
  return mount(WxCascader, { props: { options, ...props }, attachTo: document.body })
}

function columns() {
  return [...document.querySelectorAll('.wx-cascader__column')]
}

function optionsIn(level: number) {
  return [...(columns()[level]?.querySelectorAll('.wx-cascader__option') ?? [])]
}

async function openPanel(wrapper: ReturnType<typeof factory>) {
  await wrapper.get('.wx-cascader__trigger').trigger('click')
  await nextTick()
}

describe('WxCascader', () => {
  it('shows the placeholder until something is chosen', () => {
    const wrapper = factory({ placeholder: 'Pick a section' })

    expect(wrapper.get('.wx-cascader__placeholder').text()).toBe('Pick a section')
  })

  it('treats an empty path as nothing picked', () => {
    const wrapper = factory({ modelValue: [], placeholder: 'Pick a section' })

    expect(wrapper.get('.wx-cascader__placeholder').text()).toBe('Pick a section')
    expect(wrapper.find('.wx-cascader__value').exists()).toBe(false)
  })

  it('shows the whole path of the current value', () => {
    const wrapper = factory({ modelValue: ['guide', 'navigation', 'side'] })

    expect(wrapper.get('.wx-cascader__value').text()).toBe('Guide / Navigation / Side Navigation')
  })

  it('can show the last level alone', () => {
    const wrapper = factory({
      modelValue: ['guide', 'navigation', 'side'],
      showAllLevels: false,
    })

    expect(wrapper.get('.wx-cascader__value').text()).toBe('Side Navigation')
  })

  it('opens one column and adds the next as a node is opened', async () => {
    const wrapper = factory()
    await openPanel(wrapper)

    expect(columns()).toHaveLength(1)
    expect(optionsIn(0).map((node) => node.textContent?.trim())).toEqual([
      'Guide',
      'Component',
      'Resource',
    ])

    await optionsIn(0)[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(columns()).toHaveLength(2)
    expect(optionsIn(1).map((node) => node.textContent?.trim())).toEqual([
      'Disciplines',
      'Navigation',
    ])
    expect(wrapper.emitted('expand')).toHaveLength(1)
  })

  it('does not pick a parent while only leaves may be chosen', async () => {
    const wrapper = factory()
    await openPanel(wrapper)

    optionsIn(0)[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('emits the path of a picked leaf and closes', async () => {
    const wrapper = factory()
    await openPanel(wrapper)

    optionsIn(0)[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    optionsIn(1)[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['guide', 'disciplines']])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([['guide', 'disciplines']])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('emits the last value alone when emit-path is off', async () => {
    const wrapper = factory({ emitPath: false })
    await openPanel(wrapper)

    optionsIn(0)[1].dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    optionsIn(1)[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['input'])
  })

  it('finds the path of a bare value, so the field still reads as a path', () => {
    const wrapper = factory({ modelValue: 'top', emitPath: false })

    expect(wrapper.get('.wx-cascader__value').text()).toBe('Guide / Navigation / Top Navigation')
  })

  it('lets a parent be picked with check-strictly, and keeps the panel open', async () => {
    const wrapper = factory({ checkStrictly: true })
    await openPanel(wrapper)

    optionsIn(0)[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['guide']])
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('opens the next level on hover when asked to', async () => {
    const wrapper = factory({ expandTrigger: 'hover' })
    await openPanel(wrapper)

    optionsIn(0)[0].dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }))
    await nextTick()

    expect(columns()).toHaveLength(2)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('ignores a disabled option', async () => {
    const wrapper = factory()
    await openPanel(wrapper)

    const disabled = optionsIn(0)[2] as HTMLButtonElement
    expect(disabled.disabled).toBe(true)

    disabled.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()

    expect(columns()).toHaveLength(1)
  })

  it('clears the value', async () => {
    const wrapper = factory({ modelValue: ['guide', 'disciplines'], clearable: true })

    await wrapper.get('.wx-cascader__clear').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[]])
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })

  it('reopens on the branch that is currently selected', async () => {
    const wrapper = factory({ modelValue: ['guide', 'navigation', 'side'] })
    await openPanel(wrapper)

    // Guide and Navigation are open, so the chosen leaf is on screen.
    expect(columns()).toHaveLength(3)
    expect(optionsIn(2).map((node) => node.textContent?.trim())).toEqual([
      'Side Navigation',
      'Top Navigation',
    ])
  })

  it('walks the columns with the arrow keys', async () => {
    const wrapper = factory()
    await openPanel(wrapper)

    const panel = document.querySelector('.wx-cascader__panel') as HTMLElement
    const press = (key: string, from: Element = document.activeElement ?? panel) =>
      from.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }))

    // The panel holds focus when it opens; the first arrow steps into the list.
    press('ArrowDown', panel)
    expect(document.activeElement?.textContent?.trim()).toBe('Guide')

    press('ArrowDown')
    expect(document.activeElement?.textContent?.trim()).toBe('Component')

    press('ArrowUp')
    expect(document.activeElement?.textContent?.trim()).toBe('Guide')

    press('ArrowRight')
    await nextTick()
    expect(columns()).toHaveLength(2)
    expect(document.activeElement?.textContent?.trim()).toBe('Disciplines')

    press('ArrowLeft')
    await nextTick()
    expect(document.activeElement?.textContent?.trim()).toBe('Guide')
  })

  it('posts the value through a hidden input when it is named', () => {
    const wrapper = factory({ modelValue: ['guide', 'disciplines'], name: 'section' })

    expect(wrapper.get('input[type="hidden"]').attributes('value')).toBe('guide,disciplines')
  })

  describe('lazy trees', () => {
    const load = vi.fn(async (option: CascaderOption | null) => {
      if (!option) return [{ label: 'Kyiv region', value: 'kyiv' }]
      if (option.value === 'kyiv') return [{ label: 'Bucha', value: 'bucha', leaf: true }]
      return []
    })

    it('asks for the root level on open, then for each node opened', async () => {
      const wrapper = mount(WxCascader, {
        props: { lazy: true, load, options: [] },
        attachTo: document.body,
      })

      await openPanel(wrapper)
      await nextTick()

      expect(load).toHaveBeenCalledWith(null, [])
      expect(optionsIn(0).map((node) => node.textContent?.trim())).toEqual(['Kyiv region'])

      optionsIn(0)[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))
      await nextTick()
      await nextTick()

      expect(load).toHaveBeenCalledTimes(2)
      expect(optionsIn(1).map((node) => node.textContent?.trim())).toEqual(['Bucha'])
      // The loaded node was not treated as a leaf, so nothing was selected by opening it.
      expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    })
  })
})
