import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { parseHint, renderFile, renderItem, type FeedParts } from './preview'
import type { FeedSample } from './types'

const here = dirname(fileURLToPath(import.meta.url))

interface Case {
  name: string
  feed: FeedParts
  sample: Pick<FeedSample, 'products' | 'frame'>
  text: string
}

/** What the server answered, `sample` and `preview` of one feed: `PanelApiTest` writes and holds it. */
const cases = JSON.parse(
  readFileSync(
    resolve(here, '../../../php/packages/module-catalog-feeds/tests/fixtures/preview.json'),
    'utf8',
  ),
) as Case[]

describe('the preview comes to what the server writes', () => {
  it.each(cases.map((one) => [one.name, one] as const))('%s', (_, one) => {
    expect(renderFile(one.feed, one.sample).text).toBe(one.text)
  })
})

describe('renderFile()', () => {
  const sample = {
    products: [
      { id: 1, name: 'A', sku: null, values: { name: 'A & B' } },
      { id: 2, name: 'C', sku: null, values: { name: 'C' } },
    ],
    frame: { 'shop.name': 'Shop', products_count: '2' },
  }

  it('escapes by the format and puts the separator between products only', () => {
    const parts: FeedParts = {
      format: 'xml',
      head: '<s n="{{shop.name}}">',
      item: '<p>{{name}}</p>',
      foot: '</s>',
      separator: '\n',
    }

    expect(renderFile(parts, sample).text).toBe('<s n="Shop"><p>A &amp; B</p>\n<p>C</p></s>')
  })

  it('takes the delimiter of a csv out of its options', () => {
    const parts: FeedParts = { format: 'csv', item: '{{name}}', options: { delimiter: '&' } }

    expect(renderItem(parts, { name: 'A & B' }).text).toBe('"A & B"')
    expect(renderItem({ ...parts, options: {} }, { name: 'A & B' }).text).toBe('A & B')
  })

  it('says what no variable of the part answers to', () => {
    const variables = { item: [{ name: 'name' }], frame: [{ name: 'shop.name' }] }
    const parts: FeedParts = { format: 'text', head: '{{name}}', item: '{{name}}{{nobody}}' }

    expect(renderFile(parts, sample, variables).unknown.sort()).toEqual(['name', 'nobody'])
  })
})

describe('parseHint()', () => {
  it('finds the line of bad JSON', () => {
    expect(parseHint('json', '{"a":1}')).toBeNull()
    expect(parseHint('json', '{\n"a":\n}')?.message).toBeTruthy()
  })

  it('reads XML with the browser’s parser', () => {
    expect(parseHint('xml', '<a><b/></a>')).toBeNull()
    expect(parseHint('xml', '<a><b></a>')?.message).toBeTruthy()
  })

  it('has nothing to say of a csv or of nothing', () => {
    expect(parseHint('csv', 'a,"b')).toBeNull()
    expect(parseHint('json', '  ')).toBeNull()
  })
})
