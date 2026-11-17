// The browser half of `php artisan webx:theme:critical` (WEBX_UI_THEMES.md §13.3).
//
//   node critical.mjs <job.json>
//
// The job names the site, its pages and the screens. `extract` renders every page with its full
// CSS and keeps the rules the first screen draws with: the rules of what is seen (a quarter of a
// screen below too), of what is fixed or sticky, the rules that hide a closed window or a menu,
// `@font-face`, `@keyframes`, `@property`, and an `@media` only where its condition holds on one
// of the screens. Then lightningcss minifies it and writes the prefixes the targets need — the
// rules come out of Chromium, which drops every prefix but its own. `check` draws the first
// screen twice — with the full CSS, and with the stylesheets refused so only the inline CSS is
// left — and counts the pixels that differ.
//
// Answers one line of JSON on stdout. `playwright` and `lightningcss` come from the site's
// node_modules (or NODE_PATH): the theme engine is a Composer package and brings no Node of its own.

import { createRequire } from 'node:module'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { mergeRules, serializeRules, splitSelectors } from './rules.mjs'

const job = JSON.parse(readFileSync(process.argv[2], 'utf8'))
const require = createRequire(join(job.root, 'package.json'))

function load(name) {
  try {
    return require(name)
  } catch {
    answer({ error: `${name} is not installed in the site: npm install --save-dev ${name}` })
    process.exit(1)
  }
}

function answer(value) {
  process.stdout.write(JSON.stringify(value) + '\n')
}

// Runs in the page: the rules of every linked stylesheet the first screen needs, by sheet.
// `split` is splitSelectors of rules.mjs, handed over as source (see collect()).
const COLLECT = (below, split) => {
  const bottom = innerHeight * below
  const dynamic =
    /::?(hover|focus|focus-visible|focus-within|active|visited|before|after|placeholder|marker|selection|backdrop|file-selector-button|-webkit-[a-z-]+|-moz-[a-z-]+)(\([^)]*\))?/g
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
    return box.top < bottom
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
const COMPARE = async ([a, b]) => {
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

async function open(browser, url, viewport, cssOff = false) {
  const context = await browser.newContext({
    viewport: { width: viewport[0], height: viewport[1] },
    ignoreHTTPSErrors: true,
  })
  if (cssOff)
    await context.route('**/*', (route) =>
      route.request().resourceType() === 'stylesheet' ? route.abort() : route.continue(),
    )
  const page = await context.newPage()
  const response = await page.goto(url, { waitUntil: 'load', timeout: 60000 })
  if (!response || response.status() >= 400)
    throw new Error(`${url} answered ${response ? response.status() : 'nothing'}`)
  await page.evaluate(() => document.fonts.ready)
  await page.waitForTimeout(job.settle ?? 1500)
  return { context, page }
}

async function extract(browser) {
  const screens = []
  const hrefs = new Set()
  for (const path of job.pages) {
    for (const viewport of job.viewports) {
      const { context, page } = await open(browser, job.base + path, viewport)
      // A string, not a function: splitSelectors goes in as source, and a page's
      // Content-Security-Policy, which refuses `new Function`, does not apply to it.
      const found = await page.evaluate(
        `(${COLLECT})(${JSON.stringify(job.below)}, ${splitSelectors})`,
      )
      await context.close()
      // The kept rules of the screen in the order it applies them: its sheets as it links them.
      screens.push(found.flatMap((sheet) => sheet.rules))
      for (const sheet of found) hrefs.add(sheet.href)
    }
  }
  // Every page's cascade as it was: one page's block bundle must not land after another's.
  const { rules, conflicts } = mergeRules(screens)
  const css = serializeRules(rules)
  const lightning = load('lightningcss')
  let targets = job.targets
  try {
    targets = lightning.browserslistToTargets(require('browserslist')(job.browsers))
  } catch {
    // No browserslist in the site: the fixed targets of the job.
  }
  const { code } = lightning.transform({
    filename: 'critical.css',
    code: Buffer.from(css),
    minify: true,
    targets,
  })
  return { css: code.toString(), sheets: hrefs.size, conflicts }
}

async function check(browser) {
  const results = []
  const blank = await (await browser.newContext()).newPage()
  for (const path of job.pages) {
    for (const viewport of job.viewports) {
      const shots = []
      for (const cssOff of [false, true]) {
        const { context, page } = await open(browser, job.base + path, viewport, cssOff)
        shots.push(await page.screenshot({ animations: 'disabled', caret: 'hide' }))
        await context.close()
      }
      const differ = await blank.evaluate(
        COMPARE,
        shots.map((shot) => shot.toString('base64')),
      )
      if (differ > 0 && job.shots) {
        mkdirSync(job.shots, { recursive: true })
        const name = `${path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home'}-${viewport[0]}`
        writeFileSync(join(job.shots, `${name}-full.png`), shots[0])
        writeFileSync(join(job.shots, `${name}-critical.png`), shots[1])
      }
      results.push({ path, viewport, differ, pixels: viewport[0] * viewport[1] })
    }
  }
  return { results }
}

const { chromium } = load('playwright')
const browser = await chromium.launch(
  process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {},
)
try {
  answer(job.mode === 'check' ? await check(browser) : await extract(browser))
} catch (error) {
  answer({ error: String(error?.message ?? error) })
  process.exitCode = 1
} finally {
  await browser.close()
}
