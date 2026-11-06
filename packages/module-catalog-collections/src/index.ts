import { defineAsyncComponent } from 'vue'
import './style.css'

export { catalogCollections, type CatalogCollectionsOptions } from './module'
export { catalogCollectionsMessages } from './messages'
export {
  blankValues,
  collectionSearch,
  createCollectionsApi,
  formValues,
  payload,
  presetFields,
  presetOf,
  previewQuestion,
  tagOf,
  COLLECTIONS_API,
  FORM_FIELDS,
  type CatalogCollectionsApi,
} from './api'
export {
  collectionEditorKey,
  provideCollectionEditor,
  useCollectionEditor,
  type CollectionEditorContext,
} from './editor'
export const WxCatalogCollectionsPage = defineAsyncComponent(() => import('./CollectionsPage.vue'))
export const WxCatalogCollectionEditor = defineAsyncComponent(
  () => import('./CollectionEditorPage.vue'),
)
export { default as WxCatalogCollectionField } from './CollectionField.vue'
export { default as WxCatalogCollectionKey } from './CollectionKeyField.vue'
export { default as WxCatalogCollectionPreset } from './CollectionPresetField.vue'
export { default as WxCatalogCollectionPreview } from './CollectionPreviewPanel.vue'
export { default as WxCatalogCollectionProducts } from './CollectionProductsField.vue'
export { default as WxCatalogCollectionSet } from './CollectionSetField.vue'
export { default as WxCatalogCollectionSort } from './CollectionSortField.vue'
export type {
  CollectionAttention,
  CollectionCandidate,
  CollectionDetail,
  CollectionFacets,
  CollectionItem,
  CollectionPreset,
  CollectionPreview,
  CollectionQuery,
  CollectionRow,
  CollectionSource,
  CollectionsPage,
  CollectionView,
  DropReason,
  DroppedProduct,
  PreviewProduct,
} from './types'
