import type { IconName } from '../Icon/types'

export type StepsDirection = 'horizontal' | 'vertical'
export type StepsSize = 'sm' | 'md'

/** Where a step stands relative to the one the reader is on. */
export type StepState = 'done' | 'current' | 'todo' | 'error'

export interface StepsProps {
  /** Which step the reader is on, counting from zero. */
  current?: number
  direction?: StepsDirection
  size?: StepsSize
  /** Marks the current step as gone wrong, without moving off it. */
  error?: boolean
  /**
   * Lets a finished step be clicked to go back to it. Going forward is never
   * offered: the steps ahead are the ones that have not been filled in.
   */
  clickable?: boolean
  /**
   * How narrow a step may get before a horizontal sequence turns down the page, in
   * pixels. `0` never folds, and a sequence that is already vertical ignores it.
   */
  minStepWidth?: number
  /** Accessible name of the sequence. */
  ariaLabel?: string
}

export interface StepsEmits {
  /** A step was chosen. Only fires for steps that are behind the current one. */
  change: [index: number]
}

export interface StepProps {
  /** What this step is. */
  title?: string
  /** What it involves, in a few words. */
  description?: string
  /** Icon instead of the number. */
  icon?: IconName
}

/** What `WxSteps` hands down to each step. */
export interface StepsContext {
  register: (id: symbol) => void
  unregister: (id: symbol) => void
  indexOf: (id: symbol) => number
  stateOf: (index: number) => StepState
  choose: (index: number) => void
  direction: StepsDirection
  size: StepsSize
  clickable: boolean
  total: number
}
