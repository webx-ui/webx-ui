import { inject, type ComputedRef, type InjectionKey } from 'vue'
import type { IconName } from '../components/Icon/types'

export type BreadcrumbSize = 'sm' | 'md'

/** What `WxBreadcrumb` hands down to the items inside it. */
export interface BreadcrumbContext {
  /** The character drawn between items. */
  separator: ComputedRef<string>
  /** An icon drawn instead of that character. */
  separatorIcon: ComputedRef<IconName | undefined>
  size: ComputedRef<BreadcrumbSize>
}

export const breadcrumbKey: InjectionKey<BreadcrumbContext> = Symbol('wx-breadcrumb')

export function useBreadcrumb(): BreadcrumbContext | null {
  return inject(breadcrumbKey, null)
}
