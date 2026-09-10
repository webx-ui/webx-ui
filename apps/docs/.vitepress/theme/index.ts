import type { Theme } from 'vitepress'
import DefaultTheme from 'vitepress/theme'
import { WebxUI } from '@webx-ui/core'
import './demo.css'

/**
 * VitePress toggles `.dark` on `<html>`; WebX UI tokens read `data-theme`.
 * Keep the two in sync so demos follow the site theme.
 */
function syncTheme() {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  const apply = () =>
    root.setAttribute('data-theme', root.classList.contains('dark') ? 'dark' : 'light')
  apply()
  new MutationObserver(apply).observe(root, { attributes: true, attributeFilter: ['class'] })
}

export default {
  extends: DefaultTheme,
  enhanceApp({ app }) {
    app.use(WebxUI)
    syncTheme()
  },
} satisfies Theme
