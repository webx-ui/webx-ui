// @vitest-environment node
import { describe, expect, it } from 'vitest'
import type { ScreenNode } from '@webx-ui/schema'
import { settingOptions } from './settingOptions'

const root: ScreenNode[] = [
  {
    id: 'tabs',
    type: 'wx-tabs',
    children: [
      {
        id: 'contacts',
        type: 'wx-tab',
        label: 'Contacts',
        children: [
          {
            id: 'card',
            type: 'wx-card',
            children: [
              { id: 'phone', type: 'wx-input', name: 'contacts.phone', label: 'Phone' },
              { id: 'address', type: 'wx-textarea', name: 'contacts.address', label: 'Address' },
              { id: 'logo', type: 'wx-media', name: 'contacts.logo', label: 'Logo' },
            ],
          },
        ],
      },
      {
        id: 'shortcodes',
        type: 'wx-tab',
        label: 'Shortcodes',
        children: [
          {
            id: 'list',
            type: 'wx-repeater',
            name: 'shortcodes.data',
            children: [{ id: 'name', type: 'wx-input', name: 'name', label: 'Name' }],
          },
        ],
      },
    ],
  },
]

describe('settingOptions', () => {
  it('offers the text settings, labelled where they live, with the key and what they hold', () => {
    const options = settingOptions(
      root,
      {
        'contacts.phone': '+800 1234 5678',
        'contacts.address': {
          ru: '',
          en: '1 High Street,\n   Old Town, Sample City 12345, Sample Country',
        },
      },
      'ru',
    )

    expect(options).toEqual([
      {
        value: 'contacts.phone',
        label: 'Contacts → Phone',
        description: 'contacts.phone · +800 1234 5678',
      },
      {
        value: 'contacts.address',
        label: 'Contacts → Address',
        description: 'contacts.address · 1 High Street, Old Town, Sample City 12…',
      },
    ])
  })

  it('shows only the key of a setting that holds nothing yet', () => {
    expect(settingOptions(root, {}, 'en')[0]?.description).toBe('contacts.phone')
  })

  it('does not offer a setting hidden for good', () => {
    const hidden: ScreenNode[] = [
      {
        id: 'card',
        type: 'wx-card',
        children: [
          { id: 'phone', type: 'wx-input', name: 'contacts.phone', label: 'Phone' },
          {
            id: 'hours-card',
            type: 'wx-card',
            visible: false,
            children: [{ id: 'tz', type: 'wx-input', name: 'contacts.timezone', label: 'Zone' }],
          },
          { id: 'fax', type: 'wx-input', name: 'contacts.fax', label: 'Fax', visible: false },
        ],
      },
    ]

    expect(settingOptions(hidden, {}, 'en').map((option) => option.value)).toEqual([
      'contacts.phone',
    ])
  })
})
