/*
 * The widgets runtime: on every page of a site with a theme (spec §4).
 *
 * It joins `window.webx` rather than owning it. The blocks runtime may already be there — its
 * bundle is in the head, this script is before </body> — or may come after it; each wraps the
 * `mount` it found, so `webx.mount(root)` starts both the blocks and the widgets inside `root`,
 * whichever loaded first. `webx.unmount(root)` lets the widgets inside `root` go.
 *
 * The light behaviours (§6) live here and not in files of their own: an attribute in any
 * template switches them on, with no component to claim them.
 */

import '../css/runtime.css'
import { define, mount, unmount } from './core.js'
import { accordion } from './behaviours/accordion.js'
import { dialog, openDialog } from './behaviours/dialog.js'
import { disclosure } from './behaviours/disclosure.js'
import { tabs } from './behaviours/tabs.js'

const webx = (window.webx ??= {})

if (typeof webx.widget !== 'function') {
  // The stylesheet's no-JavaScript fallbacks (`:target` dialogs) step aside from here on.
  document.documentElement.classList.add('webx-js')

  const mountBefore = webx.mount
  const unmountBefore = webx.unmount

  webx.widget = (name, selector, setup) => {
    define(name, selector, setup)
    mount(document, name)
  }
  webx.mount = (root = document) => {
    mountBefore?.call(webx, root)
    mount(root)
  }
  webx.unmount = (root = document) => {
    unmountBefore?.call(webx, root)
    unmount(root)
  }

  webx.widget('disclosure', '[data-webx-disclosure]', disclosure)
  webx.widget('dialog', '[data-webx-dialog]', dialog)
  webx.widget('tabs', '[data-webx-tabs]', tabs)
  webx.widget('accordion', '[data-webx-accordion]', accordion)

  // A link to a dialog, shared or bookmarked, opens it the way its opener would.
  const target = location.hash
    ? document.getElementById(decodeURIComponent(location.hash.slice(1)))
    : null
  if (target instanceof HTMLDialogElement) openDialog(target)
}
