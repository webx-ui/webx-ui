import { computed, type ComputedRef } from 'vue'
import { useTranslate } from './i18n'

export interface RemoveConfirm {
  /** The question, «Delete?». */
  confirm: string
  confirmText: string
  cancelText: string
}

/**
 * The words of the popover a row of a list asks in before it goes, in the panel's language.
 *
 * Removing one row — a label off a product, a choice out of a field, a chat out of a channel — is
 * asked about by the button, not in a dialog (`WxAction`'s `confirm`); deleting a whole record
 * stays with `confirm()` in a modal. The core speaks English only, so the panel hands it these:
 *
 *     <wx-action type="remove" v-bind="askRemove" @click="remove(row)" />
 */
export function useRemoveConfirm(): ComputedRef<RemoveConfirm> {
  const t = useTranslate('webx-admin')

  return computed(() => ({
    confirm: t('confirm.remove'),
    confirmText: t('confirm.yes'),
    cancelText: t('confirm.cancel'),
  }))
}
