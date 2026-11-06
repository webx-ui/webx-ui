import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { mount } from '@vue/test-utils'
import { WxScreenRenderer, type ScreenNode } from '@webx-ui/schema'
import { describe, expect, it } from 'vitest'
import { catalogFeedsMessages } from './messages'

const here = dirname(fileURLToPath(import.meta.url))

const screen = JSON.parse(
  readFileSync(
    resolve(here, '../../../php/packages/module-catalog-feeds/resources/screens/feed-form.json'),
    'utf8',
  ),
) as { root: ScreenNode[] }

function find(nodes: ScreenNode[], id: string): ScreenNode | undefined {
  for (const node of nodes) {
    if (node.id === id) return node

    const inside = find(node.children ?? [], id)
    if (inside) return inside
  }

  return undefined
}

const warning = find(screen.root, 'format-csv-warning')

describe('the warning about formulas in a CSV feed', () => {
  function form(format: string) {
    return mount(WxScreenRenderer, {
      props: { root: [warning!], modelValue: { format } },
      attachTo: document.body,
    })
  }

  it('stands on the form, in words the dictionary has', () => {
    expect(warning?.type).toBe('wx-alert')
    expect(warning?.props?.description).toBe('trans::webx-catalog-feeds::feed.format-csv-warning')
    expect(catalogFeedsMessages.feed?.['format-csv-warning']).toContain('formula')
  })

  it('shows on a feed that is CSV, saved or not', () => {
    expect(form('csv').find('.wx-alert').exists()).toBe(true)
  })

  it.each(['xml', 'json', 'text'])('stays away from %s', (format) => {
    expect(form(format).find('.wx-alert').exists()).toBe(false)
  })

  it('follows the select both ways', async () => {
    const wrapper = form('xml')

    await wrapper.setProps({ modelValue: { format: 'csv' } })
    expect(wrapper.find('.wx-alert').exists()).toBe(true)

    await wrapper.setProps({ modelValue: { format: 'xml' } })
    expect(wrapper.find('.wx-alert').exists()).toBe(false)
  })
})
