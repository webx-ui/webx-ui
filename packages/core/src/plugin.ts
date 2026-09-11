import type { App, Component, Plugin } from 'vue'
import * as components from './components'

export interface WebxUiOptions {
  /**
   * Prefix used when registering components globally.
   * Components are declared as `WxButton`, so the default keeps that name.
   */
  prefix?: string
}

/**
 * Registers every component globally:
 *
 * ```ts
 * import { WebxUI } from '@webx-ui/core'
 * import '@webx-ui/core/style.css'
 *
 * app.use(WebxUI)
 * ```
 *
 * Prefer named imports when bundle size matters — they tree-shake.
 */
export const WebxUI: Plugin<[WebxUiOptions?]> = {
  install(app: App, options: WebxUiOptions = {}) {
    const { prefix = 'Wx' } = options

    /*
     * The barrel also exports helpers — the icon registry, the countdown formatter —
     * so the `Wx` prefix is what marks a component, and the cast follows that check.
     */
    for (const [name, exported] of Object.entries(components)) {
      if (!name.startsWith('Wx')) continue
      const registeredName = prefix === 'Wx' ? name : `${prefix}${name.slice(2)}`
      app.component(registeredName, exported as Component)
    }
  },
}

export default WebxUI
