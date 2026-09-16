/*
 * The WebX blocks runtime.
 *
 * Every bundle carries it, so a page with blocks always has `webx`. It is idempotent: a site that
 * needs `webx.provide` before its own entry runs loads it on its own first — `@webxBlocks('runtime')`
 * in the layout's head — and the copy inside the bundle steps aside.
 *
 * A block's script is a function per instance, not code run once: two identical blocks on a page,
 * a nested block and the panel replacing one block after an edit all rely on that. An instance is
 * mounted once; `webx.mount(root)` picks up whatever is new inside a subtree.
 */
;(() => {
  if (window.webx && typeof window.webx.block === 'function') return

  const inits = new Map()
  const provided = new Map()
  const waiting = new Map()
  const mounted = new WeakMap()

  const values = (el) => {
    const raw = el.getAttribute('data-wx-values')
    if (!raw) return {}
    try {
      return JSON.parse(raw)
    } catch {
      return {}
    }
  }

  const mount = (root, only) => {
    for (const [slug, init] of inits) {
      if (only && only !== slug) continue
      const selector = `[data-wx-block="${slug}"]`
      const roots = Array.from(root.querySelectorAll(selector))
      if (root instanceof Element && root.matches(selector)) roots.unshift(root)
      for (const el of roots) {
        const done = mounted.get(el) ?? new Set()
        if (done.has(slug)) continue
        done.add(slug)
        mounted.set(el, done)
        Promise.resolve()
          .then(() => init(el, values(el)))
          .catch((error) => console.error(`webx block "${slug}":`, error))
      }
    }
  }

  window.webx = {
    block(slug, init) {
      inits.set(slug, init)
      mount(document, slug)
    },
    mount(root = document) {
      mount(root)
    },
    provide(name, value) {
      provided.set(name, value)
      for (const resolve of waiting.get(name) ?? []) resolve(value)
      waiting.delete(name)
    },
    use(name) {
      if (provided.has(name)) return Promise.resolve(provided.get(name))
      return new Promise((resolve) => waiting.set(name, [...(waiting.get(name) ?? []), resolve]))
    },
  }
})()
