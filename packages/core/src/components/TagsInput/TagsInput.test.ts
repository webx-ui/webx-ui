import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxTagsInput from './TagsInput.vue'

function mountTags(props: Record<string, unknown> = {}) {
  return mount(WxTagsInput, { props, attachTo: document.body })
}

function tagTexts(wrapper: ReturnType<typeof mount>) {
  return wrapper.findAll('.wx-tags-input__tag').map((tag) => tag.text().replace(/✕$/, '').trim())
}

describe('WxTagsInput', () => {
  it('renders a tag per value', () => {
    const wrapper = mountTags({ modelValue: ['news', 'guides'] })

    expect(tagTexts(wrapper)).toEqual(['news', 'guides'])
  })

  it('adds what was typed on Enter', async () => {
    const wrapper = mountTags({ modelValue: ['news'] })

    await wrapper.get('input').setValue('hotfix')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['news', 'hotfix']])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([['news', 'hotfix']])
  })

  it('trims what was typed and ignores an empty Enter', async () => {
    const wrapper = mountTags({ modelValue: [] })

    await wrapper.get('input').setValue('  spaced  ')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['spaced']])

    await wrapper.get('input').setValue('   ')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)
  })

  it('refuses a duplicate unless duplicates are allowed', async () => {
    const wrapper = mountTags({ modelValue: ['news'] })

    await wrapper.get('input').setValue('news')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('refuses to add past max', async () => {
    const wrapper = mountTags({ modelValue: ['a', 'b'], max: 2 })

    await wrapper.get('input').setValue('c')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('does not create free tags when allowCreate is off', async () => {
    const wrapper = mountTags({ modelValue: [], allowCreate: false })

    await wrapper.get('input').setValue('invented')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('arms the last tag on Backspace and removes it on the second press', async () => {
    const wrapper = mountTags({ modelValue: ['news', 'guides'] })
    const input = wrapper.get('input')

    await input.trigger('keydown', { key: 'Backspace' })
    expect(wrapper.findAll('.wx-tags-input__tag')[1].classes()).toContain('is-armed')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    await input.trigger('keydown', { key: 'Backspace' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['news']])
  })

  it('leaves the tags alone while the field still has text', async () => {
    const wrapper = mountTags({ modelValue: ['news'] })

    await wrapper.get('input').setValue('typing')
    await wrapper.get('input').trigger('keydown', { key: 'Backspace' })

    expect(wrapper.find('.wx-tags-input__tag.is-armed').exists()).toBe(false)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('removes a tag through its button', async () => {
    const wrapper = mountTags({ modelValue: ['news', 'guides'] })

    await wrapper.findAll('.wx-tags-input__remove')[0].trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['guides']])
  })

  it('reports what was typed so suggestions can come from a backend', async () => {
    const wrapper = mountTags({ modelValue: [] })

    await wrapper.get('input').setValue('gui')

    expect(wrapper.emitted('search')?.at(-1)).toEqual(['gui'])
  })

  it('offers suggestions that are not already picked', async () => {
    const wrapper = mountTags({
      modelValue: ['news'],
      suggestions: ['news', 'guides', 'releases'],
    })

    await wrapper.get('input').trigger('focus')
    await nextTick()

    expect(wrapper.findAll('.wx-tags-input__option').map((o) => o.text())).toEqual([
      'guides',
      'releases',
    ])
  })

  it('adds the highlighted suggestion on Enter instead of the typed text', async () => {
    const wrapper = mountTags({ modelValue: [], suggestions: ['guides', 'releases'] })
    const input = wrapper.get('input')

    await input.setValue('rel')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['releases']])
  })

  it('adds a suggestion that is clicked', async () => {
    const wrapper = mountTags({ modelValue: [], suggestions: ['guides'] })

    await wrapper.get('input').trigger('focus')
    await nextTick()
    await wrapper.get('.wx-tags-input__option').trigger('mousedown')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['guides']])
  })

  it('drops the remove buttons while disabled', () => {
    const wrapper = mountTags({ modelValue: ['news'], disabled: true })

    expect(wrapper.find('.wx-tags-input__remove').exists()).toBe(false)
    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
  })
})
