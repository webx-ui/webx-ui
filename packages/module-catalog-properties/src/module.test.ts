import { describe, expect, it } from 'vitest'
import { catalogPropertiesMessages } from './messages'
import { catalogProperties, propertyGroupsOptions } from './module'

describe('catalogProperties()', () => {
  it('is one section: the list, the page of one, and the groups behind a button', () => {
    const [section, ...rest] = catalogProperties()

    expect(rest).toEqual([])
    expect(section?.id).toBe('catalog-properties')
    expect(section?.path).toBe('/catalog/properties')
    expect(section?.routes?.map((route) => [route.path, route.name])).toEqual([
      ['/catalog/properties', 'webx.catalog-properties'],
      ['/catalog/properties/:id(\\d+)', 'webx.catalog-properties.edit'],
      ['/catalog/properties/groups', 'webx.catalog-properties.groups'],
      ['/catalog/properties/groups/:id(\\d+)', 'webx.catalog-properties.groups.edit'],
    ])
  })

  it('moves with the catalogue', () => {
    expect(catalogProperties({ path: '/shop' })[0]?.path).toBe('/shop/properties')
    expect(propertyGroupsOptions({ path: '/shop' }).back?.path).toBe('/shop/properties')
  })

  it('draws every node the server puts on its screens and on the catalogue’s', () => {
    expect(Object.keys(catalogProperties()[0]?.types ?? {}).sort()).toEqual([
      'wx-catalog-category-properties',
      'wx-catalog-product-properties',
      'wx-catalog-property-example',
      'wx-catalog-property-intervals',
      'wx-catalog-property-type',
      'wx-catalog-property-values',
    ])
  })

  it('asks for the groups with the rights of the catalogue, counting their properties', () => {
    const options = propertyGroupsOptions()

    expect(options.api).toBe('catalog/property-groups')
    expect(options.screen).toBe('catalog.property-group-form')
    expect(options.manage).toBe('catalog.manage')
    expect(options.count).toBe('properties_count')
  })

  /* A word pointing at a key nobody ships is the key itself on screen. */
  it('names the groups with lines it ships', () => {
    const options = propertyGroupsOptions()
    const keys = [...Object.values(options.words ?? {}), options.title!, options.back!.label]

    for (const key of keys) {
      const [, path = ''] = key.split('::')
      const [group = '', line = ''] = path.split('.')

      expect(catalogPropertiesMessages[group]?.[line], key).toBeTypeOf('string')
    }
  })
})
