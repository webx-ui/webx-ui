import './style.css'

export { catalogLandings, type CatalogLandingsOptions } from './module'
export { catalogLandingsMessages } from './messages'
export {
  cleanSet,
  createLandingsApi,
  formValues,
  landingSearch,
  FORM_FIELDS,
  LANDINGS_API,
  type CatalogLandingsApi,
} from './api'
export {
  landingEditorKey,
  provideLandingEditor,
  useLandingEditor,
  type LandingEditorContext,
} from './editor'
export { default as WxCatalogLandingsPage } from './LandingsPage.vue'
export { default as WxCatalogLandingEditor } from './LandingEditorPage.vue'
export { default as WxCatalogLandingSet } from './LandingSetField.vue'
export { default as WxCatalogLandingSlug } from './LandingSlugField.vue'
export { default as WxCatalogLandingSort } from './LandingSortField.vue'
export { default as WxCatalogLandingProducts } from './LandingProductsField.vue'
export type {
  BaseFacet,
  GenerateConflict,
  GenerateParams,
  GeneratePreview,
  GenerateRow,
  GenerateRun,
  LandingAttention,
  LandingChip,
  LandingDetail,
  LandingFilters,
  LandingQuery,
  LandingRow,
  LandingsPage,
  RecommendedItem,
  SetChoice,
  SetCount,
} from './types'
