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
        'contacts.phone': '+44 20 7946 0958',
        'contacts.address': {
          ru: '',
          en: '1 High Street,\n   Westminster, London SW1A 1AA, United Kingdom',
        },
      },
      'ru',
    )

    expect(options).toEqual([
      {
        value: 'contacts.phone',
        label: 'Contacts → Phone',
        description: 'contacts.phone · +44 20 7946 0958',
      },
      {
        value: 'contacts.address',
        label: 'Contacts → Address',
        description: 'contacts.address · 1 High Street, Westminster, London SW1A…',
      },
    ])
  })

  it('shows only the key of a setting that holds nothing yet', () => {
    expect(settingOptions(root, {}, 'en')[0]?.description).toBe('contacts.phone')
  })
})
