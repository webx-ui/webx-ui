import type {
  AccordionIconPosition,
  AccordionSize,
  AccordionVariant,
} from '../../composables/useAccordion'

/** One open item, or a set of them when `multiple` is on. */
export type AccordionModelValue = string | string[] | undefined

export interface AccordionProps {
  /** Let more than one item stand open. The model becomes an array. */
  multiple?: boolean
  /** With one item at a time, clicking the open one closes it. */
  collapsible?: boolean
  /** Outline around the whole list, a card per item, or bare rules. */
  variant?: AccordionVariant
  size?: AccordionSize
  /** Chevron before the title or after it. */
  iconPosition?: AccordionIconPosition
  /**
   * Element the titles are written as. Headings are how a screen reader skims a page,
   * so pick the level that follows the one above the accordion.
   */
  headingTag?: string
  /** Nothing in the accordion opens or closes. */
  disabled?: boolean
}

export interface AccordionEmits {
  change: [value: AccordionModelValue]
}

export type { AccordionIconPosition, AccordionSize, AccordionVariant }
