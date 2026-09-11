import { inject, type ComputedRef, type InjectionKey } from 'vue'

export type AccordionSize = 'sm' | 'md'

/** Boxed as one list, each panel its own card, or nothing but rules between them. */
export type AccordionVariant = 'bordered' | 'separated' | 'plain'

/** Which side of the header the chevron sits on. */
export type AccordionIconPosition = 'start' | 'end'

/** What `WxAccordion` hands down to the items inside it. */
export interface AccordionContext {
  size: ComputedRef<AccordionSize>
  variant: ComputedRef<AccordionVariant>
  iconPosition: ComputedRef<AccordionIconPosition>
  /** The element each header is written as, so a page keeps one heading outline. */
  headingTag: ComputedRef<string>
}

export const accordionKey: InjectionKey<AccordionContext> = Symbol('wx-accordion')

export function useAccordion(): AccordionContext | null {
  return inject(accordionKey, null)
}
