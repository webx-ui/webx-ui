// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { configurators } from './module'

describe('configurators()', () => {
  /* The id is what the manifest names the section by; the composer half writes `...configurators()`. */
  it('is one section with the list, the editor, its field types and the request card', () => {
    const [section] = configurators()

    expect(section?.id).toBe('configurators')
    expect(section?.routes?.map((route) => route.path)).toEqual([
      '/configurators',
      '/configurators/:id(\\d+)',
    ])
    expect(Object.keys(section?.types ?? {})).toEqual([
      'wx-configurator',
      'wx-configurator-preset',
      'inbox-enclosure:configurator',
    ])
  })

  it('moves with the path it is given', () => {
    expect(configurators({ path: '/builder' })[0]?.path).toBe('/builder')
  })
})
