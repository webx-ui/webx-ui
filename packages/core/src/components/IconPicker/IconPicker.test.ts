import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxIconPicker from './IconPicker.vue'

// Teleported panels outlive their wrapper otherwise, and leak into the next test.
enableAutoUnmount(afterEach)

function mountPicker(props: Record<string, unknown> = {}) {
  return mount(WxIconPicker, { props, attachTo: document.body })
}

const options = () => document.querySelectorAll<HTMLElement>('.wx-icon-picker__option')

describe('WxIconPicker', () => {
  it('shows the chosen icon and its name', () => {
    const wrapper = mountPicker({ modelValue: 'search' })

    expect(wrapper.get('.wx-icon-picker__preview').classes()).not.toContain('is-empty')
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('search')
  })

  it('marks an empty field rather than drawing nothing in silence', () => {
    const wrapper = mountPicker({ modelValue: null })

    expect(wrapper.get('.wx-icon-picker__preview').classes()).toContain('is-empty')
  })

  /* Picking cannot produce a name the set does not have, but an import or an older field can,
     and "draws nothing" must not look the same as "nothing chosen yet". */
  it('marks a name the set does not have', () => {
    const wrapper = mountPicker({ modelValue: 'map-pin-outline', unknownText: 'Not in the set' })
    const preview = wrapper.get('.wx-icon-picker__preview')

    expect(preview.classes()).toContain('is-unknown')
    expect(preview.attributes('title')).toBe('Not in the set')
  })

  it('opens on focus with the whole set in it', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').trigger('focus')
    // The panel opens a turn later, so that the click that focused the field cannot dismiss it.
    await new Promise((resolve) => setTimeout(resolve))
    await nextTick()

    expect(options().length).toBeGreaterThan(100)
  })

  it('filters by what is typed', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('trash')

    const names = [...options()].map((option) => option.getAttribute('title'))

    expect(names).toContain('trash')
    expect(names).not.toContain('search')
  })

  /* The point of the whole component: a name the set does not have never becomes the value. */
  it('takes a name only by picking one', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('not-an-icon')

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(document.querySelector('.wx-icon-picker__empty')).not.toBeNull()
  })

  it('chooses the one match on Enter', async () => {
    const wrapper = mountPicker({ modelValue: null })

    await wrapper.get('input').setValue('trash')
    await wrapper.get('input').trigger('keydown.enter')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['trash'])
    expect(wrapper.emitted('change')?.at(-1)).toEqual(['trash'])
  })

  it('empties the field when asked', async () => {
    const wrapper = mountPicker({ modelValue: 'search', clearable: true })

    await wrapper.get('.wx-icon-picker__clear').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null])
  })

  it('has no clear button with nothing to clear', () => {
    const wrapper = mountPicker({ modelValue: null, clearable: true })

    expect(wrapper.find('.wx-icon-picker__clear').exists()).toBe(false)
  })

  describe('with a set of its own', () => {
    const theme = {
      icons: ['area', 'bath', 'bed', 'terrace'],
      sprite: '/themes/icons/abc/icons.svg',
    }

    async function openPicker(props: Record<string, unknown>) {
      const wrapper = mountPicker(props)

      await wrapper.get('input').trigger('focus')
      await new Promise((resolve) => setTimeout(resolve))
      await nextTick()

      return wrapper
    }

    it("offers its names and none of the panel's", async () => {
      await openPicker({ modelValue: null, ...theme })

      expect([...options()].map((option) => option.getAttribute('title'))).toEqual(theme.icons)
    })

    /* The theme's icons are not registered into the panel's set, so the sprite draws them. */
    it('draws each name from the sprite', async () => {
      const wrapper = await openPicker({ modelValue: 'bed', ...theme })

      expect(wrapper.get('.wx-icon-picker__preview use').attributes('href')).toBe(
        '/themes/icons/abc/icons.svg#bed',
      )
      expect(
        [...document.querySelectorAll('.wx-icon-picker__option use')].map((use) =>
          use.getAttribute('href'),
        ),
      ).toContain('/themes/icons/abc/icons.svg#terrace')
      expect(document.querySelector('.wx-icon-picker__option .wx-icon')).toBeNull()
    })

    it('searches only its names', async () => {
      const wrapper = mountPicker({ modelValue: null, ...theme })

      await wrapper.get('input').setValue('ba')

      expect([...options()].map((option) => option.getAttribute('title'))).toEqual(['bath'])
    })

    /* A panel icon is not in a theme's set, however well the panel draws it. */
    it('marks a name outside it as unknown', () => {
      const wrapper = mountPicker({ modelValue: 'search', ...theme })

      expect(wrapper.get('.wx-icon-picker__preview').classes()).toContain('is-unknown')
    })

    it("draws a list without a sprite with the panel's icons", async () => {
      const wrapper = await openPicker({ modelValue: 'trash', icons: ['search', 'trash'] })

      expect(options()).toHaveLength(2)
      expect(wrapper.find('.wx-icon-picker__preview use').exists()).toBe(false)
      expect(wrapper.get('.wx-icon-picker__preview').classes()).not.toContain('is-unknown')
    })
  })
})
