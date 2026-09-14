import type { ScreenModel, ScreenNode, VisibilityCondition } from './types'

/** Loose enough for `{ "is": 1 }` to match a numeric input, strict enough for `"1"` not to. */
function same(a: unknown, b: unknown): boolean {
  if (a === b) return true
  if (a === null || b === null || typeof a !== 'object' || typeof b !== 'object') return false
  return JSON.stringify(a) === JSON.stringify(b)
}

export function evaluateCondition(condition: VisibilityCondition, model: ScreenModel): boolean {
  if ('all' in condition) return condition.all.every((item) => evaluateCondition(item, model))
  if ('any' in condition) return condition.any.some((item) => evaluateCondition(item, model))

  const value = model[condition.when]
  if ('in' in condition) return condition.in.some((item) => same(item, value))
  if ('not' in condition) return !same(condition.not, value)
  return same(condition.is, value)
}

/** Whether a node should render, given the current model. Absent `visible` means yes. */
export function isVisible(node: ScreenNode, model: ScreenModel): boolean {
  const { visible } = node
  if (visible === undefined || visible === true) return true
  if (visible === false) return false
  return evaluateCondition(visible, model)
}
