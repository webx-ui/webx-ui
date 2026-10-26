import { inject, provide, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import type { ConfiguratorDetail, ConfiguratorValues } from './types'

/**
 * What the tabs of the editor share with the page that holds them: the values being edited, the
 * record around them, the refusals of the last save by field, and the way to read it all again
 * after something changed it on the server — a version put back.
 */
export interface ConfiguratorEditor {
  values: Ref<ConfiguratorValues>
  detail: Ref<ConfiguratorDetail | null>
  errors: Ref<Record<string, string[]>>
  canManage: ComputedRef<boolean>
  reload(): Promise<void>
}

const key: InjectionKey<ConfiguratorEditor> = Symbol('wx-configurator-editor')

export function provideConfiguratorEditor(editor: ConfiguratorEditor): void {
  provide(key, editor)
}

export function useConfiguratorEditor(): ConfiguratorEditor {
  const editor = inject(key, null)

  if (!editor) throw new Error('A tab of the configurator editor is used outside of it.')

  return editor
}
