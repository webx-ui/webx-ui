import { defineAsyncComponent } from 'vue'
export { configurators, type ConfiguratorsOptions } from './module'
export { createConfiguratorsApi, CONFIGURATORS_API, type ConfiguratorsApi } from './api'
export { configuratorsMessages } from './messages'
export { formatMoney, formatNumber } from './money'
export * from './engine'
export const WxConfiguratorsPage = defineAsyncComponent(() => import('./ConfiguratorsPage.vue'))
export const WxConfiguratorEditorPage = defineAsyncComponent(
  () => import('./ConfiguratorEditorPage.vue'),
)
export { default as WxConfiguratorField } from './ConfiguratorField.vue'
export { default as WxConfiguratorPresetField } from './PresetField.vue'
export { default as WxConfiguratorRequest } from './RequestEnclosure.vue'
export type {
  ConfiguratorCandidate,
  ConfiguratorChoice,
  ConfiguratorPreset,
  ConfiguratorSizeChoice,
  ConfiguratorStepChoice,
  ConfiguratorDetail,
  ConfiguratorQuery,
  ConfiguratorRow,
  ConfiguratorStatus,
  ConfiguratorValues as ConfiguratorRecordValues,
  ConfiguratorVersion,
  SchemaProblem,
  ServerQuote,
} from './types'
