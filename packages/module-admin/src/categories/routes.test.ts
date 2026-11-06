import { describe, expect, it } from 'vitest'
import { categoryRoutes } from './routes'
import type { CategoriesOptions } from './types'

const options = (name: string, module: string): CategoriesOptions =>
  ({ name, module, path: '/x', screen: 'x.category-form' }) as CategoriesOptions

describe('categoryRoutes', () => {
  it.each([
    ['webx.blog.rubrics', 'rubrics', 'blog.rubrics'],
    ['webx.catalog-properties.groups', 'catalog-properties', 'catalog-properties.groups'],
    ['webx.catalog-stock', 'catalog-stock', 'catalog-stock.index'],
  ])('names the help of the list %s after the route', (name, module, key) => {
    expect(categoryRoutes(options(name, module))[0]?.meta?.help).toBe(key)
  })

  it('names the help of the page of one after its screen', () => {
    expect(categoryRoutes(options('webx.blog.rubrics', 'rubrics'))[1]?.meta?.help).toBe(
      'x.category-form',
    )
  })
})
