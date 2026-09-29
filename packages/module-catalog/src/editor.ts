import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { CategoryRow, ProductImage, ProductRow } from './types'

/**
 * What the product editor knows and the nodes of `catalog.product-form` do not.
 *
 * The gallery needs it most: its pictures are not a value of the form but records of their own,
 * added, ordered and captioned by requests of their own (§11.2), so the node has to know which
 * product it is attached to and whether this administrator may write.
 */
export interface ProductEditorContext {
  /** The product as the server last answered it, or `null` while the first request is out. */
  product: Ref<ProductRow | null>
  /** The gallery in its order. The editor reads it with the product; the node keeps it. */
  images: Ref<ProductImage[]>
  /** Closed for writing: no permission, or a save in flight. */
  locked: Ref<boolean>
}

export const productEditorKey: InjectionKey<ProductEditorContext> = Symbol('wx-catalog-product')

export function provideProductEditor(editor: ProductEditorContext): void {
  provide(productEditorKey, editor)
}

/** The editor above this node, or `null` outside one — a demo or a test draws a placeholder. */
export function useProductEditor(): ProductEditorContext | null {
  return inject(productEditorKey, null)
}

/**
 * What the category editor knows and `wx-catalog-facets` needs: which category it is, so that the
 * field can say whose setting it inherits — the parent's, the grandparent's, or nobody's (§6.2).
 */
export interface CategoryEditorContext {
  category: Ref<CategoryRow | null>
  /** Closed for writing — what the screen's form says to its own controls, for the ones it cannot. */
  locked: Ref<boolean>
}

export const categoryEditorKey: InjectionKey<CategoryEditorContext> = Symbol('wx-catalog-category')

export function provideCatalogCategoryEditor(editor: CategoryEditorContext): void {
  provide(categoryEditorKey, editor)
}

export function useCatalogCategoryEditor(): CategoryEditorContext | null {
  return inject(categoryEditorKey, null)
}
