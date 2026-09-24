import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { ScreenModel } from '@webx-ui/schema'

/**
 * What a record's editor knows about its address and a described screen cannot carry.
 *
 * The address of a service, a category, an article is the module's prefix and the record's slug.
 * The slug is a field of the screen; the prefix is configuration the editor was handed with the
 * record, and the address the site answers at right now is the registry's. `wx-slug` needs all
 * three to print the whole address and to warn before it moves — so the page that hosts the
 * screen provides them, the same way it provides the preview to `wx-blocks`.
 */
export interface RecordAddress {
  /** The values of the screen as they are right now, edits included. */
  values: Ref<ScreenModel>
  /** Where the module's addresses start: `services`, `''` for the root, `null` for none at all. */
  prefix: Ref<string | null>
  /** The address the registry holds for the record in this language, without a leading slash. */
  path: Ref<string | null | undefined>
  /** "The address is changing" — in the module's words. */
  moving: () => string
}

export const recordAddressKey: InjectionKey<RecordAddress> = Symbol('wx-record-address')

export function provideRecordAddress(address: RecordAddress): void {
  provide(recordAddressKey, address)
}

/** The editor above this field, or `null` outside one — a demo, a test, a stray screen. */
export function useRecordAddress(): RecordAddress | null {
  return inject(recordAddressKey, null)
}
