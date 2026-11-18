// The halves of critical.mjs that run in the page. Each goes in as source (`(${collect})(…)`),
// so each uses nothing from outside itself: a page's Content-Security-Policy, which refuses
// `new Function`, does not apply to a string Playwright evaluates.

// The rules of every linked stylesheet the first screen needs, by sheet. `below` — how far the
// first screen reaches, in screens; `split` is splitSelectors of rules.mjs.
export const collect = (below, split) => {
  const bottom = innerHeight * below
  const dynamic =
    /::?(hover|focus|focus-visible|focus-within|active|visited|before|after|placeholder|marker|selection|backdrop|file-selector-button|-webkit-[a-z-]+|-moz-[a-z-]+)(\([^)]*\))?/g

  // A block the edge of the first screen crosses is drawn by its whole height when something
  // seen hangs on its bottom: a texture `inset: 0; object-fit: cover`, a background `cover` or
  // set off the top. That height comes from the children below the edge — without their rules
  // the block is shorter and the texture scales otherwise — so its children count as seen.
  // A frame of the whole page (over three screens) does not: it would pull the whole page in.
  const tall = innerHeight * 3
  const anchored = (element, box) => {
    const style = getComputedStyle(element)
    if (
      style.backgroundImage !== 'none' &&
      (!/^auto( auto)?$/.test(style.backgroundSize) ||
        !/^(0%|0px|top)$/.test(style.backgroundPositionY))
    )
      return true
    for (const child of element.querySelectorAll('*')) {
      if (child.offsetParent !== element || getComputedStyle(child).position !== 'absolute')
        continue
      const inner = child.getBoundingClientRect()
      if (inner.top < bottom && Math.abs(inner.bottom - box.bottom) < 1) return true
    }
    return false
  }
  const anchors = []
  for (const element of document.body.querySelectorAll('*')) {
    const box = element.getBoundingClientRect()
    if (box.top < bottom && box.bottom > bottom && box.height <= tall && anchored(element, box))
      anchors.push(element)
  }

  const seen = (element) => {
    // A hidden element counts by its nearest drawn ancestor (a menu of the header); one whose
    // ancestor is the body itself is a closed window, which the first screen does not draw.
    if (!element.getClientRects().length) {
      while (element && !element.getClientRects().length) element = element.parentElement
      if (!element || element === document.body || element === document.documentElement)
        return false
    }
    const style = getComputedStyle(element)
    if (style.visibility === 'hidden') return false
    if (style.position === 'fixed' || style.position === 'sticky') return true
    const box = element.getBoundingClientRect()
    // Above the page counts too: that is where a skip link or a menu waits, held there by its rules.
    if (box.top < bottom) return true
    return anchors.some((anchor) => anchor.contains(element))
  }
  const query = (selector) => {
    try {
      return document.querySelectorAll(selector)
    } catch {
      return null
    }
  }
  const matches = (selectorText) => {
    for (const part of split(selectorText)) {
      let elements = query(part)
      if (!elements || !elements.length) elements = query(part.replace(dynamic, '').trim() || '*')
      for (const element of elements ?? []) if (seen(element)) return true
    }
    return false
  }
  const absolute = (text, base) =>
    text.replace(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g, (whole, quote, url) => {
      if (/^(data:|#|[a-z]+:\/\/)/i.test(url) || url.startsWith('/')) return whole
      const resolved = new URL(url, base)
      return `url("${resolved.origin === location.origin ? resolved.pathname + resolved.search + resolved.hash : resolved.href}")`
    })
  const sheets = []
  const walk = (rules, path, wrappers, base, kept) => {
    ;[...rules].forEach((rule, index) => {
      const id = [...path, index]
      if (rule instanceof CSSStyleRule) {
        // A rule that hides something is kept whatever it hides: without it a closed window
        // would flash open until the full CSS arrives.
        const hides = rule.style.display === 'none' || rule.style.visibility === 'hidden'
        const present = hides && !!query(rule.selectorText.replace(dynamic, '') || '*')?.length
        if (present || matches(rule.selectorText))
          kept.push({ id, wrappers, text: absolute(rule.cssText, base) })
      } else if (rule instanceof CSSMediaRule) {
        if (matchMedia(rule.conditionText).matches)
          walk(rule.cssRules, id, [...wrappers, `@media ${rule.conditionText}`], base, kept)
      } else if (
        rule.cssRules &&
        !(rule instanceof CSSKeyframesRule) &&
        rule.cssText.startsWith('@')
      ) {
        // @supports, @layer, @container: the rules inside are judged one by one.
        walk(
          rule.cssRules,
          id,
          [...wrappers, rule.cssText.slice(0, rule.cssText.indexOf('{')).trim()],
          base,
          kept,
        )
      } else if (!(rule instanceof CSSImportRule)) {
        kept.push({ id, wrappers, text: absolute(rule.cssText, base) })
      }
    })
  }
  for (const sheet of document.styleSheets) {
    // The inline <style> of the page — the tokens, the fonts, this very CSS — stays inline anyway.
    if (!sheet.href) continue
    const kept = []
    try {
      walk(sheet.cssRules, [], [], sheet.href, kept)
    } catch {
      continue
    }
    sheets.push({ href: sheet.href.replace(location.origin, ''), rules: kept })
  }
  return sheets
}

// Runs in a blank page: how many pixels of two screenshots differ.
export const compare = async ([a, b]) => {
  const decode = async (base64) => {
    const bitmap = await createImageBitmap(
      await (await fetch(`data:image/png;base64,${base64}`)).blob(),
    )
    const canvas = new OffscreenCanvas(bitmap.width, bitmap.height)
    const context = canvas.getContext('2d')
    context.drawImage(bitmap, 0, 0)
    return context.getImageData(0, 0, bitmap.width, bitmap.height)
  }
  const [x, y] = await Promise.all([decode(a), decode(b)])
  if (x.width !== y.width || x.height !== y.height) return x.width * x.height
  let differ = 0
  for (let i = 0; i < x.data.length; i += 4) {
    if (
      x.data[i] !== y.data[i] ||
      x.data[i + 1] !== y.data[i + 1] ||
      x.data[i + 2] !== y.data[i + 2]
    )
      differ++
  }
  return differ
}
