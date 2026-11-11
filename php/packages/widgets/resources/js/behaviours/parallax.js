/*
 * `data-webx-parallax` (spec §14): a picture that fills its frame — an <img>, or a wrapper of the
 * picture and its video — moves slower than the page while the frame crosses the screen. It is
 * drawn a little larger than the frame and shifted with `translate`, never laid out again, so the
 * page around it does not move (no CLS) and the frame's own `overflow` crops the overscan.
 *
 * Where the browser has scroll-driven animations (`animation-timeline: view()`) this script only
 * adds the class and the stylesheet does the rest on the compositor. Elsewhere it is the fallback:
 * an IntersectionObserver says when the frame is on screen, and only then each scroll asks for one
 * frame that reads where the frame stands and writes one custom property — a read, then a write,
 * never a read after a write. The frame is the element's parent: the element's own box carries the
 * shift, and measuring it would measure the shift.
 *
 * Nothing moves without JavaScript, under reduced motion or in print: the picture then stands in
 * its frame at its own size, cropped by nothing but what cropped it before.
 */

import { listeners } from '../core.js'

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

const scrollDriven = () => window.CSS?.supports?.('animation-timeline: view()') ?? false

export function parallax(el) {
  if (reduced()) return

  if (scrollDriven()) {
    el.classList.add('webx-parallax')
    return () => el.classList.remove('webx-parallax')
  }

  if (typeof IntersectionObserver !== 'function' || typeof requestAnimationFrame !== 'function')
    return

  const frame = el.parentElement ?? el
  const on = listeners()
  let asked = 0
  let seen = false

  el.classList.add('webx-parallax', 'webx-parallax--script')

  // 0 — the frame's top at the bottom of the screen, 1 — its bottom at the top: `cover` of view().
  const place = () => {
    asked = 0
    const rect = frame.getBoundingClientRect()
    const height = window.innerHeight
    const progress = Math.min(1, Math.max(0, (height - rect.top) / (height + rect.height)))
    el.style.setProperty('--webx-parallax-progress', progress.toFixed(4))
  }

  const ask = () => {
    if (seen && !asked) asked = requestAnimationFrame(place)
  }

  const observer = new IntersectionObserver((entries) => {
    seen = entries[entries.length - 1].isIntersecting
    ask()
  })
  observer.observe(frame)

  on(window, 'scroll', ask, { passive: true })
  on(window, 'resize', ask)

  return () => {
    observer.disconnect()
    on.off()
    if (asked) cancelAnimationFrame(asked)
    el.classList.remove('webx-parallax', 'webx-parallax--script')
    el.style.removeProperty('--webx-parallax-progress')
  }
}
