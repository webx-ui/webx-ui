import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import BlockDeclaredCard from './BlockDeclaredCard.vue'
import type { DeclaredComponent } from './types'

const declared: DeclaredComponent = {
  slug: 'recipe-card',
  module: 'recipes',
  title: 'Recipe card',
  description: 'One recipe in a list.',
  fallback: 'webx-recipes::partials.card',
  customised: false,
}

/** The panel's dictionary: the English this package seeds, not the keys. */
function panel() {
  const i18n = createI18n()
  const admin = { i18n } as unknown as AdminContext

  return { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n }
}

describe('WxBlockDeclaredCard', () => {
  it('says whose view the place is and asks to customise it', async () => {
    const wrapper = mount(BlockDeclaredCard, {
      props: { declared, module: 'Recipes', canManage: true },
      global: { provide: panel() },
    })

    expect(wrapper.text()).toContain('recipe-card')
    expect(wrapper.text()).toContain('Standard view · module Recipes')
    expect(wrapper.text()).toContain('One recipe in a list.')

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('customise')).toEqual([[declared]])
  })

  it('offers nothing to a person who cannot manage blocks', () => {
    const wrapper = mount(BlockDeclaredCard, {
      props: { declared, module: 'Recipes' },
      global: { provide: panel() },
    })

    expect(wrapper.find('button').exists()).toBe(false)
  })
})
