export { catalog, type CatalogOptions } from './module'
export { createCatalogApi, productSearch, CATALOG_API, type CatalogApi } from './api'
export { catalogMessages } from './messages'
export {
  provideProductEditor,
  productEditorKey,
  useProductEditor,
  provideCatalogCategoryEditor,
  categoryEditorKey,
  useCatalogCategoryEditor,
  type ProductEditorContext,
  type CategoryEditorContext,
} from './editor'
export { useCategoryTree, useFacetRegistry, type CategoryTree, type FacetRegistry } from './store'
export { default as WxCatalogProductsPage } from './ProductsPage.vue'
export { default as WxCatalogProductEditor } from './ProductEditorPage.vue'
export { default as WxCatalogProductCreateDialog } from './ProductCreateDialog.vue'
export { default as WxCatalogCategoriesPage } from './CategoriesPage.vue'
export { default as WxCatalogCategoryEditor } from './CategoryEditorPage.vue'
export { default as WxCatalogCategoryCreateDialog } from './CategoryCreateDialog.vue'
export { default as WxCatalogDeletedPage } from './DeletedPage.vue'
export { default as WxCatalogCategoryField } from './CategoryField.vue'
export { default as WxCatalogFacetsField } from './FacetsField.vue'
export { default as WxCatalogGallery } from './GalleryField.vue'
export type {
  CategoryDetail,
  CategoryNode,
  CategoryRef,
  CategoryRow,
  DeletedCategory,
  DeletedKind,
  DeletedProduct,
  FacetChoice,
  FacetInfo,
  FacetKind,
  FacetSetting,
  ProductColumnInfo,
  ProductDetail,
  ProductImage,
  ProductQuery,
  ProductRow,
  ProductSort,
  ProductState,
  ProductsPage,
  SkuHolder,
} from './types'
