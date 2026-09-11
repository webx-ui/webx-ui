import ConfirmDialog from '../internal/ConfirmDialog.vue'
import { openModal } from './useModal'
import type { ButtonType } from '../components/Button/types'

export interface ConfirmOptions {
  /** The question, as the heading. Defaults to "Are you sure?" */
  title?: string
  /** What is at stake, under the question. */
  message?: string
  confirmText?: string
  cancelText?: string
  /** Colour of the confirming button. `danger` for anything that destroys something. */
  tone?: ButtonType
  width?: number | string
}

/**
 * Asks, and answers `true` or `false`.
 *
 * ```ts
 * if (await confirm('Delete this product?')) remove(product)
 * ```
 *
 * It never rejects. A question that throws when the answer is no turns every call site
 * into a `try` block, and a forgotten `catch` into an unhandled rejection.
 */
export function confirm(
  message?: string | ConfirmOptions,
  options: ConfirmOptions = {},
): Promise<boolean> {
  const props = typeof message === 'string' ? { message, ...options } : { ...message, ...options }

  return openModal<boolean>(ConfirmDialog, { props }).then((answer) => answer === true)
}
