import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AuditDetails from './AuditDetails.vue'

describe('AuditDetails', () => {
  it('draws every cell by its type', () => {
    const wrapper = mount(AuditDetails, {
      props: {
        details: {
          summary: 'Stand dev.shop.com in “Delivery”, field body.',
          table: {
            columns: [
              { key: 'url', label: 'Address', type: 'url' },
              { key: 'status', label: 'Code', type: 'status' },
              { key: 'published', label: 'On the site', type: 'bool' },
              { key: 'value', label: 'Value', type: 'missing' },
              { key: 'field', label: 'Field', type: 'text' },
            ],
            rows: [
              {
                url: 'https://dev.shop.com/sale',
                status: 301,
                published: true,
                value: null,
                field: 'body',
              },
            ],
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Stand dev.shop.com')
    expect(wrapper.find('a').attributes('href')).toBe('https://dev.shop.com/sale')
    expect(wrapper.get('th').text()).toBe('Address')

    // Outside a panel there is no dictionary, so the words come out as their keys.
    const cells = wrapper.findAll('td').map((cell) => cell.text())

    expect(cells).toEqual(['https://dev.shop.com/sale', '301', 'page.yes', 'page.missing', 'body'])
  })

  it('leaves a yes-or-no cell empty when the check could not tell', () => {
    const wrapper = mount(AuditDetails, {
      props: {
        details: {
          summary: null,
          table: {
            columns: [
              { key: 'lang', label: 'Language', type: 'text' },
              { key: 'back', label: 'Links back', type: 'bool' },
            ],
            rows: [
              { lang: 'de', back: false },
              { lang: 'fr', back: null },
            ],
          },
        },
      },
    })

    expect(wrapper.findAll('td').map((cell) => cell.text())).toEqual(['de', 'page.no', 'fr', ''])
  })

  it('is only a line when the check gave no table', () => {
    const wrapper = mount(AuditDetails, {
      props: { details: { summary: 'APP_DEBUG is on.', table: null } },
    })

    expect(wrapper.text()).toBe('APP_DEBUG is on.')
    expect(wrapper.find('table').exists()).toBe(false)
  })
})
