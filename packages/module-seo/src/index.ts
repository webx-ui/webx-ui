import { defineAsyncComponent } from 'vue'
export { seo, type SeoOptions } from './module'
export { createSeoApi, type SeoApi } from './api'
export { seoMessages } from './messages'
export { ruleInput, ruleTitle, seoOf } from './rule'
export { default as WxSeo } from './SeoCard.vue'
export const WxSeoUrlsPage = defineAsyncComponent(() => import('./SeoUrlsPage.vue'))
export const WxSeoRedirectsPage = defineAsyncComponent(() => import('./SeoRedirectsPage.vue'))
export const WxSeoAliasesPage = defineAsyncComponent(() => import('./SeoAliasesPage.vue'))
export const WxSeoLinksPage = defineAsyncComponent(() => import('./SeoLinksPage.vue'))
export { default as WxSeoSiteBarTab } from './SeoSiteBarTab.vue'
export { faqEnabled, seoFeature } from './features'
export type {
  MatchType,
  RobotsDirective,
  SeoAlias,
  SeoAliasQuery,
  SeoChainStep,
  SeoFaqImportMode,
  SeoFaqImportResult,
  SeoFaqInput,
  SeoFaqItem,
  SeoFields,
  SeoImage,
  SeoLinkAddress,
  SeoLinkBlock,
  SeoLinkHeadingInput,
  SeoLinkHeadingResult,
  SeoLinkImportMode,
  SeoLinkImportResult,
  SeoLinkInput,
  SeoLinkItem,
  SeoLinkProblem,
  SeoLinkQuery,
  SeoLinkSaved,
  SeoLinkTarget,
  SeoPage,
  SeoRedirect,
  SeoRedirectInput,
  SeoRedirectQuery,
  SeoOrigin,
  SeoResolved,
  SeoRoute,
  SeoSiteBarField,
  SeoSiteBarReport,
  SeoTestResult,
  SeoUrlInput,
  SeoUrlQuery,
  SeoUrlRule,
  SeoValue,
} from './types'
export { robotsDirectives } from './types'
