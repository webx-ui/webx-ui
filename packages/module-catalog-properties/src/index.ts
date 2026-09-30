import './style.css'

export { catalogProperties, propertyGroupsOptions, type CatalogPropertiesOptions } from './module'
export { catalogPropertiesMessages } from './messages'
export {
  createPropertiesApi,
  propertySearch,
  GROUPS_API,
  PROPERTIES_API,
  SETS_API,
  type CatalogPropertiesApi,
  type ValueInput,
} from './api'
export { formatNumber, propertyName, wordsIn, type NumberShape } from './format'
export {
  propertyEditorKey,
  providePropertyEditor,
  usePropertyEditor,
  type PropertyEditorContext,
} from './editor'
export { default as WxCatalogPropertiesPage } from './PropertiesPage.vue'
export { default as WxCatalogPropertyEditor } from './PropertyEditorPage.vue'
export { default as WxCatalogPropertyValues } from './ValuesField.vue'
export { default as WxCatalogPropertyIntervals } from './IntervalsField.vue'
export { default as WxCatalogCategoryProperties } from './CategoryPropertiesField.vue'
export { default as WxCatalogProductProperties } from './ProductPropertiesField.vue'
export type {
  CategorySet,
  EffectiveSet,
  FilterMode,
  HeldValue,
  PropertiesPage,
  PropertyDetail,
  PropertyInterval,
  PropertyQuery,
  PropertyRow,
  PropertyType,
  PropertyValue,
  ValueNode,
  ValueOrder,
  Words,
} from './types'
