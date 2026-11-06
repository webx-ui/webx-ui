import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { feedEditorKey, type FeedEditorContext } from './editor'
import FeedTemplateField from './FeedTemplateField.vue'
import type { FeedDriver, FeedsMeta, FeedValues } from './types'

const LINES: FeedDriver = {
  key: 'lines',
  label: 'Marketplace lines',
  class: 'App\\Feeds\\LinesFormat',
  extension: 'lines',
  content_type: 'application/x-lines',
}

function field(driver: string, drivers: FeedDriver[] | null) {
  const context = {
    locked: ref(false),
    values: ref({ driver, format: 'text' } as FeedValues),
    variables: ref(null),
    errors: ref({}),
    meta: ref(drivers === null ? null : ({ drivers } as FeedsMeta)),
  } as unknown as FeedEditorContext

  return mount(FeedTemplateField, {
    global: { provide: { [feedEditorKey as symbol]: context } },
  })
}

describe('FeedTemplateField of a feed written by a class', () => {
  it('names the class, and there is no editor and no preview', () => {
    const wrapper = field('lines', [LINES])

    expect(wrapper.text()).toContain('Marketplace lines')
    expect(wrapper.find('.wx-catalog-feed-template__class').text()).toBe('App\\Feeds\\LinesFormat')
    expect(wrapper.find('.wx-catalog-feed-template__layout').exists()).toBe(false)
    expect(wrapper.find('.wx-alert').exists()).toBe(false)
  })

  it('says so when the class has left the site', () => {
    const wrapper = field('lines', [])

    expect(wrapper.find('.wx-alert').exists()).toBe(true)
    expect(wrapper.find('.wx-catalog-feed-template__class').exists()).toBe(false)
  })

  it('calls nothing gone before the formats are known', () => {
    const wrapper = field('lines', null)

    expect(wrapper.find('.wx-alert').exists()).toBe(false)
    expect(wrapper.text()).toContain('lines')
  })
})
