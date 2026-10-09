// What the specs write down as a measurement rather than a look (THEMES §18.4, WIDGETS §16),
// on the starter site that `scripts/starter-site.sh` builds. The pages are the ones the header
// links to — the showcase included — so a page the demo adds is checked without being listed.

import { readFileSync } from 'node:fs'
import { expect, test } from '@playwright/test'

const tokens = JSON.parse(
  readFileSync(new URL('../../php/packages/theme-default/tokens.json', import.meta.url), 'utf8'),
)

// A preset as the site would print it: its tokens over the defaults on :root.
const presets = {
  default: {},
  ...Object.fromEntries(Object.entries(tokens.presets).map(([name, { tokens: t }]) => [name, t])),
}

async function paint(page, preset) {
  await page.evaluate((values) => {
    for (const [name, value] of Object.entries(values)) {
      document.documentElement.style.setProperty(`--site-${name}`, value)
    }
  }, presets[preset])
}

// Transitions would leave a measurement taken right after a change somewhere in between.
async function still(page) {
  await page.addStyleTag({
    content: '*, *::before, *::after, ::backdrop { transition: none !important; }',
  })
}

let pages = null

/** The home page and every page of the site the mobile navigation links to. */
async function sitePages(page) {
  if (pages) return pages
  await page.goto('/')
  const found = await page.$$eval('.webx-mobile-nav a[href], .webx-header-nav a[href]', (links) =>
    links
      .map((a) => new URL(a.href))
      .filter((url) => url.origin === location.origin)
      .map((url) => url.pathname),
  )
  pages = ['/', ...new Set(found)].filter((path, i, all) => all.indexOf(path) === i)
  return pages
}

test.describe('every page', () => {
  test('has no horizontal scroll at 360px', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    for (const path of await sitePages(page)) {
      await page.goto(path)
      const { scroll, client } = await page.evaluate(() => ({
        scroll: document.documentElement.scrollWidth,
        client: document.documentElement.clientWidth,
      }))
      expect(scroll, `${path} scrolls sideways`).toBeLessThanOrEqual(client)
    }
  })

  test('is photographed at 375 and 1280 in every preset', async ({ page }, info) => {
    test.setTimeout(10 * 60_000)
    for (const path of await sitePages(page)) {
      for (const width of [375, 1280]) {
        await page.setViewportSize({ width, height: width === 375 ? 812 : 900 })
        await page.goto(path)
        for (const preset of Object.keys(presets)) {
          await paint(page, preset)
          await info.attach(`${path} ${width} ${preset}`, {
            body: await page.screenshot({ fullPage: true }),
            contentType: 'image/png',
          })
        }
      }
    }
  })
})

test.describe('mobile menu at 375px', () => {
  test.beforeEach(async ({ page }) => {
    // Short, so that the menu's body has more than it can show.
    await page.setViewportSize({ width: 375, height: 520 })
    await page.goto('/services')
    await still(page)
  })

  const open = async (page) => {
    await page.locator('.webx-header__trigger').click()
    await expect(page.locator('#webx-mobile-menu')).toHaveJSProperty('open', true)
  }

  test('its body scrolls, the page under it does not, its bottom is on screen', async ({
    page,
  }) => {
    await page.evaluate(() => window.scrollTo(0, 200))
    await open(page)
    const before = await page.evaluate(() => window.scrollY)

    const body = page.locator('.webx-mobile-menu__body')
    const box = await body.boundingBox()
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
    await page.mouse.wheel(0, 2000)
    await page.waitForTimeout(300)

    const after = await page.evaluate(() => ({
      body: document.querySelector('.webx-mobile-menu__body').scrollTop,
      overflow:
        document.querySelector('.webx-mobile-menu__body').scrollHeight >
        document.querySelector('.webx-mobile-menu__body').clientHeight,
      page: window.scrollY,
      bottom: document.querySelector('.webx-mobile-menu__bottom')?.getBoundingClientRect().bottom,
      panel: document.querySelector('#webx-mobile-menu').getBoundingClientRect().bottom,
      height: window.innerHeight,
    }))

    expect(after.overflow, 'the showcase should give the menu more than fits').toBe(true)
    expect(after.body).toBeGreaterThan(0)
    expect(after.page).toBe(before)
    expect(after.panel).toBeLessThanOrEqual(after.height)
    expect(after.bottom).toBeLessThanOrEqual(after.height)
  })

  test('keeps the focus inside and gives it back on Esc', async ({ page }) => {
    await open(page)
    for (let i = 0; i < 25; i++) {
      await page.keyboard.press('Tab')
      // Past the last entry a modal hands focus to the browser's own controls (activeElement is
      // <body> then), never to the page behind it.
      const inside = await page.evaluate(() => {
        const active = document.activeElement
        return (
          !active ||
          active === document.body ||
          document.getElementById('webx-mobile-menu').contains(active)
        )
      })
      expect(inside, `Tab #${i + 1} left the menu`).toBe(true)
    }
    await page.keyboard.press('Escape')
    await expect(page.locator('#webx-mobile-menu')).toHaveJSProperty('open', false)
    expect(await page.evaluate(() => document.activeElement?.id)).toBe('webx-mobile-menu-trigger')
    expect(
      await page.evaluate(() => document.documentElement.classList.contains('webx-scroll-locked')),
    ).toBe(false)
  })

  test('closes on Back without leaving the page, and on a link inside', async ({ page }) => {
    const url = page.url()
    await open(page)
    await page.goBack()
    await expect(page.locator('#webx-mobile-menu')).toHaveJSProperty('open', false)
    expect(page.url()).toBe(url)

    await open(page)
    await page.locator('.webx-mobile-nav a[href]:visible').first().click()
    await page.waitForLoadState()
    await expect(page.locator('#webx-mobile-menu')).toHaveJSProperty('open', false)
  })

  test('opens on the branch of the page, and goes back a level with Back', async ({ page }) => {
    await open(page)
    const back = page
      .locator('details[open] > .webx-mobile-nav__level--drill > .webx-mobile-nav__back')
      .last()
    await expect(back).toBeVisible()
    await back.click()
    await expect(page.locator('.webx-mobile-nav > .webx-mobile-nav__list')).not.toHaveClass(
      /is-drilled/,
    )
  })
})

test.describe('header', () => {
  test('folds the navigation exactly when its items no longer fit', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 800 })
    await page.goto('/')

    // What the bar needs in a row, measured once where everything fits: the bar is a grid of
    // brand, navigation, actions and trigger, three gaps between them.
    const need = await page.evaluate(() => {
      const bar = document.querySelector('.webx-header__bar')
      const gap = parseFloat(getComputedStyle(bar).columnGap)
      const width = (selector) => bar.querySelector(selector)?.getBoundingClientRect().width ?? 0
      return (
        width('.webx-header__brand') +
        width('.webx-header-nav') +
        width('.webx-header__actions') +
        3 * gap
      )
    })

    for (let width = 1600; width >= 360; width -= 40) {
      await page.setViewportSize({ width, height: 800 })
      await page.waitForTimeout(80)
      const { room, folded } = await page.evaluate(() => {
        const bar = document.querySelector('.webx-header__bar')
        const style = getComputedStyle(bar)
        return {
          room: bar.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
          folded: document.querySelector('.webx-header').classList.contains('is-collapsed'),
        }
      })
      if (Math.abs(room - need) < 2) continue
      expect(folded, `at ${width}px: needs ${need}, has ${room}`).toBe(need > room)
    }
  })

  test('stops an anchor below the sticky header', async ({ page }) => {
    for (const width of [375, 1280]) {
      await page.setViewportSize({ width, height: 700 })
      await page.goto('/kitchen-sink/header-and-menu')
      await still(page)
      await page.evaluate(() => {
        const main = document.querySelector('main')
        main.insertAdjacentHTML(
          'beforeend',
          '<div style="height:2000px"></div><h2 id="probe">Probe</h2><div style="height:2000px"></div>',
        )
        location.hash = '#probe'
      })
      await page.waitForTimeout(200)
      const { top, height, bottom } = await page.evaluate(() => ({
        top: document.getElementById('probe').getBoundingClientRect().top,
        height: parseFloat(
          getComputedStyle(document.documentElement).getPropertyValue('--webx-header-height'),
        ),
        bottom: document.querySelector('.webx-header').getBoundingClientRect().bottom,
      }))
      expect(height, 'the header publishes its height').toBeGreaterThan(0)
      expect(top, `at ${width}px`).toBeGreaterThanOrEqual(height - 1)
      expect(top, `at ${width}px`).toBeGreaterThanOrEqual(bottom - 1)
    }
  })

  test('opens a dropdown on hover and from the keyboard', async ({ page }) => {
    await page.setViewportSize({ width: 1600, height: 800 })
    await page.goto('/')
    const item = page.locator('.webx-header-nav__item:has(.webx-header-nav__dropdown)').first()
    await item.locator('.webx-header-nav__link').hover()
    await expect(item.locator('.webx-header-nav__dropdown')).toBeVisible()
    await page.mouse.move(5, 790)
    await expect(item.locator('.webx-header-nav__dropdown')).toBeHidden()

    await item.locator('.webx-header-nav__toggle').focus()
    await page.keyboard.press('Enter')
    await expect(item.locator('.webx-header-nav__dropdown')).toBeVisible()
    expect(await page.evaluate(() => document.activeElement?.className)).toContain(
      'webx-header-nav__sublink',
    )
    await page.keyboard.press('Escape')
    await expect(item.locator('.webx-header-nav__dropdown')).toBeHidden()
  })
})

test.describe('cookie consent', () => {
  const PATH = '/kitchen-sink/cookie-consent'
  const THIRD = 'https://third-party.example'

  // The starter site has nothing third-party, so the page gets some the way the server would
  // print it: a counter waiting for statistics, an embed waiting for media.
  async function withThirdParty(page) {
    await page.route(
      (url) => url.pathname === PATH,
      async (route) => {
        const response = await route.fetch()
        const body = (await response.text()).replace(
          '</main>',
          `<script type="text/plain" data-webx-consent="statistics">window.statisticsRan = true</script>` +
            `<iframe title="probe" data-webx-consent="media" data-src="${THIRD}/embed"></iframe></main>`,
        )
        // Without the original length: the body grew, and a stale Content-Length would cut its end
        // off — the scripts before </body> with it.
        const headers = { ...response.headers() }
        delete headers['content-length']
        await route.fulfill({ response, body, headers })
      },
    )
    await page.route(`${THIRD}/**`, (route) =>
      route.fulfill({ body: '<p>third party</p>', contentType: 'text/html' }),
    )
    const origin = new URL(test.info().project.use.baseURL).origin
    const outside = []
    page.on('request', (request) => {
      if (new URL(request.url()).origin !== origin) outside.push(request.url())
    })
    return outside
  }

  const answer = async (page) =>
    (await page.context().cookies()).find((cookie) => cookie.name === 'webx_consent')

  test('the banner covers at most a third of a 360px screen, its buttons on it', async ({
    page,
  }) => {
    for (const height of [640, 740]) {
      await page.setViewportSize({ width: 360, height })
      await page.goto(PATH)
      const banner = page.locator('.webx-consent')
      await expect(banner).toBeVisible()
      const box = await banner.boundingBox()
      expect(box.height, `at 360×${height}`).toBeLessThanOrEqual(height / 3)
      expect(Math.round(box.y + box.height)).toBe(height)
      for (const button of await banner.locator('button').all()) {
        const b = await button.boundingBox()
        expect(b.y + b.height).toBeLessThanOrEqual(height)
        expect(b.height).toBeGreaterThanOrEqual(44)
      }
    }
  })

  test('"Reject all" and "Accept all" are the same size and the same weight', async ({ page }) => {
    for (const width of [360, 1280]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto(PATH)
      const look = (selector) =>
        page.locator(`.webx-consent ${selector}`).evaluate((el) => {
          const style = getComputedStyle(el)
          const box = el.getBoundingClientRect()
          return {
            width: Math.round(box.width),
            height: Math.round(box.height),
            weight: style.fontWeight,
            size: style.fontSize,
            background: style.backgroundColor,
            color: style.color,
          }
        })
      expect(await look('[data-webx-consent-reject]'), `at ${width}px`).toEqual(
        await look('[data-webx-consent-accept]'),
      )
    }
  })

  test('nothing third-party loads before consent; a text/plain script runs after it', async ({
    page,
  }) => {
    const outside = await withThirdParty(page)
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto(PATH)
    await page.waitForLoadState('networkidle')
    expect(outside, 'requests before any answer').toEqual([])
    expect(await page.evaluate(() => window.statisticsRan)).toBeUndefined()

    // Customize: the page asks for statistics and media, so the dialog offers them — and no more.
    await page.locator('.webx-consent__customize').click()
    const dialog = page.locator('#webx-consent')
    await expect(dialog).toHaveJSProperty('open', true)
    await expect(dialog.locator('[data-webx-consent-row="statistics"]')).toBeVisible()
    await expect(dialog.locator('[data-webx-consent-row="media"]')).toBeVisible()
    await expect(dialog.locator('[data-webx-consent-row="marketing"]')).toBeHidden()

    await dialog.locator('[name="statistics"]').check()
    await dialog.locator('[data-webx-consent-save]').click()
    await expect(dialog).toHaveJSProperty('open', false)
    await expect(page.locator('.webx-consent')).toBeHidden()
    expect(await page.evaluate(() => window.statisticsRan)).toBe(true)
    expect(outside, 'statistics is not media').toEqual([])
    expect(decodeURIComponent((await answer(page)).value)).toContain('"c":["statistics"]')
    expect((await answer(page)).sameSite).toBe('Lax')

    // The footer link opens the dialog again; accepting media loads the embed.
    await page.locator('.webx-consent-link').click()
    await expect(dialog).toHaveJSProperty('open', true)
    await expect(dialog.locator('[name="statistics"]')).toBeChecked()
    const embed = page.waitForRequest(`${THIRD}/embed`)
    await dialog.locator('[data-webx-consent-accept]').click()
    await embed

    // Answered: the next page does not ask, and what waited runs at once.
    await page.goto(PATH)
    await expect(page.locator('.webx-consent')).toBeHidden()
    expect(await page.evaluate(() => window.statisticsRan)).toBe(true)
  })

  test('taking an answer back reloads the page', async ({ page }) => {
    await withThirdParty(page)
    await page.goto(PATH)
    await page.locator('.webx-consent [data-webx-consent-accept]').click()
    expect(await page.evaluate(() => window.statisticsRan)).toBe(true)

    await page.locator('.webx-consent-link').click()
    await page.locator('#webx-consent [name="statistics"]').uncheck()
    await Promise.all([
      page.waitForEvent('load'),
      page.locator('#webx-consent [data-webx-consent-save]').click(),
    ])
    expect(await page.evaluate(() => window.statisticsRan)).toBeUndefined()
    await expect(page.locator('.webx-consent')).toBeHidden()
  })

  test('Global Privacy Control leaves marketing out of "Accept all"', async ({ browser }) => {
    const context = await browser.newContext({ extraHTTPHeaders: { 'Sec-GPC': '1' } })
    const page = await context.newPage()
    await page.goto(PATH)
    await page.locator('.webx-consent__customize').click()
    await expect(page.locator('#webx-consent [data-webx-consent-gpc]')).toBeVisible()
    await page.locator('#webx-consent [data-webx-consent-accept]').click()
    const cookie = (await context.cookies()).find((c) => c.name === 'webx_consent')
    expect(decodeURIComponent(cookie.value)).not.toContain('marketing')
    await context.close()
  })

  test('the buttons of the banner keep their contrast in every preset', async ({ page }) => {
    await page.goto(PATH)
    for (const preset of Object.keys(presets)) {
      await paint(page, preset)
      const pairs = await page.evaluate(() => {
        const canvas = document
          .createElement('canvas')
          .getContext('2d', { willReadFrequently: true })
        // Whatever form the computed colour takes (rgb, oklch, color-mix), as sRGB bytes.
        const rgb = (color) => {
          canvas.clearRect(0, 0, 1, 1)
          canvas.fillStyle = color
          canvas.fillRect(0, 0, 1, 1)
          return Array.from(canvas.getImageData(0, 0, 1, 1).data).slice(0, 3)
        }
        const luminance = (color) => {
          const [r, g, b] = rgb(color).map((v) => {
            v /= 255
            return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
          })
          return 0.2126 * r + 0.7152 * g + 0.0722 * b
        }
        const ratio = (a, b) => {
          const [x, y] = [luminance(a), luminance(b)].sort((p, q) => q - p)
          return (x + 0.05) / (y + 0.05)
        }
        const banner = getComputedStyle(document.querySelector('.webx-consent'))
        const button = getComputedStyle(document.querySelector('.webx-consent__button'))
        const customize = getComputedStyle(document.querySelector('.webx-consent__customize'))
        return {
          button: ratio(button.color, button.backgroundColor),
          customize: ratio(customize.color, banner.backgroundColor),
          text: ratio(banner.color, banner.backgroundColor),
        }
      })
      for (const [what, ratio] of Object.entries(pairs)) {
        expect(ratio, `${preset}: ${what}`).toBeGreaterThanOrEqual(4.5)
      }
    }
  })
})
