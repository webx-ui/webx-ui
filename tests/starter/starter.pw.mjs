// What the specs write down as a measurement rather than a look (THEMES §18.4, WIDGETS §16),
// on the starter site that `scripts/starter-site.sh` builds. The pages are the ones the header
// links to — the showcase included — so a page the demo adds is checked without being listed.

import { readdirSync, readFileSync } from 'node:fs'
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
    await page.goto('/what-we-do')
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
    // Added while the page is parsed, before its module scripts run — not by rewriting the
    // response: a document fulfilled by page.route() is no longer on the loopback address, and
    // the browser's Private Network Access blocks every stylesheet and script it asks the site for.
    await page.addInitScript(
      ({ path, third }) => {
        if (location.pathname !== path) return
        new MutationObserver((_, observer) => {
          const main = document.querySelector('main')
          if (!main) return
          observer.disconnect()
          main.insertAdjacentHTML(
            'beforeend',
            '<script type="text/plain" data-webx-consent="statistics">window.statisticsRan = true</script>' +
              `<iframe title="probe" data-webx-consent="media" data-src="${third}/embed"></iframe>`,
          )
        }).observe(document, { childList: true, subtree: true })
      },
      { path: PATH, third: THIRD },
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

test.describe('dropdown panel', () => {
  const PATH = '/kitchen-sink/dropdown-and-form'

  // The header's dropdowns (the phones, the languages) stand at its right edge only, so the
  // page gets a row of them the way <x-webx-dropdown> prints them, across the whole window:
  // one at its left, one on hover, one against its right edge.
  async function withDropdowns(page) {
    await page.addInitScript((path) => {
      if (location.pathname !== path) return
      const dropdown = (id, openOn) =>
        `<details id="${id}" class="webx-dropdown webx-dropdown--bottom-start" data-webx-dropdown="${openOn}">` +
        `<summary class="webx-dropdown__trigger">${id}</summary>` +
        '<div class="webx-dropdown__panel"><a href="#one">Office: 0800-303-332</a><br><a href="#two">Sales: 0800-303-333</a></div>' +
        '</details>'
      new MutationObserver((_, observer) => {
        const main = document.querySelector('main')
        if (!main) return
        observer.disconnect()
        document.body.insertAdjacentHTML(
          'afterbegin',
          '<div id="dropdowns" style="display: flex; justify-content: space-between; padding: 0 8px">' +
            dropdown('first', 'click') +
            dropdown('hovered', 'hover') +
            dropdown('edge', 'click') +
            '</div>',
        )
      }).observe(document, { childList: true, subtree: true })
    }, PATH)
  }

  test('turns over at the right edge of the window at 360 and 1280', async ({ page }) => {
    await withDropdowns(page)
    for (const width of [360, 1280]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto(PATH)
      await still(page)
      await page.locator('#first summary').click()
      const first = await page.locator('#first .webx-dropdown__panel').evaluate((panel) => ({
        left: panel.getBoundingClientRect().left,
        trigger: panel.previousElementSibling.getBoundingClientRect().left,
        flipped: panel.classList.contains('is-flipped'),
      }))
      expect(first.flipped, `at ${width}`).toBe(false)
      expect(Math.round(first.left)).toBe(Math.round(first.trigger))

      await page.locator('#edge summary').click()
      const edge = await page.locator('#edge .webx-dropdown__panel').evaluate((panel) => {
        const box = panel.getBoundingClientRect()
        const trigger = panel.previousElementSibling.getBoundingClientRect()
        return {
          left: box.left,
          right: box.right,
          triggerRight: trigger.right,
          window: document.documentElement.clientWidth,
          flipped: panel.classList.contains('is-flipped'),
          top: panel.matches(':popover-open'),
        }
      })
      expect(edge.flipped, `at ${width}`).toBe(true)
      expect(edge.top, 'in the top layer').toBe(true)
      expect(edge.left).toBeGreaterThanOrEqual(0)
      expect(edge.right).toBeLessThanOrEqual(edge.window)
      expect(Math.round(edge.right)).toBe(Math.round(edge.triggerRight))
    }
  })

  test('keeps one open on the page, opens on hover', async ({ page }) => {
    await withDropdowns(page)
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto(PATH)
    await page.locator('#first summary').click()
    await expect(page.locator('#first')).toHaveJSProperty('open', true)
    await page.locator('#edge summary').click()
    await expect(page.locator('#first')).toHaveJSProperty('open', false)
    await expect(page.locator('#edge')).toHaveJSProperty('open', true)

    await page.locator('#hovered summary').hover()
    await expect(page.locator('#hovered .webx-dropdown__panel')).toBeVisible()
    await expect(page.locator('#edge')).toHaveJSProperty('open', false)
    await page.mouse.move(5, 790)
    await expect(page.locator('#hovered')).toHaveJSProperty('open', false)
  })

  test('closes on Esc and gives the focus back to its trigger', async ({ page }) => {
    await withDropdowns(page)
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await page.locator('#first summary').focus()
    await page.keyboard.press('Enter')
    await expect(page.locator('#first')).toHaveJSProperty('open', true)
    await page.keyboard.press('Tab')
    expect(await page.evaluate(() => document.activeElement?.getAttribute('href'))).toBe('#one')
    await page.keyboard.press('Escape')
    await expect(page.locator('#first')).toHaveJSProperty('open', false)
    expect(await page.evaluate(() => document.activeElement?.textContent)).toBe('first')

    // Tab out of the panel closes it.
    await page.keyboard.press('Space')
    await expect(page.locator('#first')).toHaveJSProperty('open', true)
    for (let i = 0; i < 3; i++) await page.keyboard.press('Tab')
    await expect(page.locator('#first')).toHaveJSProperty('open', false)
  })
})

test.describe('form in a dialog', () => {
  const PATH = '/kitchen-sink/dropdown-and-form'

  test('is on the page once, however many buttons open it', async ({ page }) => {
    await page.goto(PATH)
    expect(await page.locator('a[href="#webx-form-contact"]').count()).toBeGreaterThanOrEqual(2)
    expect(await page.locator('dialog#webx-form-contact').count()).toBe(1)
    expect(await page.locator('form[data-webx-form="contact"]').count()).toBe(1)
    await expect(page.locator('#webx-form-contact form')).toHaveClass(/webx-inbox-form--modal/)
  })

  test('fits a 360px screen and scrolls inside, the page under it still', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 480 })
    await page.goto(PATH)
    await still(page)
    await page.locator('a[href="#webx-form-contact"]').first().click()
    const dialog = page.locator('#webx-form-contact')
    await expect(dialog).toHaveJSProperty('open', true)

    const box = await dialog.evaluate((el) => {
      const r = el.getBoundingClientRect()
      return {
        left: r.left,
        right: r.right,
        top: r.top,
        bottom: r.bottom,
        width: document.documentElement.clientWidth,
        height: window.innerHeight,
        scroll: el.scrollHeight,
        client: el.clientHeight,
        sideways: el.scrollWidth - el.clientWidth,
      }
    })
    expect(box.left).toBeGreaterThanOrEqual(0)
    expect(box.right).toBeLessThanOrEqual(box.width)
    expect(box.top).toBeGreaterThanOrEqual(0)
    expect(box.bottom).toBeLessThanOrEqual(box.height)
    expect(box.sideways, 'scrolls sideways').toBeLessThanOrEqual(0)
    expect(box.scroll, 'the form is longer than the screen here').toBeGreaterThan(box.client)

    // Measured from here: the click scrolled the button into view first.
    const before = await page.evaluate(() => window.scrollY)
    await page.mouse.move(180, 240)
    await page.mouse.wheel(0, 400)
    await expect.poll(() => dialog.evaluate((el) => el.scrollTop)).toBeGreaterThan(0)
    expect(await page.evaluate(() => window.scrollY)).toBe(before)
    // The title and the close button stay on top while the form scrolls.
    const header = await page
      .locator('#webx-form-contact .webx-form-dialog__header')
      .evaluate(
        (el) => el.getBoundingClientRect().top - el.parentElement.getBoundingClientRect().top,
      )
    expect(Math.abs(header)).toBeLessThanOrEqual(1)
  })

  test('keeps the focus inside and gives it back to its button on Esc', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    const opener = page.locator('a[href="#webx-form-contact"]').first()
    await opener.focus()
    await page.keyboard.press('Enter')
    const dialog = page.locator('#webx-form-contact')
    await expect(dialog).toHaveJSProperty('open', true)
    for (let i = 0; i < 25; i++) {
      await page.keyboard.press('Tab')
      const inside = await page.evaluate(() => {
        const active = document.activeElement
        return (
          !active ||
          active === document.body ||
          document.getElementById('webx-form-contact').contains(active)
        )
      })
      expect(inside, `Tab #${i + 1} left the dialog`).toBe(true)
    }
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveJSProperty('open', false)
    expect(await opener.evaluate((el) => el === document.activeElement)).toBe(true)
    expect(
      await page.evaluate(() => document.documentElement.classList.contains('webx-scroll-locked')),
    ).toBe(false)
  })
})

test.describe('contacts', () => {
  const PATH = '/kitchen-sink/contacts'

  // The demo's Contacts tab: three numbers, Mon–Fri 9–19 and Sat 10–16 in UTC.
  const inWindow = async (locator) => {
    const box = await locator.boundingBox()
    const width = await locator.page().evaluate(() => document.documentElement.clientWidth)
    expect(box, 'the panel is not on the screen').not.toBeNull()
    expect(box.x).toBeGreaterThanOrEqual(0)
    expect(box.x + box.width).toBeLessThanOrEqual(width + 0.5)
  }

  test('every number dials in E.164', async ({ page }) => {
    await page.goto(PATH)
    const links = await page.$$eval('.webx-phones a[href^="tel:"], main a[href^="tel:"]', (all) =>
      all.map((a) => a.getAttribute('href')),
    )
    expect(links.length).toBeGreaterThanOrEqual(4)
    for (const href of links) expect(href).toMatch(/^tel:\+[1-9][\d-]{6,}(;ext=\d+)?$/)
    // Shown as typed, dialled without the spaces.
    const main = page.locator('.webx-header__actions .webx-phones__primary > .webx-phones__number')
    await expect(main).toHaveText('+800 1234 5678')
    await expect(main).toHaveAttribute('href', 'tel:+800-1234-5678')
  })

  test('the phone dropdown stays in the window at 1280, and in the menu at 360', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto(PATH)
    await still(page)
    await page.locator('.webx-header__actions .webx-phones__toggle').click()
    const panel = page.locator('.webx-header__actions .webx-phones .webx-dropdown__panel')
    await expect(panel).toBeVisible()
    await inWindow(panel)
    await expect(panel.locator('.webx-phones__label')).toHaveText(['Support', 'Partners'])

    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)
    await page.locator('.webx-mobile-menu__trigger').click()
    const inMenu = page.locator('.webx-mobile-menu__bottom .webx-phones')
    await inMenu.locator('.webx-phones__toggle').click()
    const menuPanel = inMenu.locator('.webx-dropdown__panel')
    await expect(menuPanel).toBeVisible()
    await inWindow(menuPanel)
  })

  test('the hours are the office clock, whatever the visitor clock says', async ({ browser }) => {
    // 15:30 in New York on a Monday is 19:30 in UTC: closed there, not open here.
    const context = await browser.newContext({ timezoneId: 'America/New_York' })
    const page = await context.newPage()
    await page.clock.setFixedTime(new Date('2026-10-12T15:30:00-04:00'))
    await page.goto(PATH)
    const status = page.locator('.webx-header__topbar [data-webx-hours-status]')
    await expect(status).toHaveText('Closed, opens tomorrow at 9:00 AM')
    await expect(status).toHaveClass(/is-closed/)

    await page.clock.setFixedTime(new Date('2026-10-12T10:00:00Z'))
    await page.reload()
    await expect(status).toHaveText('Open until 7:00 PM')
    await expect(status).toHaveClass(/is-open/)

    // Sunday: a day off, and Monday next.
    await page.clock.setFixedTime(new Date('2026-10-18T12:00:00Z'))
    await page.reload()
    await expect(status).toHaveText('Closed today, opens tomorrow at 9:00 AM')
    await page.locator('.webx-header__topbar .webx-hours__toggle').click()
    const today = page.locator('.webx-header__topbar .webx-hours__day.is-today')
    await expect(today).toHaveCount(1)
    await expect(today.locator('th')).toHaveText('Sun')
    await inWindow(page.locator('.webx-header__topbar .webx-dropdown__panel'))
    await context.close()
  })

  test('quick contact stands above the cookie banner, and in the corner without it', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)
    const button = page.locator('.webx-contact-button__toggle')
    const banner = page.locator('[data-webx-consent-banner]')
    await expect(banner).toBeVisible()
    const above = await button.boundingBox()
    const top = (await banner.boundingBox()).y
    expect(above.y + above.height).toBeLessThanOrEqual(top)

    await banner.locator('[data-webx-consent-accept]').click()
    await expect(banner).toBeHidden()
    await expect
      .poll(async () => {
        const box = await button.boundingBox()
        return Math.round(740 - (box.y + box.height))
      })
      .toBe(16)

    await button.click()
    const panel = page.locator('.webx-contact-button .webx-dropdown__panel')
    await expect(panel).toBeVisible()
    await inWindow(panel)
    const kinds = await panel
      .locator('.webx-contact-button__item')
      .evaluateAll((items) => items.map((a) => a.className.match(/__item--([a-z]+)/)[1]))
    expect(kinds).toEqual(['whatsapp', 'telegram', 'viber', 'telegram', 'phone', 'email'])
  })

  test('the bottom bar keeps the footer clear on a phone and is gone on a desktop', async ({
    page,
  }) => {
    // The default theme stands the button in the corner rather than the bar, so the page gets
    // the bar the way <x-webx-contact-bar :breakpoint="768"> prints it.
    await page.addInitScript((path) => {
      if (location.pathname !== path) return
      new MutationObserver((_, observer) => {
        const footer = document.querySelector('.site-footer')
        if (!footer || !document.querySelector('.webx-contact-button')) return
        observer.disconnect()
        footer.insertAdjacentHTML(
          'afterend',
          '<div class="webx-contact-bar" id="probe-bar">' +
            '<style>@container (min-width: 768px) { #probe-bar-spacer, #probe-bar-bar { display: none; } }</style>' +
            '<div class="webx-contact-bar__spacer" id="probe-bar-spacer"></div>' +
            '<nav class="webx-contact-bar__bar" id="probe-bar-bar">' +
            '<a class="webx-contact-bar__item webx-contact-bar__item--call" href="tel:+80012345678"><span>Call</span></a>' +
            '<a class="webx-contact-bar__item webx-contact-bar__item--form" href="#webx-form-contact"><span>Request</span></a>' +
            '</nav></div>',
        )
      }).observe(document, { childList: true, subtree: true })
    }, PATH)

    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)
    const bar = page.locator('#probe-bar-bar')
    await expect(bar).toBeVisible()
    const box = await bar.boundingBox()
    expect(Math.round(box.y + box.height)).toBe(740)
    expect(box.width).toBe(360)
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
    const footer = await page.locator('.site-footer').boundingBox()
    expect(footer.y + footer.height).toBeLessThanOrEqual(box.y + 0.5)
    const { scroll, client } = await page.evaluate(() => ({
      scroll: document.documentElement.scrollWidth,
      client: document.documentElement.clientWidth,
    }))
    expect(scroll).toBeLessThanOrEqual(client)

    await page.setViewportSize({ width: 1280, height: 800 })
    await expect(bar).toBeHidden()
    await expect(page.locator('#probe-bar-spacer')).toBeHidden()
  })

  test('the networks are links with names, opening apart from the site', async ({ page }) => {
    await page.goto(PATH)
    const links = page.locator('.site-footer .webx-socials__link')
    await expect(links).toHaveCount(4)
    for (const link of await links.all()) {
      await expect(link).toHaveAttribute('rel', 'noopener')
      await expect(link).toHaveAttribute('aria-label', /\S/)
      const size = await link.boundingBox()
      expect(size.width).toBeGreaterThanOrEqual(44)
    }
  })
})

test.describe('language switcher', () => {
  const PATH = '/kitchen-sink/language-switcher'
  // The same page in the other two languages: the showcase is written in all three.
  const TRANSLATED = {
    ru: '/ru/vitrina/pereklyuchatel-yazykov',
    pl: '/pl/witryna/przelacznik-jezykow',
  }
  // The one page of the showcase left in English on purpose.
  const UNTRANSLATED = `${PATH}/english-only`

  // The starter site speaks English, Russian and Polish, and so do the home page and the showcase
  // — all but the page under this one.
  const header = (page) => page.locator('.webx-header__actions .webx-language-switcher')

  const inWindow = async (locator) => {
    const box = await locator.boundingBox()
    const width = await locator.page().evaluate(() => document.documentElement.clientWidth)
    expect(box, 'the panel is not on the screen').not.toBeNull()
    expect(box.x).toBeGreaterThanOrEqual(0)
    expect(box.x + box.width).toBeLessThanOrEqual(width + 0.5)
  }

  const links = (switcher) =>
    switcher.locator('.webx-language-switcher__link').evaluateAll((all) =>
      all.map((a) => ({
        path: new URL(a.href).pathname,
        hreflang: a.getAttribute('hreflang'),
        lang: a.getAttribute('lang'),
        current: a.getAttribute('aria-current'),
        isCurrent: a.classList.contains('is-current'),
        fallback: a.classList.contains('is-fallback'),
        title: a.getAttribute('title'),
        name: a.textContent.trim(),
      })),
    )

  // Every link answers 200, in the language it says it is in.
  const answers = async (page, link) => {
    const response = await page.request.get(link.path)
    expect(response.status(), `${link.path} does not answer`).toBe(200)
    const html = await response.text()
    expect(
      html.match(/<html[^>]*\slang="([^"]+)"/)?.[1],
      `${link.path} is not in ${link.lang}`,
    ).toBe(link.lang)
  }

  test('leads to the same page where it is translated', async ({ page }) => {
    await page.goto('/')
    const all = await links(header(page))
    expect(all.map((l) => l.hreflang)).toEqual(['en', 'ru', 'pl'])
    expect(all.map((l) => l.name)).toEqual(['English', 'Русский', 'Polski'])
    expect(all.map((l) => l.path)).toEqual(['/', '/ru', '/pl'])
    for (const link of all) {
      expect(link.lang).toBe(link.hreflang)
      expect(link.fallback).toBe(false)
      await answers(page, link)
    }
    expect(all.filter((l) => l.current === 'page').map((l) => l.hreflang)).toEqual(['en'])
    expect(all.filter((l) => l.isCurrent).map((l) => l.hreflang)).toEqual(['en'])

    // And back: the Polish home page leads to the English one, and marks Polish.
    await page.goto('/pl')
    const back = await links(header(page))
    expect(back.find((l) => l.hreflang === 'en').path).toBe('/')
    expect(back.find((l) => l.current === 'page').hreflang).toBe('pl')

    // A showcase page too: each language at its own address, in its own words.
    await page.goto(PATH)
    const here = await links(header(page))
    expect(here.map((l) => l.path)).toEqual([PATH, TRANSLATED.ru, TRANSLATED.pl])
    for (const link of here) {
      expect(link.fallback).toBe(false)
      await answers(page, link)
    }
    await page.goto(TRANSLATED.ru)
    await expect(page.locator('h1').first()).toHaveText('Одна страница, все языки')
    expect((await links(header(page))).find((l) => l.hreflang === 'pl').path).toBe(TRANSLATED.pl)
  })

  // ROUTING §8.7: a page with no translation stays a page — the chosen language's frame around
  // the default language's text, closed to the index and pointing at the original.
  test('stays on the same page in a language without a translation', async ({ page }) => {
    const original = await (await page.request.get(UNTRANSLATED)).text()
    const h1 = (html) =>
      html
        .match(/<h1[^>]*>([\s\S]*?)<\/h1>/)?.[1]
        .replace(/<[^>]+>/g, '')
        .trim()
    expect(h1(original), 'the original has no heading to compare').toMatch(/\S/)

    await page.goto(UNTRANSLATED)
    const all = await links(header(page))
    expect(all.find((l) => l.hreflang === 'en')).toMatchObject({
      path: UNTRANSLATED,
      current: 'page',
      fallback: false,
    })
    for (const code of ['ru', 'pl']) {
      const link = all.find((l) => l.hreflang === code)
      expect(link).toMatchObject({
        path: `/${code}${UNTRANSLATED}`,
        fallback: false,
        current: null,
        title: null,
      })
      await answers(page, link)

      const html = await (await page.request.get(link.path)).text()
      expect(h1(html), `${link.path} is not the original's text`).toBe(h1(original))
      expect(html).toContain('<meta name="robots" content="noindex, follow">')
      expect(
        new URL(html.match(/<link rel="canonical" href="([^"]+)">/)?.[1] ?? 'http://x/none')
          .pathname,
        `${link.path} does not point at the original`,
      ).toBe(UNTRANSLATED)
      // The head names no alternates; the switcher's own links carry hreflang, and are meant to.
      expect(html, `${link.path} names alternates`).not.toMatch(/<link[^>]+hreflang=/)
    }

    // On the untranslated page the switcher marks its own language and leads back.
    await page.goto(`/pl${UNTRANSLATED}`)
    const back = await links(header(page))
    expect(back.find((l) => l.current === 'page').hreflang).toBe('pl')
    expect(back.find((l) => l.hreflang === 'en')).toMatchObject({
      path: UNTRANSLATED,
      fallback: false,
    })
    expect(back.find((l) => l.hreflang === 'ru')).toMatchObject({
      path: `/ru${UNTRANSLATED}`,
      fallback: false,
    })
  })

  // THEMES §4: a missing address is answered in the site's frame — theme-default's errors/404 —
  // so the switcher is there, and leads to each language's home page, saying so.
  test('leads to the home page of each language from a page that does not exist, and marks it', async ({
    page,
  }) => {
    for (const [path, lang, home] of [
      ['/no-such-page', 'en', '/'],
      ['/ru/no-such-page', 'ru', '/ru'],
    ]) {
      const response = await page.goto(path)
      expect(response.status(), path).toBe(404)
      expect(await page.locator('html').getAttribute('lang'), path).toBe(lang)
      await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex')
      await expect(page.locator('.site-footer')).toHaveCount(1)
      expect(new URL(await page.locator('.site-error__home').getAttribute('href')).pathname).toBe(
        home,
      )

      const all = await links(header(page))
      expect(all.map((l) => l.hreflang)).toEqual(['en', 'ru', 'pl'])
      expect(all.find((l) => l.current === 'page')).toMatchObject({ hreflang: lang, path })
      for (const link of all.filter((l) => l.hreflang !== lang)) {
        expect(link).toMatchObject({
          path: link.hreflang === 'en' ? '/' : `/${link.hreflang}`,
          fallback: true,
          current: null,
        })
        expect(link.title, `the fallback to ${link.hreflang} says nothing`).toMatch(/\S/)
        await answers(page, link)
      }
    }
  })

  test('its dropdown stays in the window at 1280, and in the menu at 360', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto(PATH)
    await still(page)
    await header(page).locator('.webx-language-switcher__current').click()
    const panel = header(page).locator('.webx-dropdown__panel')
    await expect(panel).toBeVisible()
    await inWindow(panel)
    await expect(header(page).locator('.webx-language-switcher__current')).toContainText('English')

    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)
    await page.locator('.webx-mobile-menu__trigger').click()
    const inMenu = page.locator('.webx-mobile-menu__bottom .webx-language-switcher')
    await inMenu.locator('.webx-language-switcher__current').click()
    const menuPanel = inMenu.locator('.webx-dropdown__panel')
    await expect(menuPanel).toBeVisible()
    await inWindow(menuPanel)
    // Every language is reachable with a finger.
    for (const link of await menuPanel.locator('.webx-language-switcher__link').all()) {
      expect((await link.boundingBox()).height).toBeGreaterThanOrEqual(44)
    }
    const { scroll, client } = await page.evaluate(() => ({
      scroll: document.documentElement.scrollWidth,
      client: document.documentElement.clientWidth,
    }))
    expect(scroll, 'the page scrolls sideways').toBeLessThanOrEqual(client)
  })

  test('opens from the keyboard and gives the focus back on Esc', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto(PATH)
    const trigger = header(page).locator('.webx-language-switcher__current')
    await trigger.focus()
    await page.keyboard.press('Enter')
    await expect(header(page).locator('.webx-dropdown__panel')).toBeVisible()
    await page.keyboard.press('Tab')
    await expect(header(page).locator('.webx-language-switcher__link').first()).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(header(page).locator('.webx-dropdown__panel')).toBeHidden()
    await expect(trigger).toBeFocused()
  })
})

test.describe('slider', () => {
  const PATH = '/kitchen-sink/slider'

  // Answered already: the banner would stand over the sliders at the bottom of a phone.
  const answered = async (context) => {
    const url = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local')
    await context.addCookies([
      {
        name: 'webx_consent',
        value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: [] })),
        domain: url.hostname,
        path: '/',
      },
    ])
  }

  // Slider `index` on the page: its width, per view as the browser chose it and as Swiper took
  // it, and how many of its slides are on its screen — wholly and at all.
  const measure = (page, index) =>
    page.evaluate((i) => {
      const root = document.querySelectorAll('.webx-slider')[i]
      const viewport = root.querySelector('.webx-slider__viewport')
      const frame = viewport.getBoundingClientRect()
      const slides = [...root.querySelectorAll('.webx-slider__slide:not([aria-hidden])')]
      const inside = (slide, whole) => {
        // A faded slide stands where the current one does: out of view is opacity 0.
        if (getComputedStyle(slide).opacity === '0') return false
        const box = slide.getBoundingClientRect()
        return whole
          ? box.left >= frame.left - 1 && box.right <= frame.right + 1
          : box.right > frame.left + 1 && box.left < frame.right - 1
      }
      const track = root.querySelector('.webx-slider__track')
      return {
        width: frame.width,
        perView: parseFloat(getComputedStyle(track).getPropertyValue('--webx-slider-per-view')),
        swiper: viewport.swiper?.params.slidesPerView,
        gap: parseFloat(getComputedStyle(track).rowGap),
        slide: slides[0].getBoundingClientRect().width,
        whole: slides.filter((slide) => inside(slide, true)).length,
        seen: slides.filter((slide) => inside(slide, false)).length,
      }
    }, index)

  // The page's sliders, in order: cards, cards narrow, hero, gallery, gallery narrow, logos, one slide.
  const CARDS = 0
  const NARROW = 1
  const HERO = 2
  const GALLERY = 3

  test('shows as many slides as its container has room for, in a narrow column and at full width', async ({
    page,
    context,
  }) => {
    await answered(context)
    await page.setViewportSize({ width: 1400, height: 900 })
    await page.goto(PATH)
    await still(page)

    // Cards at full width: three per view from a 960px container, two from 640, 1.2 below.
    // In the 20rem column: 1.2 whatever the window. One window, resized — the script follows.
    for (const [width, full] of [
      [1400, 3],
      [900, 2],
      [375, 1.2],
      [1280, 3],
    ]) {
      await page.setViewportSize({ width, height: 900 })
      await expect
        .poll(async () => (await measure(page, CARDS)).swiper, { message: `per view at ${width}` })
        .toBe(full)

      for (const [index, perView] of [
        [CARDS, full],
        [NARROW, 1.2],
      ]) {
        // Swiper's width of a slide: the frame less the gaps between the slides in view. A frame
        // that changed width is measured again by Swiper on its next frame — so, polled.
        await expect
          .poll(
            async () => {
              const m = await measure(page, index)
              return Math.abs(m.slide - (m.width - (perView - 1) * m.gap) / perView) < 0.5
            },
            { message: `the width of a slide at ${width}` },
          )
          .toBe(true)
        const m = await measure(page, index)
        expect(m.perView, `the container query at ${width}`).toBe(perView)
        expect(m.whole, `whole slides at ${width}`).toBe(Math.floor(perView))
        expect(m.seen, `slides in view at ${width}`).toBe(Math.ceil(perView))
      }

      // The hero follows the new width on its own next frame, like the cards: polled.
      await expect
        .poll(
          async () => {
            const hero = await measure(page, HERO)
            return [hero.whole, hero.seen]
          },
          { message: `the hero at ${width}` },
        )
        .toEqual([1, 1])
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        ),
        `no horizontal scroll at ${width}`,
      ).toBe(0)
    }
  })

  test('without JavaScript is a strip that scrolls sideways and snaps', async ({ browser }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 1280, height: 900 },
    })
    const page = await context.newPage()
    await page.goto(PATH)

    const strip = await page.evaluate(() =>
      [...document.querySelectorAll('.webx-slider__track')].map((track) => {
        const before = track.scrollLeft
        // To the end: a snap would pull a short scroll back to the first slide.
        track.scrollLeft = track.scrollWidth
        const style = getComputedStyle(track)
        const first = track.querySelector('.webx-slider__slide').getBoundingClientRect().width
        return {
          scrolls: track.scrollWidth > track.clientWidth,
          moved: track.scrollLeft > before,
          overflow: style.overflowX,
          snap: style.scrollSnapType,
          perView: parseFloat(style.getPropertyValue('--webx-slider-per-view')),
          gap: parseFloat(style.columnGap),
          width: track.clientWidth,
          first,
        }
      }),
    )
    const controls = await page
      .locator('.webx-slider__controls')
      .evaluateAll((all) => all.map((c) => c.hidden))

    // Every slider with more slides than fit scrolls; the one-slide one has nothing to scroll.
    for (const track of strip.slice(0, 6)) {
      expect(track.overflow).toBe('auto')
      expect(track.snap).toContain('x')
      expect(track.scrolls).toBe(true)
      expect(track.moved).toBe(true)
      expect(track.first).toBeCloseTo(
        (track.width - (track.perView - 1) * track.gap) / track.perView,
        0,
      )
    }
    expect(strip[CARDS].perView).toBe(3)
    expect(controls.every(Boolean)).toBe(true)
    expect(await page.locator('.webx-slider__thumbs').count()).toBe(0)
    await context.close()
  })

  test('moves by its arrows, and by the keyboard only the slider the focus is in', async ({
    page,
    context,
  }) => {
    await answered(context)
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto(PATH)
    await still(page)

    const index = (i) =>
      page.evaluate(
        (n) => document.querySelectorAll('.webx-slider__viewport')[n].swiper.realIndex,
        i,
      )
    const cards = page.locator('.webx-slider').nth(CARDS)

    // Three per view: the arrow moves a view.
    await cards.locator('.webx-slider__next').click()
    await expect.poll(() => index(CARDS)).toBe(3)
    await expect(cards.locator('.webx-slider__slide').nth(3)).toHaveClass(/is-active/)

    await page.locator('.webx-slider').nth(NARROW).locator('.webx-slider__slide a').first().focus()
    await page.keyboard.press('ArrowRight')
    await expect.poll(() => index(NARROW)).toBe(1)
    await page.keyboard.press('End')
    await expect.poll(() => index(NARROW)).toBe(7)
    expect(await index(CARDS)).toBe(3)

    // Eight dots in a 20rem column wrap among themselves; the arrows stay on their row.
    const narrow = page.locator('.webx-slider').nth(NARROW)
    const prev = await narrow.locator('.webx-slider__prev').boundingBox()
    const next = await narrow.locator('.webx-slider__next').boundingBox()
    const frame = await narrow.boundingBox()
    expect(Math.abs(prev.y - next.y)).toBeLessThan(1)
    expect(next.x + next.width).toBeLessThanOrEqual(frame.x + frame.width + 0.5)

    // Named for a screen reader; the live region is hidden, not a stray line of text.
    await expect(cards.locator('.webx-slider__slide').nth(1)).toHaveAttribute('aria-label', '2 / 8')
    const notice = await cards.locator('.webx-slider__notice').boundingBox()
    expect(notice.width).toBeLessThanOrEqual(1)
  })

  test('what turns on its own has a pause button, and does not start with reduced motion', async ({
    browser,
  }) => {
    for (const reducedMotion of ['no-preference', 'reduce']) {
      const context = await browser.newContext({
        reducedMotion,
        viewport: { width: 1280, height: 900 },
      })
      await answered(context)
      const page = await context.newPage()
      await page.goto(PATH)

      const running = () =>
        page.evaluate(
          (n) => document.querySelectorAll('.webx-slider__viewport')[n].swiper.autoplay.running,
          HERO,
        )
      const pause = page.locator('.webx-slider').nth(HERO).locator('.webx-slider__pause')
      await expect(pause).toBeVisible()
      const box = await pause.boundingBox()
      expect(box.height).toBeGreaterThanOrEqual(44)

      if (reducedMotion === 'reduce') {
        expect(await running()).toBe(false)
        await expect(pause).toHaveAttribute('aria-label', 'Play')
      } else {
        expect(await running()).toBe(true)
        await pause.click()
        expect(await running()).toBe(false)
        await expect(pause).toHaveAttribute('aria-label', 'Play')
        // The logos run too, with nothing but a pause button.
        const logos = page.locator('.webx-slider--logos')
        await expect(logos.locator('.webx-slider__pause')).toBeVisible()
        expect(await logos.locator('.webx-slider__prev, .webx-slider__bullet').count()).toBe(0)
      }
      await context.close()
    }
  })

  test('a thumbnail of the gallery picks its picture and says it is current', async ({
    page,
    context,
  }) => {
    await answered(context)
    await page.setViewportSize({ width: 375, height: 800 })
    await page.goto(PATH)
    await still(page)

    const gallery = page.locator('.webx-slider').nth(GALLERY)
    const thumbs = gallery.locator('.webx-slider__thumb')
    await expect(thumbs).toHaveCount(6)
    const box = await thumbs.first().boundingBox()
    expect(box.width).toBeGreaterThanOrEqual(44)

    await thumbs.nth(2).click()
    await expect
      .poll(() =>
        page.evaluate(
          (n) => document.querySelectorAll('.webx-slider__viewport')[n].swiper.realIndex,
          GALLERY,
        ),
      )
      .toBe(2)
    await expect(thumbs.nth(2)).toHaveAttribute('aria-current', 'true')
    await expect(thumbs.nth(0)).not.toHaveAttribute('aria-current', /.*/)

    // Only the first picture of a slider loads at once; the rest wait for the screen.
    expect(
      await gallery.locator('.webx-slider__slide img').first().getAttribute('loading'),
    ).toBeNull()
    expect(await gallery.locator('.webx-slider__slide img').nth(1).getAttribute('loading')).toBe(
      'lazy',
    )
  })
})

test.describe('lightbox', () => {
  const PATH = '/kitchen-sink/lightbox'

  // Answered already: the banner would stand over the pictures at the bottom of a phone.
  const answered = async (context) => {
    const url = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local')
    await context.addCookies([
      {
        name: 'webx_consent',
        value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: [] })),
        domain: url.hostname,
        path: '/',
      },
    ])
  }

  const viewer = (page) => page.locator('.pswp')
  const counter = (page) => page.locator('.pswp__counter')

  // The picture on screen: not the placeholder, in the slide that is shown.
  const picture = (page) =>
    page.evaluate(() => {
      const image = document.querySelector(
        '.pswp__item[aria-hidden="false"] img.pswp__img:not(.pswp__img--placeholder)',
      )
      if (!image?.complete) return null
      const box = image.getBoundingClientRect()
      return {
        left: box.left,
        top: box.top,
        right: box.right,
        bottom: box.bottom,
        width: box.width,
        height: box.height,
      }
    })

  // The block of the page by its heading: the gallery block as a grid and as a slider, and the
  // showcase's links by hand.
  const section = (page, heading) =>
    page
      .locator('[data-wx-block]')
      .filter({ has: page.getByRole('heading', { name: heading, exact: true }) })

  test('on a phone the picture fits the screen, a swipe and an arrow page on, Esc gives the focus back', async ({
    page,
    context,
  }) => {
    await answered(context)
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize({ width: 375, height: 667 })
    await page.goto(PATH)

    const links = section(page, 'Grid').locator('a[data-webx-lightbox]')
    await expect(links).toHaveCount(6)
    await expect(links.first()).toHaveAttribute('aria-haspopup', 'dialog')
    await links.first().click()

    await expect(viewer(page)).toBeVisible()
    await expect(viewer(page)).toHaveAttribute('role', 'dialog')
    await expect(viewer(page)).toHaveAttribute('aria-modal', 'true')
    await expect(viewer(page)).toHaveAttribute('aria-label', /.+/)
    await expect(counter(page)).toHaveText('1 / 6')

    // 1800 × 1200 on 375 × 667: the width of the screen, all of it on the screen.
    await expect.poll(() => picture(page)).not.toBeNull()
    const wide = await picture(page)
    expect(wide.left).toBeGreaterThanOrEqual(-1)
    expect(wide.top).toBeGreaterThanOrEqual(-1)
    expect(wide.right).toBeLessThanOrEqual(376)
    expect(wide.bottom).toBeLessThanOrEqual(668)
    expect(wide.width).toBeGreaterThan(370)
    expect(wide.width / wide.height).toBeCloseTo(1.5, 1)

    // A swipe to the left: the next of the group.
    await page.mouse.move(300, 333)
    await page.mouse.down()
    for (let x = 280; x >= 40; x -= 40) await page.mouse.move(x, 336)
    await page.mouse.up()
    await expect(counter(page)).toHaveText('2 / 6')

    await page.keyboard.press('ArrowRight')
    await expect(counter(page)).toHaveText('3 / 6')

    // Nothing sideways, the viewer open or closed.
    const sideways = () =>
      page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
      )
    expect(await sideways()).toBe(false)

    await page.keyboard.press('Escape')
    await expect(viewer(page)).toHaveCount(0)
    // Back on the picture last shown, so Tab goes on from there.
    expect(await links.nth(2).evaluate((a) => a === document.activeElement)).toBe(true)
    expect(await sideways()).toBe(false)

    // Taller than wide: it fits by its height.
    await section(page, 'Links by hand').locator('a').first().click()
    await expect(viewer(page)).toBeVisible()
    await expect.poll(() => picture(page)).not.toBeNull()
    const tall = await picture(page)
    expect(tall.top).toBeGreaterThanOrEqual(-1)
    expect(tall.bottom).toBeLessThanOrEqual(668)
    expect(tall.right).toBeLessThanOrEqual(376)
    expect(tall.width / tall.height).toBeCloseTo(0.6, 1)
    // On its own: no counter, nothing to page to.
    await expect(counter(page)).toBeHidden()
  })

  test('asks nothing of anybody but the site, and measures a picture that came without sizes', async ({
    page,
    context,
  }) => {
    await answered(context)
    const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin
    const outside = []
    page.on('request', (request) => {
      const url = request.url()
      if (!url.startsWith('data:') && new URL(url).origin !== site) outside.push(url)
    })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto(PATH)

    // Opened from the keyboard.
    await section(page, 'Grid').locator('a').first().focus()
    await page.keyboard.press('Enter')
    await expect(counter(page)).toHaveText('1 / 6')
    await page.keyboard.press('ArrowLeft')
    await expect(counter(page)).toHaveText('6 / 6')
    await page.keyboard.press('Escape')
    await expect(viewer(page)).toHaveCount(0)

    // No data-width, no data-height: measured before it opens — 1800 × 1200 at its proportions.
    await section(page, 'Links by hand').locator('a').nth(1).click()
    await expect.poll(() => picture(page)).not.toBeNull()
    const bare = await picture(page)
    expect(bare.width / bare.height).toBeCloseTo(1.5, 1)
    expect(bare.height).toBeGreaterThan(780)
    await page.keyboard.press('Escape')

    await page.waitForLoadState('networkidle')
    expect(outside).toEqual([])
  })

  test("the gallery slider: a click opens the slide's picture, a drag only moves the slider", async ({
    page,
    context,
  }) => {
    await answered(context)
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto(PATH)

    const gallery = section(page, 'Gallery slider')
    const viewport = gallery.locator('.webx-slider__viewport')
    const index = () => viewport.evaluate((el) => el.swiper.realIndex)
    await viewport.scrollIntoViewIfNeeded()
    await expect.poll(() => viewport.evaluate((el) => Boolean(el.swiper))).toBe(true)

    // A drag across the picture moves the slider and opens nothing.
    const box = await viewport.boundingBox()
    const y = box.y + box.height / 2
    await page.mouse.move(box.x + box.width * 0.8, y)
    await page.mouse.down()
    for (let x = box.x + box.width * 0.7; x >= box.x + box.width * 0.2; x -= box.width * 0.1) {
      await page.mouse.move(x, y)
    }
    await page.mouse.up()
    await expect.poll(index).toBe(1)
    await page.waitForTimeout(100)
    await expect(viewer(page)).toHaveCount(0)

    // A click on the slide opens its picture, in the group of the slider.
    await gallery.locator('.webx-slider__slide.is-active a[data-webx-lightbox]').click()
    await expect(counter(page)).toHaveText('2 / 6')
    await page.keyboard.press('ArrowRight')
    await expect(counter(page)).toHaveText('3 / 6')

    // Closed on the third: the slider comes to it, and the focus is on its link.
    await page.keyboard.press('Escape')
    await expect(viewer(page)).toHaveCount(0)
    await expect.poll(index).toBe(2)
    expect(
      await gallery
        .locator('.webx-slider__slide.is-active a[data-webx-lightbox]')
        .evaluate((a) => a === document.activeElement),
    ).toBe(true)
  })

  test('without JavaScript a picture is a link that opens its file', async ({ browser }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 375, height: 667 },
    })
    const page = await context.newPage()
    await page.goto(PATH)

    const hrefs = await page
      .locator('a[data-webx-lightbox]')
      .evaluateAll((links) => links.map((a) => a.href))
    expect(hrefs.length).toBeGreaterThanOrEqual(14)

    // A picture of the media library: the JPEG of the demo, made a WebP on the way in.
    const file = await page.request.get(hrefs[0])
    expect(file.status()).toBe(200)
    expect(file.headers()['content-type']).toContain('image/webp')

    // Neither the script nor the words of a page that has no lightbox.
    expect(await page.locator('script[src*="lightbox.js"]').count()).toBe(1)
    const plain = await context.newPage()
    await plain.goto('/kitchen-sink/slider')
    expect(await plain.locator('script[src*="lightbox.js"], #webx-lightbox').count()).toBe(0)
    await context.close()
  })
})

test.describe('gallery and logos blocks', () => {
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  // Answered already: the banner would stand over the blocks at the bottom of a phone.
  const answered = async (context) => {
    const url = new URL(site)
    await context.addCookies([
      {
        name: 'webx_consent',
        value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: [] })),
        domain: url.hostname,
        path: '/',
      },
    ])
  }

  const block = (page, type, heading) =>
    page
      .locator(`[data-wx-block="${type}"]`)
      .filter({ has: page.getByRole('heading', { name: heading, exact: true }) })

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  // Every picture of the blocks is a file of the site — the library's, through its own address.
  const pictures = (page) =>
    page.evaluate(() =>
      [
        ...document.querySelectorAll('[data-wx-block="gallery"] img, [data-wx-block="logos"] img'),
      ].map((img) => img.currentSrc || img.src),
    )

  test('the grid takes as many columns as its column has room for, and opens the lightbox', async ({
    page,
    context,
  }) => {
    await answered(context)
    await page.emulateMedia({ reducedMotion: 'reduce' })
    const outside = []
    page.on('request', (request) => {
      const url = request.url()
      if (!url.startsWith('data:') && new URL(url).origin !== site) outside.push(url)
    })

    // Three asked for: three on a wide page, two where a cell would be narrower than 9em.
    for (const [width, columns] of [
      [1280, 3],
      [375, 2],
    ]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto('/kitchen-sink/lightbox')
      await still(page)

      const grid = block(page, 'gallery', 'Grid')
      const measured = await grid.locator('.b-gallery__grid').evaluate((list) => {
        const cells = [...list.querySelectorAll('.b-gallery__cell')]
        const box = list.getBoundingClientRect()
        return {
          columns: getComputedStyle(list).gridTemplateColumns.split(' ').length,
          rows: new Set(cells.map((cell) => Math.round(cell.getBoundingClientRect().top))).size,
          widths: cells.map((cell) => Math.round(cell.getBoundingClientRect().width)),
          inside: cells.every((cell) => {
            const c = cell.getBoundingClientRect()
            return c.left >= box.left - 0.5 && c.right <= box.right + 0.5
          }),
          square: cells.every((cell) => {
            const img = cell.querySelector('img').getBoundingClientRect()
            return Math.abs(img.width - img.height) < 1
          }),
        }
      })
      expect(measured.columns, `columns at ${width}`).toBe(columns)
      expect(measured.rows, `rows at ${width}`).toBe(Math.ceil(6 / columns))
      expect(new Set(measured.widths).size, `cells of one width at ${width}`).toBe(1)
      expect(measured.inside).toBe(true)
      expect(measured.square).toBe(true)
      expect(await sideways(page), `no horizontal scroll at ${width}`).toBe(0)

      // The title of a picture in the library is its caption.
      await expect(grid.locator('figcaption').first()).toHaveText(/caption from the library/)

      // A cell opens its picture over the page, among the six of its block.
      await grid.locator('a[data-webx-lightbox]').nth(1).click()
      await expect(page.locator('.pswp__counter')).toHaveText('2 / 6')
      await page.keyboard.press('Escape')
      await expect(page.locator('.pswp')).toHaveCount(0)
    }

    // One group per block: the grid and the slider do not page into each other.
    const groups = await page
      .locator('[data-wx-block="gallery"] a[data-webx-lightbox]')
      .evaluateAll((links) => [...new Set(links.map((a) => a.dataset.webxLightbox))])
    expect(groups).toHaveLength(2)

    for (const src of await pictures(page)) expect(new URL(src).origin).toBe(site)
    await page.waitForLoadState('networkidle')
    expect(outside).toEqual([])
  })

  test('as a slider it shows one picture to the width, its thumbnails and the lightbox', async ({
    page,
    context,
  }) => {
    await answered(context)
    await page.emulateMedia({ reducedMotion: 'reduce' })

    for (const width of [375, 1280]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto('/kitchen-sink/lightbox')
      await still(page)

      const gallery = block(page, 'gallery', 'Gallery slider')
      const viewport = gallery.locator('.webx-slider__viewport')
      await viewport.scrollIntoViewIfNeeded()
      await expect.poll(() => viewport.evaluate((el) => Boolean(el.swiper))).toBe(true)

      const m = await gallery.evaluate((root) => {
        const frame = root.querySelector('.webx-slider__viewport').getBoundingClientRect()
        const slide = root.querySelector('.webx-slider__slide').getBoundingClientRect()
        const thumb = root.querySelector('.webx-slider__thumb')?.getBoundingClientRect()
        return {
          frame: frame.width,
          slide: slide.width,
          thumb: thumb?.width ?? 0,
          block: root.getBoundingClientRect().width,
        }
      })
      expect(Math.abs(m.slide - m.frame), `one picture to the width at ${width}`).toBeLessThan(1)
      expect(m.frame).toBeLessThanOrEqual(m.block + 0.5)
      expect(m.thumb).toBeGreaterThanOrEqual(44)
      await expect(gallery.locator('.webx-slider__thumb')).toHaveCount(6)
      expect(await sideways(page), `no horizontal scroll at ${width}`).toBe(0)

      // The picture of the slide opens over the page; the thumbnails come from the library too.
      await gallery.locator('.webx-slider__slide.is-active a[data-webx-lightbox]').click()
      await expect(page.locator('.pswp__counter')).toHaveText('1 / 6')
      await page.keyboard.press('Escape')
      await expect(page.locator('.pswp')).toHaveCount(0)
      const thumbs = await gallery
        .locator('.webx-slider__thumb img')
        .evaluateAll((all) => all.map((img) => img.currentSrc || img.src))
      expect(thumbs).toHaveLength(6)
      for (const src of thumbs) expect(new URL(src).origin).toBe(site)
    }
  })

  test('the logos run, stop for a pause button and for the focus, and are links by the keyboard', async ({
    page,
    context,
  }) => {
    await answered(context)

    for (const [width, perView] of [
      [1280, 6],
      [375, 2.5],
    ]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto('/kitchen-sink/slider')

      const logos = block(page, 'logos', 'Logos')
      const viewport = logos.locator('.webx-slider__viewport')
      await viewport.scrollIntoViewIfNeeded()
      await expect
        .poll(() => viewport.evaluate((el) => el.swiper?.params.slidesPerView))
        .toBe(perView)

      // Running: the strip moves on its own.
      const offset = () => viewport.evaluate((el) => el.swiper.getTranslate())
      const before = await offset()
      await expect.poll(offset, { message: `the strip runs at ${width}` }).not.toBe(before)
      expect(await sideways(page), `no horizontal scroll at ${width}`).toBe(0)

      // A pause button a finger can hit, and nothing else to press.
      const pause = logos.locator('.webx-slider__pause')
      const box = await pause.boundingBox()
      expect(box.width).toBeGreaterThanOrEqual(44)
      expect(box.height).toBeGreaterThanOrEqual(44)
      expect(await logos.locator('.webx-slider__prev, .webx-slider__bullet').count()).toBe(0)

      // Every logo with a link is reached by Tab, once — the copies of the strip are inert —
      // and the strip holds still while the focus is inside.
      const links = await logos
        .locator('a.b-logos__item')
        .evaluateAll((all) =>
          all.filter((a) => !a.closest('[inert]')).map((a) => a.getAttribute('href')),
        )
      // In the strip's order, from whichever logo the running loop has put first in the DOM.
      const order = ['#logo-1', '#logo-3', '#logo-5', '#logo-7']
      const round = (from) => [...order.slice(from), ...order.slice(0, from)]
      expect(links).toEqual(round(order.indexOf(links[0])))
      await logos.locator('h2').evaluate((h) => {
        h.tabIndex = -1
        h.focus()
      })
      // A press per frame, as a person types: Swiper's A11y brings a slide past the edge in on
      // the next frame, and its loop moves slides in the DOM. Two presses inside one frame let
      // the move meant for the previous link land on the focused one, which drops the focus to
      // <body> — a test's speed, not a visitor's. The focus is read after the move.
      const reached = []
      for (let i = 0; i < links.length; i++) {
        await page.keyboard.press('Tab')
        reached.push(
          await page.evaluate(
            () =>
              new Promise((settled) =>
                requestAnimationFrame(() =>
                  requestAnimationFrame(() => settled(document.activeElement.getAttribute('href'))),
                ),
              ),
          ),
        )
      }
      // The running loop rotates the slides in the DOM, so Tab enters at whichever logo leads
      // the strip at that moment and goes round from there: each once, in the strip's order.
      expect(order, `Tab enters the strip on a link at ${width}`).toContain(reached[0])
      expect(reached).toEqual(round(order.indexOf(reached[0])))
      expect(await viewport.evaluate((el) => el.swiper.autoplay.paused)).toBe(true)

      // The button stops it for good, wherever the focus goes.
      await pause.click()
      await expect(pause).toHaveAttribute('aria-label', 'Play')
      await page.mouse.click(1, 1)
      expect(await viewport.evaluate((el) => el.swiper.autoplay.running)).toBe(false)
    }

    for (const src of await pictures(page)) expect(new URL(src).origin).toBe(site)
  })

  test('without JavaScript the logos are a strip that scrolls, the grid a grid of links', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 375, height: 800 },
    })
    const page = await context.newPage()
    await page.goto('/kitchen-sink/slider')

    const logos = block(page, 'logos', 'Logos')
    const strip = await logos.locator('.webx-slider__track').evaluate((track) => {
      const before = track.scrollLeft
      track.scrollLeft = track.scrollWidth
      return {
        overflow: getComputedStyle(track).overflowX,
        scrolls: track.scrollWidth > track.clientWidth,
        moved: track.scrollLeft > before,
        slides: track.querySelectorAll('.webx-slider__slide').length,
      }
    })
    expect(strip).toEqual({ overflow: 'auto', scrolls: true, moved: true, slides: 8 })
    expect(await logos.locator('.webx-slider__pause').isHidden()).toBe(true)
    expect(await sideways(page)).toBe(0)

    await page.goto('/kitchen-sink/lightbox')
    const hrefs = await block(page, 'gallery', 'Grid')
      .locator('a')
      .evaluateAll((all) => all.map((a) => a.href))
    expect(hrefs).toHaveLength(6)
    const file = await page.request.get(hrefs[0])
    expect(file.status()).toBe(200)
    expect(file.headers()['content-type']).toContain('image/')
    await context.close()
  })
})

test.describe('video', () => {
  const PATH = '/kitchen-sink/video'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin
  const THIRD =
    /(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be|ytimg\.com|googlevideo\.com|vimeo\.com|vimeocdn\.com)$/

  const consent = (c) => ({
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c })),
    domain: new URL(site).hostname,
    path: '/',
  })

  // The providers answered here, so the run does not depend on the network, and every request
  // to them is written down — before consent there must be none at all.
  async function providers(context) {
    const asked = []
    await context.route(
      (url) => THIRD.test(url.hostname),
      (route) => {
        asked.push(route.request().url())
        return route.fulfill({ body: '<p>player</p>', contentType: 'text/html' })
      },
    )
    return asked
  }

  const section = (page, heading) =>
    page
      .locator('[data-wx-block="video"], [data-wx-block="showcase-video"]')
      .filter({ has: page.getByRole('heading', { name: heading, exact: true }) })

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  test('before consent nothing is asked of YouTube or Vimeo; the placeholder keeps its ratio and fits', async ({
    browser,
  }) => {
    const context = await browser.newContext()
    const asked = await providers(context)
    const page = await context.newPage()
    const outside = []
    page.on('request', (request) => {
      const url = request.url()
      if (!url.startsWith('data:') && new URL(url).origin !== site) outside.push(url)
    })

    for (const width of [375, 1280]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto(PATH)
      await page.waitForLoadState('networkidle')
      await still(page)

      const frames = await page.locator('.webx-video.is-blocked').evaluateAll((all) =>
        all.map((video) => {
          const box = video.getBoundingClientRect()
          const [w, h] = getComputedStyle(video).aspectRatio.split('/').map(Number)
          const notice = video.querySelector('.webx-video__consent')
          const inside = [...notice.querySelectorAll('button, p')].every((el) => {
            const b = el.getBoundingClientRect()
            return (
              b.left >= box.left - 0.5 &&
              b.right <= box.right + 0.5 &&
              b.top >= box.top - 0.5 &&
              b.bottom <= box.bottom + 0.5
            )
          })
          return {
            ratio: box.width / box.height,
            expected: w / (h || 1),
            width: box.width,
            fits: notice.scrollHeight <= notice.clientHeight + 1 && inside,
            buttons: [...notice.querySelectorAll('button')].map(
              (b) => b.getBoundingClientRect().height,
            ),
            posters: [...video.querySelectorAll('img')].map((img) => img.currentSrc || img.src),
          }
        }),
      )

      // YouTube with a poster, without, Vimeo, the narrow column, the 4:3 one, the upright one.
      expect(frames.length, `at ${width}`).toBe(6)
      for (const frame of frames) {
        expect(frame.ratio, `at ${width}`).toBeCloseTo(frame.expected, 1)
        expect(frame.fits, `the notice fits a frame ${Math.round(frame.width)}px wide`).toBe(true)
        expect(Math.min(...frame.buttons)).toBeGreaterThanOrEqual(44)
        for (const poster of frame.posters) expect(new URL(poster).origin).toBe(site)
      }
      expect(await sideways(page), `at ${width}`).toBe(0)
    }

    expect(asked, 'the providers, before any answer').toEqual([])
    expect(outside, 'anybody but the site').toEqual([])
    await context.close()
  })

  test('"Load" plays that one only; "Always load videos" agrees to media and unblocks the page without a reload', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } })
    // Answered without media: the banner stays out of the way, the videos wait.
    await context.addCookies([consent([])])
    const asked = await providers(context)
    const page = await context.newPage()
    await page.goto(PATH)
    await page.evaluate(() => (window.notReloaded = true))

    const bunny = section(page, 'YouTube, no poster')
    await bunny.locator('[data-webx-video-load]').click()
    const player = bunny.locator('iframe.webx-video__frame')
    await expect(player).toHaveAttribute(
      'src',
      /^https:\/\/www\.youtube-nocookie\.com\/embed\/aqz-KE-bpKQ\?autoplay=1/,
    )
    expect(await page.evaluate(() => document.activeElement?.className)).toBe('webx-video__frame')
    expect(await page.locator('.webx-video.is-blocked').count(), 'the others still wait').toBe(5)
    expect(
      decodeURIComponent((await context.cookies()).find((c) => c.name === 'webx_consent').value),
    ).toContain('"c":[]')

    const vimeo = section(page, 'Vimeo, no poster')
    await vimeo.locator('[data-webx-video-always]').click()
    await expect(vimeo.locator('iframe.webx-video__frame')).toHaveAttribute(
      'src',
      /player\.vimeo\.com\/video\/1084537\?.*dnt=1/,
    )
    await expect(page.locator('.webx-video.is-blocked')).toHaveCount(0)
    await expect(page.locator('.webx-video__consent')).toHaveCount(0)
    // The rest are facades now: a play button each, and no player until it is pressed.
    expect(await page.locator('iframe.webx-video__frame').count()).toBe(2)
    expect(await page.locator('button.webx-video__facade').count()).toBe(4)
    expect(await page.evaluate(() => window.notReloaded)).toBe(true)
    expect(
      decodeURIComponent((await context.cookies()).find((c) => c.name === 'webx_consent').value),
    ).toContain('"c":["media"]')
    expect(
      asked.every((url) => /youtube-nocookie\.com\/embed|player\.vimeo\.com\/video/.test(url)),
    ).toBe(true)
    await context.close()
  })

  test('with consent: a play button, the player only after it is pressed, the focus in it', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } })
    await context.addCookies([consent(['media'])])
    const asked = await providers(context)
    const page = await context.newPage()
    await page.goto(PATH)
    await page.waitForLoadState('networkidle')

    expect(await page.locator('.webx-video__consent').count()).toBe(0)
    expect(await page.locator('iframe').count()).toBe(0)
    expect(asked, 'a facade asks for nothing').toEqual([])

    // From the keyboard: Tab reaches the first play button, a real button with a name.
    let focused = null
    for (let i = 0; i < 80 && !focused?.facade; i++) {
      await page.keyboard.press('Tab')
      focused = await page.evaluate(() => ({
        facade: document.activeElement?.classList.contains('webx-video__facade'),
        tag: document.activeElement?.tagName,
        name: document.activeElement?.getAttribute('aria-label'),
      }))
    }
    expect(focused).toEqual({
      facade: true,
      tag: 'BUTTON',
      name: 'Play: YouTube, a poster from the library',
    })
    await page.keyboard.press('Enter')

    const player = section(page, 'YouTube, a poster from the library').locator(
      'iframe.webx-video__frame',
    )
    await expect(player).toHaveAttribute('src', /youtube-nocookie\.com\/embed\/eRsGyueVLvQ/)
    expect(await page.evaluate(() => document.activeElement?.className)).toBe('webx-video__frame')
    expect(await player.evaluate((el) => el.title)).toBe('YouTube, a poster from the library')
    await expect.poll(() => asked.length).toBe(1)
    await context.close()
  })

  test('a video of the site asks for no consent and loads nothing before Play', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } })
    await context.addCookies([consent([])])
    const page = await context.newPage()
    const clips = []
    page.on('request', (request) => {
      if (/\.(mp4|webm)(\?|$)/.test(request.url())) clips.push(request.url())
    })
    await page.goto(PATH)
    await page.waitForLoadState('networkidle')

    const own = section(page, 'A video of the site')
    const video = own.locator('video.webx-video__player')
    expect(await own.locator('.webx-video').getAttribute('class')).toBe(
      'webx-video webx-video--file',
    )
    expect(await own.locator('[data-webx-consent]').count()).toBe(0)
    expect(await video.evaluate((v) => [v.preload, v.controls, new URL(v.poster).origin])).toEqual([
      'none',
      true,
      new URL(site).origin,
    ])
    const box = await own.locator('.webx-video').boundingBox()
    expect(box.width / box.height).toBeCloseTo(16 / 9, 1)
    expect(clips, 'preload none').toEqual([])

    const played = await video.evaluate(async (v) => {
      v.muted = true
      await v.play()
      await new Promise((resolve) => setTimeout(resolve, 600))
      return v.currentTime
    })
    expect(played).toBeGreaterThan(0)
    expect(clips.length).toBeGreaterThan(0)
    for (const clip of clips) expect(new URL(clip).origin).toBe(site)
    // The frame did not move when the video came.
    expect((await own.locator('.webx-video').boundingBox()).height).toBeCloseTo(box.height, 0)
    await context.close()
  })

  test('the Video block: the caption under its frame, an upright one held to a phone’s width in the middle', async ({
    browser,
  }) => {
    const context = await browser.newContext()
    await context.addCookies([consent([])])
    const asked = await providers(context)
    const page = await context.newPage()

    for (const width of [375, 1280]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto(PATH)
      await page.waitForLoadState('networkidle')
      await still(page)

      const blocks = await page.locator('[data-wx-block="video"]').evaluateAll((all) =>
        all.map((block) => {
          const own = block.getBoundingClientRect()
          const frame = block.querySelector('.webx-video').getBoundingClientRect()
          const caption = block.querySelector('.b-video__caption')
          const under = caption?.getBoundingClientRect()
          const em = parseFloat(getComputedStyle(block.querySelector('.b-video__figure')).fontSize)
          return {
            name: block.querySelector('h2')?.textContent.trim(),
            upright: block.classList.contains('b-video--9-16'),
            gap: under ? under.top - frame.bottom : null,
            captionInside: under
              ? under.left >= own.left - 0.5 && under.right <= own.right + 0.5
              : null,
            frameInside: frame.left >= own.left - 0.5 && frame.right <= own.right + 0.5,
            frameWidth: frame.width,
            blockWidth: own.width,
            centred: Math.abs(frame.left - own.left - (own.right - frame.right)),
            em,
          }
        }),
      )

      expect(blocks.length, `seven blocks at ${width}`).toBe(7)
      for (const block of blocks) {
        expect(
          block.gap,
          `${block.name}: the caption right under the frame`,
        ).toBeGreaterThanOrEqual(0)
        expect(block.gap, `${block.name}: the caption right under the frame`).toBeLessThan(24)
        expect(block.captionInside, block.name).toBe(true)
        expect(block.frameInside, block.name).toBe(true)
        if (!block.upright)
          expect(block.frameWidth, `${block.name} spans the column`).toBeCloseTo(
            block.blockWidth,
            0,
          )
      }
      const upright = blocks.find((block) => block.upright)
      expect(upright.frameWidth).toBeLessThanOrEqual(22 * upright.em + 0.5)
      expect(upright.centred, 'in the middle of the column').toBeLessThan(1)
      expect(await sideways(page), `at ${width}`).toBe(0)
    }

    expect(asked, 'the providers, before consent to media').toEqual([])
    await context.close()
  })

  test('without JavaScript a video is a link to it with its poster', async ({ browser }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 375, height: 800 },
    })
    const page = await context.newPage()
    await page.goto(PATH)

    const links = await page.locator('a.webx-video__facade').evaluateAll((all) =>
      all.map((a) => ({
        href: a.href,
        poster: a.querySelector('img')?.src ?? null,
        visible: a.getBoundingClientRect().height > 0,
      })),
    )
    expect(links).toHaveLength(6)
    for (const link of links) {
      expect(link.href).toMatch(/^https:\/\/(www\.youtube\.com\/watch\?v=|vimeo\.com\/)/)
      expect(link.visible).toBe(true)
    }
    expect(links.filter((link) => link.poster).length).toBeGreaterThanOrEqual(3)
    expect(await page.locator('.webx-video__consent').first().isHidden()).toBe(true)
    expect(await page.locator('video.webx-video__player[controls]').count()).toBe(2)
    expect(await sideways(page)).toBe(0)
    await context.close()
  })
})

test.describe('map', () => {
  const PATH = '/kitchen-sink/map'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin
  // A tile, answered here: the run does not depend on the network, and every tile is written down.
  const TILE = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==',
    'base64',
  )

  const consent = (c) => ({
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c })),
    domain: new URL(site).hostname,
    path: '/',
  })

  // Anything not the site is answered with a tile and written down; before consent there must be none.
  async function outside(context) {
    const asked = []
    await context.route(
      (url) => url.protocol.startsWith('http') && url.origin !== site,
      (route) => {
        asked.push(route.request().url())
        return route.fulfill({ body: TILE, contentType: 'image/png' })
      },
    )
    return asked
  }

  const tiles = (asked) =>
    asked.filter((url) => /^https:\/\/tile\.openstreetmap\.org\/\d+\//.test(url))

  const section = (page, heading) =>
    page
      .locator('[data-wx-block="map"], [data-wx-block="showcase-map"]')
      .filter({ has: page.getByRole('heading', { name: heading, exact: true }) })

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  // Each map's frame, and whether what stands in it stays inside it.
  const frames = (page) =>
    page.locator('.webx-map').evaluateAll((all) =>
      all.map((map) => {
        const box = map.getBoundingClientRect()
        const place = map.querySelector('.webx-map__place')
        const within = (el) => {
          const b = el.getBoundingClientRect()
          return (
            b.width > 0 &&
            b.left >= box.left - 0.5 &&
            b.right <= box.right + 0.5 &&
            b.top >= box.top - 0.5 &&
            b.bottom <= box.bottom + 0.5
          )
        }
        return {
          height: box.height,
          width: box.width,
          blocked: map.classList.contains('is-blocked'),
          fits:
            !place ||
            place.hidden ||
            (place.scrollHeight <= place.clientHeight + 1 &&
              [...place.querySelectorAll('p, a, button')].every(within)),
          buttons: [...map.querySelectorAll('.webx-map__button')].map(
            (b) => b.getBoundingClientRect().height,
          ),
          attribution: within(map.querySelector('.webx-map__attribution')),
        }
      }),
    )

  test('before consent nothing is asked of the tiles; the place keeps the map’s height and fits', async ({
    browser,
  }) => {
    const context = await browser.newContext()
    const asked = await outside(context)
    const page = await context.newPage()

    for (const width of [375, 1280]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto(PATH)
      await page.waitForLoadState('networkidle')
      await still(page)

      const all = await frames(page)
      // From the contacts, Berlin tall, Paris low, the narrow column.
      expect(all.length, `at ${width}`).toBe(4)
      for (const frame of all) {
        expect(frame.blocked).toBe(true)
        expect(frame.fits, `the place fits a frame ${Math.round(frame.width)}px wide`).toBe(true)
        expect(frame.attribution, 'the attribution in its corner').toBe(true)
        expect(Math.min(...frame.buttons)).toBeGreaterThanOrEqual(44)
      }
      // Low, medium and tall are three heights.
      const [contacts, tall, low] = all.map((frame) => frame.height)
      expect(low).toBeLessThan(contacts)
      expect(contacts).toBeLessThan(tall)
      expect(await sideways(page), `at ${width}`).toBe(0)
    }

    expect(asked, 'anybody but the site, before any answer').toEqual([])
    await context.close()
  })

  test('"Load" puts that map in with its tiles and the attribution over it; "Always load maps" loads every one, no reload', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } })
    // Answered without media: the banner stays out of the way, the maps wait.
    await context.addCookies([consent([])])
    const asked = await outside(context)
    const page = await context.newPage()
    await page.goto(PATH)
    await still(page)
    await page.evaluate(() => (window.notReloaded = true))

    const contacts = section(page, 'From the contacts')
    const before = (await frames(page))[0].height
    await contacts.locator('[data-webx-map-load]').click()
    await expect(contacts.locator('.webx-map.is-loaded .leaflet-tile-loaded').first()).toBeVisible()

    const after = await frames(page)
    expect(after[0].height, 'the frame keeps its height').toBeCloseTo(before, 0)
    expect(tiles(asked).length).toBeGreaterThan(0)
    expect(tiles(asked).every((url) => url.includes('/15/'))).toBe(true)
    // The attribution stands over the map, readable: the topmost thing at its middle is it.
    const top = await contacts.locator('.webx-map__attribution').evaluate((el) => {
      const b = el.getBoundingClientRect()
      return el.contains(document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2))
    })
    expect(top).toBe(true)
    await expect(contacts.locator('.webx-map__attribution')).toContainText('OpenStreetMap')
    await expect(contacts.locator('.webx-map__marker')).toBeVisible()
    expect(await page.locator('.webx-map.is-blocked').count(), 'the others still wait').toBe(3)
    expect(
      decodeURIComponent((await context.cookies()).find((c) => c.name === 'webx_consent').value),
    ).toContain('"c":[]')

    const low = section(page, 'Low, the whole city')
    await low.locator('[data-webx-map-always]').click()
    await expect(page.locator('.webx-map.is-blocked')).toHaveCount(0)
    await expect(page.locator('.webx-map__notice')).toHaveCount(0)
    // Each loads when it comes near the screen.
    for (const heading of ['Coordinates of its own', 'Low, the whole city', 'In a narrow column']) {
      const map = section(page, heading).locator('.webx-map')
      await map.scrollIntoViewIfNeeded()
      await expect(map).toHaveClass(/is-loaded/)
    }
    expect(await page.evaluate(() => window.notReloaded)).toBe(true)
    expect(
      decodeURIComponent((await context.cookies()).find((c) => c.name === 'webx_consent').value),
    ).toContain('"c":["media"]')
    expect(
      asked.every((url) => url.startsWith('https://tile.openstreetmap.org/')),
      'only tiles, only after consent',
    ).toBe(true)
    expect(await sideways(page)).toBe(0)
    await context.close()
  })

  test('the wheel over the map scrolls the page until the map is clicked, then zooms it', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } })
    await context.addCookies([consent(['media'])])
    await outside(context)
    const page = await context.newPage()
    await page.goto(PATH)

    const map = section(page, 'Coordinates of its own').locator('.webx-map')
    // The zooms of this map's own tiles: the other maps load their own as they come near.
    const zooms = () =>
      map
        .locator('img.leaflet-tile')
        .evaluateAll((all) => [...new Set(all.map((img) => img.src.split('/')[3]))])
    await map.evaluate((el) => el.scrollIntoView({ block: 'center' }))
    await expect(map).toHaveClass(/is-loaded/)
    await expect(map.locator('.leaflet-tile-loaded').first()).toBeVisible()
    const box = await map.boundingBox()
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)

    const scrolled = await page.evaluate(() => scrollY)
    // Small: the page scrolls before the event is hit-tested, and the map must still be under the pointer.
    await page.mouse.wheel(0, 100)
    await expect.poll(() => page.evaluate(() => scrollY)).toBeGreaterThan(scrolled + 50)
    await expect(map.locator('.webx-map__hint')).toHaveClass(/is-visible/)
    expect(await zooms(), 'the map did not zoom').toEqual(['16'])

    // Clicked, it is the map's wheel: the page stays, the map zooms.
    const again = await map.boundingBox()
    await page.mouse.click(again.x + again.width / 4, again.y + again.height / 2)
    await expect(map).toHaveClass(/is-active/)
    const stayed = await page.evaluate(() => scrollY)
    await page.mouse.wheel(0, -300)
    await expect.poll(async () => (await zooms()).some((z) => Number(z) > 16)).toBe(true)
    expect(await page.evaluate(() => scrollY)).toBe(stayed)

    // A press elsewhere puts it back to sleep.
    await page.mouse.click(5, 5)
    await expect(map).not.toHaveClass(/is-active/)
    await context.close()
  })

  test('without JavaScript a map is its address and a link to a map', async ({ browser }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 375, height: 800 },
    })
    const asked = await outside(context)
    const page = await context.newPage()
    await page.goto(PATH)

    const places = await page.locator('.webx-map').evaluateAll((all) =>
      all.map((map) => ({
        address: map.querySelector('.webx-map__address')?.textContent ?? null,
        link: map.querySelector('a.webx-map__open')?.href ?? null,
        notice: map.querySelector('.webx-map__notice')?.getBoundingClientRect().height ?? 0,
        buttons: map.querySelector('.webx-map__actions')?.getBoundingClientRect().height ?? 0,
      })),
    )
    expect(places).toHaveLength(4)
    expect(places.map((place) => place.address)).toEqual([
      '1 Example Street, Sample City',
      'Pariser Platz, Berlin',
      'Paris',
      '1 Example Street, Sample City',
    ])
    for (const place of places) {
      expect(place.link).toMatch(/^https:\/\/www\.openstreetmap\.org\/\?mlat=/)
      expect(place.notice + place.buttons, 'nothing to press without the script').toBe(0)
    }
    expect(asked).toEqual([])
    expect(await sideways(page)).toBe(0)
    await context.close()
  })
})

test.describe('page tools', () => {
  const PATH = '/kitchen-sink/page-tools'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin
  const CAPTION = 'Plans compared, per month'

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  // Anything not the site is refused and written down: share is links, not a network's script.
  async function outside(context) {
    const asked = []
    await context.route(
      (url) => url.protocol.startsWith('http') && url.origin !== site,
      (route) => {
        asked.push(route.request().url())
        return route.abort()
      },
    )
    return asked
  }

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  // Each wrapped table: its scroller, whether it scrolls, its shadows, where its caption stands.
  const tables = (page) =>
    page.locator('[data-webx-table]').evaluateAll((all) =>
      all.map((table) => {
        const scroller = table.querySelector('.webx-table__scroller')
        const frame = table.querySelector('.webx-table__frame')
        const caption = table.querySelector('.webx-table__caption')
        const box = scroller.getBoundingClientRect()
        const block = table.closest('[data-wx-block]').getBoundingClientRect()
        return {
          caption: caption?.textContent ?? null,
          captionAbove: caption ? caption.getBoundingClientRect().bottom <= box.top + 0.5 : null,
          scrolls: scroller.scrollWidth > scroller.clientWidth + 1,
          tabindex: scroller.getAttribute('tabindex'),
          left: frame.classList.contains('is-more-left'),
          right: frame.classList.contains('is-more-right'),
          inside: box.left >= block.left - 0.5 && box.right <= block.right + 0.5,
        }
      }),
    )

  test('a table of prose scrolls inside its frame, its caption stands still, the shadow follows', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const context = await browser.newContext({ viewport: { width, height: 900 } })
      await context.addCookies([answered])
      const page = await context.newPage()
      await page.goto(PATH)
      await still(page)

      const found = await tables(page)
      expect(found.map((t) => t.caption)).toEqual([CAPTION, CAPTION, 'Opening of the office', null])
      for (const t of found) {
        expect(t.inside, `${width}: the scroller stays in its block`).toBe(true)
        if (t.caption) expect(t.captionAbove).toBe(true)
        // A table that scrolls can be reached by the keyboard; one that fits is no tab stop.
        expect(t.tabindex).toBe(t.scrolls ? '0' : null)
        expect(t.left).toBe(false)
        expect(t.right).toBe(t.scrolls)
      }
      // Nine columns do not fit in a phone, nor in 20rem; the office's hours do.
      expect(found[0].scrolls).toBe(width === 375)
      expect(found[1].scrolls).toBe(true)
      expect(found[2].scrolls).toBe(false)
      expect(await sideways(page)).toBe(0)

      // The caption names the region; the table without one is called Table.
      await expect(page.getByRole('region', { name: CAPTION })).toHaveCount(2)
      await expect(page.getByRole('region', { name: 'Table', exact: true })).toHaveCount(1)

      // Scrolled by the keyboard: the caption does not move, the shadows change sides.
      const wide = page.getByRole('region', { name: CAPTION }).nth(1)
      const caption = page.locator('.webx-table__caption').nth(1)
      // Focusing scrolls the page to the table: the caption is measured against its scroller.
      await wide.focus()
      const before = await caption.boundingBox()
      await page.keyboard.press('ArrowRight')
      await expect.poll(() => wide.evaluate((el) => Math.round(el.scrollLeft))).toBeGreaterThan(0)
      await expect.poll(async () => (await tables(page))[1]).toMatchObject({ left: true })
      await wide.evaluate((el) => (el.scrollLeft = el.scrollWidth))
      await expect
        .poll(async () => (await tables(page))[1])
        .toMatchObject({ left: true, right: false })
      const after = await caption.boundingBox()
      expect(after.x).toBe(before.x)
      expect(after.y + after.height).toBeLessThanOrEqual((await wide.boundingBox()).y + 0.5)
      await context.close()
    }
  })

  test('cards come in as they reach the screen, one after another, and nothing above the fold is hidden', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(PATH)

    const cards = page.locator('.b-showcase-page-tools__card')
    await expect(cards).toHaveCount(24)
    // The screen the page opened on hides nothing; every card is below it, and waits.
    const pending = await page.locator('.is-pending').evaluateAll((all) =>
      all.map((el) => ({
        top: el.getBoundingClientRect().top,
        opacity: getComputedStyle(el).opacity,
      })),
    )
    expect(pending).toHaveLength(24)
    for (const { top, opacity } of pending) {
      expect(top).toBeGreaterThanOrEqual(900)
      expect(opacity).toBe('0')
    }

    // The staggered row: its cards come one after another, in their order.
    const stagger = page
      .locator('[data-wx-block="showcase-page-tools"]')
      .filter({ has: page.getByRole('heading', { name: 'Stagger', exact: true }) })
      .locator('.b-showcase-page-tools__card')
    await stagger.first().evaluate((el) => el.scrollIntoView({ block: 'center' }))
    await expect
      .poll(() =>
        stagger.evaluateAll((all) => all.filter((el) => el.matches('.is-pending')).length),
      )
      .toBe(0)

    // Down the page a screen at a time: once in, each is a plain card again, fully there.
    const height = await page.evaluate(() => document.documentElement.scrollHeight)
    for (let y = 0; y <= height; y += 400) {
      await page.evaluate((top) => window.scrollTo(0, top), y)
      await page.waitForTimeout(60)
    }
    await expect.poll(() => page.locator('.webx-reveal').count(), { timeout: 5000 }).toBe(0)
    expect(
      await cards.evaluateAll((all) => all.every((el) => getComputedStyle(el).opacity === '1')),
    ).toBe(true)
    await context.close()
  })

  test('the cards of a staggered row are delayed one after another', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(PATH)

    const stagger = page
      .locator('[data-wx-block="showcase-page-tools"]')
      .filter({ has: page.getByRole('heading', { name: 'Stagger', exact: true }) })
      .locator('.b-showcase-page-tools__card')
    // Read the delays as the row comes in, before each card settles and drops its own.
    await page.evaluate(() => {
      window.__delays = []
      const seen = new MutationObserver((records) => {
        for (const r of records) {
          const delay = r.target.style.getPropertyValue('--webx-reveal-delay')
          if (delay) window.__delays.push(delay)
        }
      })
      for (const el of document.querySelectorAll('.b-showcase-page-tools__card'))
        seen.observe(el, { attributes: true, attributeFilter: ['style'] })
    })
    await stagger.first().evaluate((el) => el.scrollIntoView({ block: 'center' }))
    await expect.poll(() => page.evaluate(() => window.__delays.length)).toBeGreaterThan(0)
    expect(await page.evaluate(() => window.__delays)).toContain(
      'calc(1 * var(--webx-reveal-stagger))',
    )
    await context.close()
  })

  test('with reduced motion and without JavaScript every card is there at once', async ({
    browser,
  }) => {
    for (const options of [{ reducedMotion: 'reduce' }, { javaScriptEnabled: false }]) {
      const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        ...options,
      })
      await context.addCookies([answered])
      const page = await context.newPage()
      await page.goto(PATH)
      await page.waitForLoadState('networkidle')

      const shown = await page
        .locator('.b-showcase-page-tools__card')
        .evaluateAll((all) =>
          all.map((el) => (el.matches('.is-pending') ? 'pending' : getComputedStyle(el).opacity)),
        )
      expect(shown, JSON.stringify(options)).toEqual(Array(24).fill('1'))
      await context.close()
    }
  })

  test('back to top comes two screens down, above the cookie banner, and takes the page and the focus up', async ({
    browser,
  }) => {
    // No answer yet: the banner is on the screen, and the button stands above it.
    const context = await browser.newContext({ viewport: { width: 375, height: 812 } })
    const page = await context.newPage()
    await page.goto(PATH)
    const button = page.locator('.webx-back-to-top')
    const banner = page.locator('[data-webx-consent-banner]')
    await expect(banner).toBeVisible()
    await expect(button).toBeHidden()

    await page.evaluate(() => window.scrollTo(0, window.innerHeight * 2 - 10))
    await page.waitForTimeout(150)
    await expect(button).toBeHidden()
    await page.evaluate(() => window.scrollTo(0, window.innerHeight * 2 + 40))
    await expect(button).toBeVisible()
    await still(page)

    const box = await button.boundingBox()
    const bannerBox = await banner.boundingBox()
    expect(box.width).toBeGreaterThanOrEqual(44)
    expect(box.height).toBeGreaterThanOrEqual(44)
    expect(box.x + box.width).toBeLessThanOrEqual(375)
    expect(box.y + box.height, 'above the banner').toBeLessThanOrEqual(bannerBox.y)

    // The theme's quick contact stands in the same corner: the button stays above it too, once
    // that has risen over the banner — and after it settles back when the banner goes.
    const contact = page.locator('[data-webx-contact-button] .webx-contact-button__toggle')
    const clear = async () => {
      const b = await button.boundingBox()
      const c = (await contact.count()) ? await contact.boundingBox() : null
      return c === null || b.x + b.width <= c.x || b.y + b.height <= c.y
    }
    await expect.poll(clear).toBe(true)

    await page.getByRole('button', { name: 'Reject all' }).click()
    await expect(banner).toBeHidden()
    await expect.poll(clear).toBe(true)
    await expect
      .poll(async () => {
        const b = await button.boundingBox()
        const c = (await contact.count()) ? await contact.boundingBox() : null
        return Math.round((c ? c.y : 812) - b.y - b.height)
      })
      .toBe(16)

    // From the keyboard: up, and the next Tab is the skip link at the top.
    await button.focus()
    await page.keyboard.press('Enter')
    await expect.poll(() => page.evaluate(() => window.scrollY), { timeout: 5000 }).toBe(0)
    expect(await page.evaluate(() => document.activeElement === document.body)).toBe(true)
    await page.keyboard.press('Tab')
    expect(await page.evaluate(() => document.activeElement.textContent.trim())).toBe(
      'Skip to content',
    )
    await expect(button).toBeHidden()
    await context.close()
  })

  test('share is links of the page’s own address, copies it, and on a phone opens the share sheet', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      viewport: { width: 375, height: 812 },
    })
    await context.addCookies([answered])
    const asked = await outside(context)
    const page = await context.newPage()
    await page.goto(PATH)
    await page.waitForLoadState('networkidle')

    const href = new URL(PATH, site).href
    const address = encodeURIComponent(href)
    const rows = page.locator('.webx-share')
    await expect(rows).toHaveCount(2)
    const first = rows.first()
    await expect(first.locator('a.webx-share__link')).toHaveCount(6)
    expect(await first.locator('a[href*="facebook.com/sharer"]').getAttribute('href')).toBe(
      `https://www.facebook.com/sharer/sharer.php?u=${address}`,
    )
    expect(await first.locator('a[href^="https://x.com/intent/post"]').getAttribute('href')).toBe(
      `https://x.com/intent/post?url=${address}&text=Share%20this%20page`,
    )
    // Each link 44px, every row inside its block — the narrow one wraps.
    for (const row of await rows.all()) {
      const block = await row.locator('xpath=ancestor::*[@data-wx-block][1]').boundingBox()
      for (const link of await row.locator('.webx-share__link').all()) {
        const box = await link.boundingBox()
        expect(box.width).toBeGreaterThanOrEqual(44)
        expect(box.x + box.width).toBeLessThanOrEqual(block.x + block.width + 0.5)
      }
    }
    expect(await sideways(page)).toBe(0)

    await first.getByRole('button', { name: 'Copy link' }).click()
    // The starter site is plain http, where a page has no clipboard API: this is the old way
    // through a selection, which every browser still takes.
    await expect(first.locator('.webx-share__status')).toHaveText('Link copied')
    expect(asked, 'no network is asked for anything').toEqual([])
    await context.close()

    // A phone: one Share button, the system's sheet with the page's address.
    const phone = await browser.newContext({
      viewport: { width: 375, height: 812 },
      hasTouch: true,
      isMobile: true,
    })
    await phone.addCookies([answered])
    await phone.addInitScript(() => {
      window.__shared = []
      navigator.share = (data) => {
        window.__shared.push(data)
        return Promise.resolve()
      }
    })
    const mobile = await phone.newPage()
    await mobile.goto(PATH)
    const row = mobile.locator('.webx-share').first()
    await expect(row.locator('.webx-share__list')).toBeHidden()
    await row.getByRole('button', { name: 'Share' }).click()
    expect(await mobile.evaluate(() => window.__shared)).toEqual([
      { url: href, title: 'Share this page' },
    ])
    await phone.close()
  })

  test('without JavaScript the tables still scroll, copy is gone, back to top is a link', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 375, height: 800 },
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(PATH)

    const found = await tables(page)
    expect(found).toHaveLength(4)
    expect(found[0].scrolls).toBe(true)
    expect(found[0].tabindex).toBe('0')
    expect(await sideways(page)).toBe(0)
    await expect(page.locator('.webx-share__link--copy').first()).toBeHidden()
    await expect(page.locator('a.webx-share__link').first()).toBeVisible()
    const top = page.locator('a.webx-back-to-top')
    await expect(top).toBeVisible()
    expect(await top.getAttribute('href')).toBe('#top')
    expect(await top.evaluate((el) => getComputedStyle(el).position)).toBe('static')
    await context.close()
  })
})

test.describe('background video', () => {
  const PATH = '/kitchen-sink/background-video'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  /** Requests for the video file, and anything not the site refused and written down. */
  async function watch(context) {
    const asked = { clip: [], outside: [] }
    context.on('request', (request) => {
      if (/\.(mp4|webm)(\?|$)/.test(request.url())) asked.clip.push(request.url())
    })
    await context.route(
      (url) => url.protocol.startsWith('http') && url.origin !== site,
      (route) => {
        asked.outside.push(route.request().url())
        return route.abort()
      },
    )
    return asked
  }

  const frames = (page) =>
    page.locator('[data-webx-video-background]').evaluateAll((all) =>
      all.map((root) => {
        const box = root.getBoundingClientRect()
        const video = root.querySelector('.webx-video__background')
        const cover = video.getBoundingClientRect()
        const button = root.querySelector('.webx-video__pause')
        const knob = button.getBoundingClientRect()
        const over = root.querySelector('.webx-video__over')
        const [w, h] = getComputedStyle(root)
          .getPropertyValue('--webx-video-ratio')
          .split('/')
          .map(Number)
        const middle = document.elementFromPoint(
          knob.left + knob.width / 2,
          knob.top + knob.height / 2,
        )
        return {
          width: box.width,
          height: box.height,
          least: (box.width * h) / w,
          covered:
            Math.abs(cover.left - box.left) < 1 &&
            Math.abs(cover.right - box.right) < 1 &&
            Math.abs(cover.top - box.top) < 1 &&
            Math.abs(cover.bottom - box.bottom) < 1,
          overInside: over
            ? over.getBoundingClientRect().top >= box.top - 0.5 &&
              over.getBoundingClientRect().bottom <= box.bottom + 0.5
            : null,
          button: {
            width: knob.width,
            height: knob.height,
            inside:
              knob.right <= box.right &&
              knob.bottom <= box.bottom &&
              knob.left >= box.left &&
              knob.top >= box.top,
          },
          // In view, the button is what a finger at its middle reaches — not the text over the video.
          reachable: knob.top >= 0 && knob.bottom <= innerHeight ? button.contains(middle) : null,
          playing: !video.paused,
          shown: video.classList.contains('is-playing'),
          label: button.getAttribute('aria-label'),
          hidden: root.querySelector('.webx-video__backdrop').getAttribute('aria-hidden'),
        }
      }),
    )

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  test('the frame keeps its ratio or grows to its text, the video covers it, the pause button is in reach', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const context = await browser.newContext({
        viewport: { width, height: width === 375 ? 812 : 900 },
      })
      await context.addCookies([answered])
      const asked = await watch(context)
      const page = await context.newPage()
      await page.goto(PATH)
      await page.locator('[data-webx-video-background]').first().scrollIntoViewIfNeeded()
      // The first plays: its first frame comes up over the poster.
      await expect(page.locator('.webx-video__background.is-playing').first()).toBeAttached({
        timeout: 10_000,
      })
      await still(page)

      const found = await frames(page)
      expect(found, `${width}`).toHaveLength(3)
      for (const [i, frame] of found.entries()) {
        expect(frame.height, `${width} #${i} keeps at least its ratio`).toBeGreaterThanOrEqual(
          frame.least - 1,
        )
        expect(frame.covered, `${width} #${i} the video covers the frame`).toBe(true)
        expect(frame.button.width, `${width} #${i}`).toBeGreaterThanOrEqual(44)
        expect(frame.button.height, `${width} #${i}`).toBeGreaterThanOrEqual(44)
        expect(frame.button.inside, `${width} #${i} the button is in its frame`).toBe(true)
        expect(frame.hidden).toBe('true')
      }
      // Text over two of them: inside the frame; the narrow one grew past its ratio to hold it.
      expect(found[0].overInside).toBe(true)
      expect(found[1].overInside).toBe(true)
      expect(found[2].overInside).toBeNull()
      expect(found[0].reachable, `${width} the first button is reached at its middle`).toBe(true)
      expect(found[0].playing).toBe(true)
      expect(found[0].shown).toBe(true)
      expect(found[0].label).toBe('Pause the background video')
      expect(await sideways(page)).toBe(0)
      expect(
        asked.clip.every((url) => url.startsWith(site)),
        'the video is the site’s',
      ).toBe(true)
      expect(asked.outside).toEqual([])
      await context.close()
    }
  })

  test('it rests off screen, and the pause button stops it, for the next page too', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    await context.addCookies([answered])
    await watch(context)
    const page = await context.newPage()
    await page.goto(PATH)
    const first = page.locator('[data-webx-video-background]').first()
    const last = page.locator('[data-webx-video-background]').last()
    await first.scrollIntoViewIfNeeded()
    await expect(first.locator('.webx-video__background.is-playing')).toBeAttached({
      timeout: 10_000,
    })

    // Scrolled away the first rests and the last plays; back again, the other way round. The
    // narrow one between them may be on the screen with either.
    await last.scrollIntoViewIfNeeded()
    await expect
      .poll(async () => (await frames(page)).map((f) => f.playing).filter((_, i) => i !== 1))
      .toEqual([false, true])
    await first.scrollIntoViewIfNeeded()
    await expect
      .poll(async () => (await frames(page)).map((f) => f.playing).filter((_, i) => i !== 1))
      .toEqual([true, false])

    await first.locator('.webx-video__pause').click()
    await expect.poll(async () => (await frames(page))[0].playing).toBe(false)
    expect((await frames(page))[0].label).toBe('Play the background video')

    // The next page: paused from the start — the visitor said so.
    await page.reload()
    await first.scrollIntoViewIfNeeded()
    await page.waitForTimeout(1500)
    expect((await frames(page))[0].playing).toBe(false)
    expect((await frames(page))[0].label).toBe('Play the background video')

    await first.locator('.webx-video__pause').click()
    await expect.poll(async () => (await frames(page))[0].playing).toBe(true)
    expect(await page.evaluate(() => localStorage.getItem('webx-video-background'))).toBeNull()
    await context.close()
  })

  test('with reduced motion nothing plays and the file is not even asked for, until the button', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      viewport: { width: 1280, height: 900 },
      reducedMotion: 'reduce',
    })
    await context.addCookies([answered])
    const asked = await watch(context)
    const page = await context.newPage()
    await page.goto(PATH)
    const first = page.locator('[data-webx-video-background]').first()
    await first.scrollIntoViewIfNeeded()
    await page.waitForTimeout(1500)

    const found = await frames(page)
    expect(found.map((f) => f.playing)).toEqual([false, false, false])
    expect(found.map((f) => f.label)).toEqual(Array(3).fill('Play the background video'))
    expect(asked.clip, 'preload="none" and no play: no byte of the video').toEqual([])
    await expect(first.locator('.webx-video__poster')).toBeVisible()

    await first.locator('.webx-video__pause').click()
    await expect.poll(async () => (await frames(page))[0].playing).toBe(true)
    expect(asked.clip.length).toBeGreaterThan(0)
    await context.close()
  })

  test('without JavaScript the posters, no button, nothing moving', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: { width: 375, height: 812 },
      javaScriptEnabled: false,
    })
    await context.addCookies([answered])
    const asked = await watch(context)
    const page = await context.newPage()
    await page.goto(PATH)
    await page.waitForLoadState('networkidle')

    await expect(
      page.locator('[data-webx-video-background] .webx-video__poster').first(),
    ).toBeVisible()
    await expect(page.locator('.webx-video__pause').first()).toBeHidden()
    const playing = await page
      .locator('.webx-video__background')
      .evaluateAll((all) => all.map((v) => !v.paused))
    expect(playing).toEqual([false, false, false])
    expect(asked.clip).toEqual([])
    expect(await sideways(page)).toBe(0)
    await context.close()
  })
})

test.describe('counters and countdown', () => {
  const PATH = '/kitchen-sink/counters-and-countdown'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  const numbers = (page) =>
    page.locator('.b-counters .webx-counter').evaluateAll((all) =>
      all.map((el) => ({
        text: el.querySelector('.webx-counter__number').textContent,
        counting: el.classList.contains('is-counting'),
        hidden: el.querySelector('.webx-counter__number').getAttribute('aria-hidden'),
        said: el.querySelector('.webx-counter__final')?.textContent ?? null,
        width: el.querySelector('.webx-counter__number').getBoundingClientRect().width,
      })),
    )

  const timers = (page) =>
    page.locator('.webx-countdown').evaluateAll((all) =>
      all.map((root) => {
        const tiles = Array.from(root.querySelectorAll('.webx-countdown__unit'), (el) =>
          el.getBoundingClientRect(),
        )
        const date = root.querySelector('.webx-countdown__date')
        const block = root.closest('[data-wx-block]').getBoundingClientRect()
        return {
          block: root.closest('[data-wx-block]').getAttribute('data-wx-block'),
          over: root.classList.contains('is-over'),
          ended: root.querySelector('.webx-countdown__ended:not([hidden])')?.textContent ?? null,
          digits: Array.from(
            root.querySelectorAll('.webx-countdown__value'),
            (el) => el.textContent,
          ),
          rows: new Set(tiles.map((t) => Math.round(t.top))).size,
          tileHeight: Math.max(0, ...tiles.map((t) => t.height)),
          inside: tiles.every((t) => t.left >= block.left - 0.5 && t.right <= block.right + 0.5),
          dateWidth: date ? date.getBoundingClientRect().width : null,
          datetime: root.querySelector('time')?.getAttribute('datetime') ?? null,
        }
      }),
    )

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  test('counters below the screen wait at zero and count up in view to the number the server printed', async ({
    browser,
  }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(PATH)

    const waiting = await numbers(page)
    expect(waiting.map((n) => n.text)).toEqual(['0', '0', '0.0', '0'])
    expect(waiting.every((n) => n.counting && n.hidden === 'true')).toBe(true)
    expect(waiting.map((n) => n.said)).toEqual(['15', '3,000', '4.9', '98'])

    await page.locator('.b-counters').scrollIntoViewIfNeeded()
    await page.waitForTimeout(700)
    const midway = await numbers(page)
    // As wide as the final number all the way: the row does not shiver.
    for (const [i, n] of midway.entries())
      expect(n.width, `#${i}`).toBeGreaterThanOrEqual(waiting[i].width - 0.5)

    await expect
      .poll(async () => (await numbers(page)).map((n) => n.text), { timeout: 5000 })
      .toEqual(['15', '3,000', '4.9', '98'])
    const done = await numbers(page)
    expect(done.every((n) => !n.counting && n.hidden === null && n.said === null)).toBe(true)
    await context.close()
  })

  test('the countdown ticks every second, its tiles in a row — two by two in a narrow column — and says the date to a screen reader', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const context = await browser.newContext({ viewport: { width, height: 900 } })
      await context.addCookies([answered])
      const page = await context.newPage()
      await page.goto(PATH)
      await still(page)

      const found = await timers(page)
      // The block to 2030, the minute one, the narrow one, the one over with its text; the hidden one is not there.
      expect(
        found.map((t) => t.block),
        `${width}`,
      ).toEqual(['countdown', 'showcase-numbers', 'showcase-numbers', 'countdown'])
      expect(await page.getByRole('heading', { name: 'Over, hidden' }).count()).toBe(0)
      expect(found[3].over).toBe(true)
      expect(found[3].ended).toBe('The winter sale is over — see you next year.')
      expect(found[3].digits).toEqual([])

      const [decade, , narrow] = found
      expect(decade.datetime).toMatch(/^2030-01-01T00:00:00[+-]\d\d:\d\d$/)
      expect(decade.dateWidth, 'the date is for screen readers').toBeLessThanOrEqual(1)
      expect(decade.rows, `${width} four tiles in a row`).toBe(1)
      expect(narrow.rows, `${width} two by two in 20rem`).toBe(2)
      expect(found.every((t) => t.inside)).toBe(true)
      expect(await sideways(page)).toBe(0)

      const before = decade.digits.join(':')
      await page.waitForTimeout(1200)
      expect((await timers(page))[0].digits.join(':'), 'a second later').not.toBe(before)
      await context.close()
    }
  })

  test('at the end it says its text, the tiles gone', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.clock.install()
    await page.goto(PATH)

    const soon = page.locator('[data-wx-block="showcase-numbers"]').first()
    await expect(soon.locator('.webx-countdown__unit')).toHaveCount(4)
    await page.clock.fastForward('02:00')
    await expect(soon.locator('.webx-countdown__ended')).toHaveText('This one is over.')
    await expect(soon.locator('.webx-countdown__ended')).toBeVisible()
    await expect(soon.locator('.webx-countdown__unit')).toHaveCount(0)
    expect(
      await soon.locator('.webx-countdown').evaluate((el) => el.classList.contains('is-over')),
    ).toBe(true)
    await context.close()
  })

  test('with reduced motion and without JavaScript the numbers are final at once; without it a countdown is its date', async ({
    browser,
  }) => {
    for (const options of [{ reducedMotion: 'reduce' }, { javaScriptEnabled: false }]) {
      const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        ...options,
      })
      await context.addCookies([answered])
      const page = await context.newPage()
      await page.goto(PATH)
      await page.waitForLoadState('networkidle')

      const found = await numbers(page)
      expect(
        found.map((n) => n.text),
        JSON.stringify(options),
      ).toEqual(['15', '3,000', '4.9', '98'])
      expect(found.every((n) => !n.counting)).toBe(true)

      if (options.javaScriptEnabled === false) {
        const timer = (await timers(page))[0]
        expect(timer.dateWidth, 'the date line instead of frozen digits').toBeGreaterThan(100)
        expect(timer.tileHeight).toBe(0)
        await expect(page.locator('.webx-countdown__date').first()).toContainText('Ends on')
      }
      await context.close()
    }
  })
})

test.describe('before and after', () => {
  const PATH = '/kitchen-sink/before-and-after'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  // Each compare: its frame, where its divider and its cut stand, its labels, its slider.
  const compares = (page) =>
    page.locator('[data-webx-compare]').evaluateAll((all) =>
      all.map((root) => {
        const frame = root.querySelector('.webx-compare__frame').getBoundingClientRect()
        const handle = root.querySelector('.webx-compare__handle').getBoundingClientRect()
        const after = root.querySelector('.webx-compare__side--after')
        const pictures = Array.from(root.querySelectorAll('.webx-compare__picture'))
        const labels = Array.from(root.querySelectorAll('.webx-compare__label'), (el) => {
          const box = el.getBoundingClientRect()
          return {
            text: el.textContent,
            inside:
              box.left >= frame.left - 0.5 &&
              box.right <= frame.right + 0.5 &&
              box.top >= frame.top - 0.5 &&
              box.bottom <= frame.bottom + 0.5,
          }
        })
        const range = root.querySelector('.webx-compare__range')
        const block = root.closest('[data-wx-block]').getBoundingClientRect()
        return {
          block: root.closest('[data-wx-block]').getAttribute('data-wx-block'),
          width: frame.width,
          ratio: frame.width / frame.height,
          divider: ((handle.left + handle.width / 2 - frame.left) / frame.width) * 100,
          cut: getComputedStyle(after).clipPath,
          labels,
          value: range.value,
          said: range.getAttribute('aria-valuetext'),
          name: range.getAttribute('aria-label'),
          loaded: pictures.every((img) => img.complete && img.naturalWidth > 0),
          sized: pictures.every((img) => img.getAttribute('width') && img.getAttribute('height')),
          inside: frame.left >= block.left - 0.5 && frame.right <= block.right + 0.5,
        }
      }),
    )

  test('the frame has the pictures’ shape, the divider stands where it starts, the labels inside', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const context = await browser.newContext({ viewport: { width, height: 900 } })
      await context.addCookies([answered])
      const asked = []
      await context.route(
        (url) => url.protocol.startsWith('http') && url.origin !== site,
        (route) => {
          asked.push(route.request().url())
          return route.abort()
        },
      )
      const page = await context.newPage()
      await page.goto(PATH)
      await still(page)
      for (const img of await page.locator('.webx-compare__picture').all()) {
        await img.scrollIntoViewIfNeeded()
      }
      await page.waitForLoadState('networkidle')

      const found = await compares(page)
      expect(
        found.map((c) => c.block),
        `${width}`,
      ).toEqual(['compare', 'compare', 'showcase-compare'])
      for (const [i, c] of found.entries()) {
        expect(c.ratio, `${width} #${i} 1800 × 1200`).toBeCloseTo(1.5, 1)
        expect(c.loaded && c.sized && c.inside, `${width} #${i}`).toBe(true)
        expect(
          c.labels.every((l) => l.inside),
          `${width} #${i} labels in the frame`,
        ).toBe(true)
      }
      expect(found[0].divider).toBeCloseTo(50, 0)
      expect(found[1].divider).toBeCloseTo(25, 0)
      expect(found[1].cut).toBe('inset(0px 0px 0px 25%)')
      expect(found[0].labels.map((l) => l.text)).toEqual(['Before', 'After'])
      expect(found[1].labels.map((l) => l.text)).toEqual(['2019', '2026'])
      expect(found[1].name).toBe('Divider between 2019 and 2026')
      expect(found[2].width, 'a column 20rem wide').toBeLessThanOrEqual(20 * 16)
      expect(await sideways(page)).toBe(0)
      expect(asked).toEqual([])
      await context.close()
    }
  })

  test('the divider follows a mouse, the arrow keys and a sideways finger', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: { width: 1280, height: 900 },
      hasTouch: true,
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(PATH)
    await still(page)

    const room = page.locator('[data-wx-block="compare"]').first()
    const frame = room.locator('.webx-compare__frame')
    const range = room.locator('.webx-compare__range')
    await frame.scrollIntoViewIfNeeded()
    const box = await frame.boundingBox()
    const at = (share) => box.x + box.width * share
    const divider = async () => (await compares(page))[0]

    // A mouse: where it presses, then where it drags.
    await page.mouse.move(at(0.3), box.y + box.height / 2)
    await page.mouse.down()
    expect((await divider()).divider).toBeCloseTo(30, 0)
    await page.mouse.move(at(0.8), box.y + box.height / 3, { steps: 5 })
    await page.mouse.up()
    const dragged = await divider()
    expect(dragged.divider).toBeCloseTo(80, 0)
    expect(Number(dragged.value)).toBeCloseTo(80, 0)
    expect(dragged.said).toBe('80%')
    await expect(range).toBeFocused()

    // The keys, from where the mouse left it.
    await page.keyboard.press('ArrowLeft')
    expect(Math.round(Number((await divider()).value))).toBe(75)
    await page.keyboard.press('Shift+ArrowLeft')
    expect(Math.round(Number((await divider()).value))).toBe(74)
    await page.keyboard.press('Home')
    expect((await divider()).divider).toBeCloseTo(0, 0)
    await page.keyboard.press('End')
    const end = await divider()
    expect(end.divider).toBeCloseTo(100, 0)
    expect(end.said).toBe('100%')

    // From the keyboard: the ring on the knob.
    await range.blur()
    await range.focus()
    expect(
      await room
        .locator('.webx-compare__handle')
        .evaluate((el) => el.classList.contains('is-focused')),
    ).toBe(true)

    // A finger: a few pixels do not move it, sideways does; a tap puts it there.
    const finger = async (type, x, id) =>
      frame.dispatchEvent(type, {
        pointerId: id,
        pointerType: 'touch',
        isPrimary: true,
        clientX: x,
        clientY: box.y + box.height / 2,
        bubbles: true,
      })
    await finger('pointerdown', at(0.5), 11)
    await finger('pointermove', at(0.5) + 3, 11)
    expect((await divider()).divider).toBeCloseTo(100, 0)
    await finger('pointermove', at(0.4), 11)
    expect((await divider()).divider).toBeCloseTo(40, 0)
    await finger('pointerup', at(0.4), 11)
    const now = await frame.boundingBox()
    await page.touchscreen.tap(now.x + now.width * 0.6, now.y + now.height / 2)
    expect((await divider()).divider).toBeCloseTo(60, 0)
    await context.close()
  })

  test('on a phone the divider is in reach and the page does not scroll sideways', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      viewport: { width: 375, height: 812 },
      isMobile: true,
      hasTouch: true,
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(PATH)
    await still(page)

    const room = page.locator('[data-wx-block="compare"]').first()
    await room.scrollIntoViewIfNeeded()
    const knob = await room
      .locator('.webx-compare__handle')
      .evaluate((el) => getComputedStyle(el, '::before').width)
    expect(parseFloat(knob), 'the knob is a finger wide').toBeGreaterThanOrEqual(44)
    expect(
      await room.locator('.webx-compare__frame').evaluate((el) => getComputedStyle(el).touchAction),
    ).toBe('pan-y')
    expect(await sideways(page)).toBe(0)
    await context.close()
  })

  test('without JavaScript the two pictures stand side by side, one under the other in a column', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const context = await browser.newContext({
        viewport: { width, height: 900 },
        javaScriptEnabled: false,
      })
      await context.addCookies([answered])
      const page = await context.newPage()
      await page.goto(PATH)

      const sides = await page.locator('[data-webx-compare]').evaluateAll((all) =>
        all.map((root) => {
          const [before, after] = Array.from(root.querySelectorAll('.webx-compare__side'), (el) =>
            el.getBoundingClientRect(),
          )
          return {
            beside: Math.abs(before.top - after.top) < 1 && after.left >= before.right - 0.5,
            under: after.top >= before.bottom - 0.5,
            ratio: before.width / before.height,
            handle: root.querySelector('.webx-compare__handle').getBoundingClientRect().width,
            range: root.querySelector('.webx-compare__range').getBoundingClientRect().width,
            labels: Array.from(
              root.querySelectorAll('.webx-compare__label'),
              (el) => el.getBoundingClientRect().width > 0,
            ),
          }
        }),
      )
      expect(sides[0].beside, `${width}`).toBe(width === 1280)
      expect(sides[0].under, `${width}`).toBe(width === 375)
      expect(sides[2].under, `${width} the column 20rem wide`).toBe(true)
      for (const s of sides) {
        expect(s.ratio).toBeCloseTo(1.5, 1)
        expect(s.handle + s.range).toBe(0)
        expect(s.labels).toEqual([true, true])
      }
      expect(await sideways(page)).toBe(0)
      await context.close()
    }
  })
})

test.describe('table of contents', () => {
  const PATH = '/kitchen-sink/table-of-contents'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  const header = (page) =>
    page.evaluate(() =>
      parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue('--webx-header-height'),
      ),
    )

  const policy = (page) => page.locator('.b-toc--text').first()

  async function open(width, options = {}) {
    const context = await options.browser.newContext({
      viewport: { width, height: width === 375 ? 812 : 900 },
      ...(options.context ?? {}),
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(PATH)
    // A style tag is put in by a script: none without JavaScript, and nothing moves there anyway.
    if (options.context?.javaScriptEnabled !== false) await still(page)
    return { context, page }
  }

  test('every link of every list lands on a heading of the page, each id once', async ({
    browser,
  }) => {
    const { context, page } = await open(1280, { browser })

    const found = await page.evaluate(() => {
      const ids = Array.from(document.querySelectorAll('[id]'), (el) => el.id)
      return {
        lists: Array.from(document.querySelectorAll('[data-webx-toc]'), (root) =>
          Array.from(root.querySelectorAll('.webx-toc__link'), (a) => {
            const target = document.getElementById(decodeURIComponent(a.hash.slice(1)))
            return { text: a.textContent, heading: target?.localName ?? null }
          }),
        ),
        duplicates: ids.filter((id, i) => ids.indexOf(id) !== i),
      }
    })
    expect(found.duplicates).toEqual([])
    // The page's list, the policy's, the one before its text, the narrow column's.
    expect(found.lists.map((list) => list.length)).toEqual([15, 11, 3, 3])
    expect(found.lists.flat().every((link) => /^h[23]$/.test(link.heading))).toBe(true)
    expect(found.lists[0].map((l) => l.text).slice(0, 3)).toEqual([
      'Of the whole page',
      'Who we are',
      'What we collect',
    ])
    expect(found.lists[1].map((l) => l.text).slice(0, 4)).toEqual([
      'Who we are',
      'Our address',
      'What we collect',
      'When you write to us',
    ])
    await context.close()
  })

  test('at 1280 the list stands beside the text, sticks under the header and marks the section read', async ({
    browser,
  }) => {
    const { context, page } = await open(1280, { browser })
    const top = await header(page)
    expect(top, 'the header sticks').toBeGreaterThan(0)

    const place = () =>
      policy(page).evaluate((block) => {
        const nav = block.querySelector('.webx-toc__nav').getBoundingClientRect()
        const content = block.querySelector('.webx-toc__content').getBoundingClientRect()
        return {
          beside: nav.left >= content.right,
          top: nav.top,
          toggle: block.querySelector('.webx-toc__toggle').getBoundingClientRect().height,
          current: Array.from(block.querySelectorAll('.webx-toc__link.is-current'), (a) => [
            a.textContent,
            a.getAttribute('aria-current'),
          ]),
        }
      })

    const first = await place()
    expect(first.beside).toBe(true)
    expect(first.toggle, 'no bar on a wide screen').toBe(0)

    // Down the policy: the list stays under the header, the section under the line is marked.
    const heading = policy(page).getByRole('heading', { name: 'Cookies' })
    await heading.evaluate((el) => window.scrollBy(0, el.getBoundingClientRect().top - 200))
    await expect.poll(async () => (await place()).current).toEqual([['Cookies', 'true']])
    const stuck = await place()
    expect(stuck.top).toBeGreaterThanOrEqual(top - 0.5)
    expect(stuck.top).toBeLessThanOrEqual(top + 24)

    // The list before its text, on the other side.
    expect(
      await page
        .locator('.b-toc--text')
        .nth(1)
        .evaluate((block) => {
          const nav = block.querySelector('.webx-toc__nav').getBoundingClientRect()
          return nav.right <= block.querySelector('.webx-toc__content').getBoundingClientRect().left
        }),
    ).toBe(true)

    // The column 20rem wide folds as a phone does.
    expect(
      await page
        .locator('[data-wx-block="showcase-toc"] .webx-toc__toggle')
        .evaluate((el) => el.getBoundingClientRect().height),
    ).toBeGreaterThanOrEqual(44)
    expect(await sideways(page)).toBe(0)
    await context.close()
  })

  test('a link of the list leaves its heading below the sticky header, on a phone below the bar too', async ({
    browser,
  }) => {
    for (const width of [1280, 375]) {
      const { context, page } = await open(width, { browser })
      const top = await header(page)
      const nav = policy(page).locator('.webx-toc__nav')

      if (width === 375) {
        await policy(page).scrollIntoViewIfNeeded()
        await nav.locator('.webx-toc__toggle').click()
      }
      await nav.getByRole('link', { name: 'Your rights' }).click()
      await expect.poll(() => page.evaluate(() => location.hash)).toBe('#your-rights')
      await page.waitForTimeout(300)

      const where = await page.evaluate(() => {
        const h = document.getElementById('your-rights').getBoundingClientRect()
        const bar = document.querySelector('.b-toc--text .webx-toc__nav').getBoundingClientRect()
        return { top: h.top, bar: bar.bottom }
      })
      expect(where.top, `${width} below the header`).toBeGreaterThanOrEqual(top - 0.5)
      if (width === 375) {
        expect(where.top, 'below the bar').toBeGreaterThanOrEqual(where.bar - 0.5)
        expect(
          await nav.locator('.webx-toc__panel').evaluate((el) => el.classList.contains('is-open')),
        ).toBe(false)
      }
      expect(where.top, `${width} and not far below`).toBeLessThan(top + 140)
      await context.close()
    }
  })

  test('at 375 the list folds into a bar under the header that says the section and opens the list', async ({
    browser,
  }) => {
    const { context, page } = await open(375, { browser })
    const top = await header(page)
    const nav = policy(page).locator('.webx-toc__nav')
    const toggle = nav.locator('.webx-toc__toggle')

    expect(await nav.locator('.webx-toc__title').isVisible()).toBe(false)
    expect(await nav.locator('.webx-toc__panel').isVisible()).toBe(false)
    const bar = await toggle.boundingBox()
    expect(bar.height).toBeGreaterThanOrEqual(44)
    expect(await toggle.getAttribute('aria-expanded')).toBe('false')

    // Into the policy: the bar sticks under the header and names the section.
    const heading = policy(page).getByRole('heading', { name: 'How long we keep it' })
    await heading.evaluate((el) => window.scrollBy(0, el.getBoundingClientRect().top - 150))
    await expect
      .poll(() => nav.locator('.webx-toc__current').textContent())
      .toBe('How long we keep it')
    const stuck = await nav.boundingBox()
    expect(Math.abs(stuck.y - top)).toBeLessThanOrEqual(1)

    await toggle.click()
    expect(await toggle.getAttribute('aria-expanded')).toBe('true')
    const panel = await nav.locator('.webx-toc__panel').boundingBox()
    expect(panel.x).toBeGreaterThanOrEqual(0)
    expect(panel.x + panel.width).toBeLessThanOrEqual(375)
    expect(panel.y + panel.height).toBeLessThanOrEqual(812)
    await page.keyboard.press('Escape')
    expect(await nav.locator('.webx-toc__panel').isVisible()).toBe(false)
    await expect(toggle).toBeFocused()

    // The page's own list, at the top: folded too.
    expect(await page.locator('.b-toc:not(.b-toc--text) .webx-toc__toggle').isVisible()).toBe(true)
    expect(await sideways(page)).toBe(0)
    await context.close()
  })

  test('without JavaScript every list is open and its links still land', async ({ browser }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(width, {
        browser,
        context: { javaScriptEnabled: false },
      })
      const lists = await page.locator('[data-webx-toc]').evaluateAll((all) =>
        all.map((root) => ({
          toggle: root.querySelector('.webx-toc__toggle').getBoundingClientRect().height,
          list: root.querySelector('.webx-toc__list').getBoundingClientRect().height,
          sticky: getComputedStyle(root.querySelector('.webx-toc__nav')).position,
        })),
      )
      expect(lists.every((l) => l.toggle === 0 && l.list > 0)).toBe(true)
      if (width === 375) expect(lists.every((l) => l.sticky !== 'sticky')).toBe(true)

      await policy(page).getByRole('link', { name: 'Cookies' }).first().click()
      expect(new URL(page.url()).hash).toBe('#cookies')
      expect(await sideways(page)).toBe(0)
      await context.close()
    }
  })
})

test.describe('show more', () => {
  const PATH = '/kitchen-sink/show-more'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  const cards = (page, list) =>
    page
      .locator(`#showcase-${list} .webx-load-more [data-webx-load-more-list] > article h3`)
      .allTextContents()

  const numbers = (from, to) => Array.from({ length: to - from + 1 }, (_, i) => `Card ${from + i}`)

  async function open(width, options = {}) {
    const context = await options.browser.newContext({
      viewport: { width, height: width === 375 ? 812 : 900 },
      ...(options.context ?? {}),
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(options.path ?? PATH)
    if (options.context?.javaScriptEnabled !== false) await still(page)
    return { context, page }
  }

  test('the button adds the next page in order, moves the focus and the address, and goes on the last page', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(width, { browser })
      const root = page.locator('#showcase-page .webx-load-more')
      const button = root.locator('.webx-load-more__button')

      // The links are covered by the button; the button is the size of a finger.
      await expect(button).toBeVisible()
      expect((await button.boundingBox()).height).toBeGreaterThanOrEqual(44)
      expect(await root.locator('.webx-load-more__pages').isVisible()).toBe(false)
      expect(await root.locator('a[rel="next"]').getAttribute('href')).toContain('page=2')
      expect(await cards(page, 'page')).toEqual(numbers(1, 6))

      await button.click()
      await expect.poll(() => cards(page, 'page')).toEqual(numbers(1, 12))
      // The focus on the first new card, the address on the page just loaded, its links next.
      expect(
        await page.evaluate(() => document.activeElement?.querySelector('h3')?.textContent),
      ).toBe('Card 7')
      expect(new URL(page.url()).searchParams.get('page')).toBe('2')
      expect(await root.getAttribute('data-next')).toContain('page=3')
      expect(await root.locator('a[rel="next"]').getAttribute('href')).toContain('page=3')
      expect(await root.locator('.webx-load-more__status').textContent()).toBe(
        'Page 2 of 4 loaded.',
      )

      await button.click()
      await expect.poll(() => cards(page, 'page')).toEqual(numbers(1, 18))
      await button.click()
      await expect.poll(() => cards(page, 'page')).toEqual(numbers(1, 23))
      await expect(button).toBeHidden()
      expect(await root.locator('.webx-load-more__status').textContent()).toBe(
        'Page 4 of 4 loaded. That is everything.',
      )
      expect(new URL(page.url()).searchParams.get('page')).toBe('4')
      // The other lists kept their pages.
      expect(await cards(page, 'more')).toEqual(numbers(1, 6))
      expect(await sideways(page)).toBe(0)

      // A reload lands on the page last loaded: its cards, no button, a link back.
      await page.reload()
      expect(await cards(page, 'page')).toEqual(numbers(19, 23))
      expect(await root.locator('.webx-load-more__button').count()).toBe(0)
      await expect(root.locator('a[rel="prev"]')).toBeVisible()
      expect(await root.locator('a[rel="prev"]').getAttribute('href')).toContain('page=3')
      await context.close()
    }
  })

  test('Back leaves the list, Forward comes back to the page last loaded', async ({ browser }) => {
    const { context, page } = await open(1280, { browser, path: '/kitchen-sink' })
    await page.goto(PATH)
    await page.locator('#showcase-page .webx-load-more__button').click()
    await expect.poll(() => cards(page, 'page')).toEqual(numbers(1, 12))

    await page.goBack()
    expect(new URL(page.url()).pathname).toBe('/kitchen-sink')
    await page.goForward()
    expect(new URL(page.url()).searchParams.get('page')).toBe('2')
    // Restored as it was, or loaded anew from its address: card 7 is there either way.
    expect(await cards(page, 'page')).toContain('Card 7')
    await context.close()
  })

  test('shown: the links stay under the button and mark the pages on the screen', async ({
    browser,
  }) => {
    const { context, page } = await open(1280, { browser })
    const root = page.locator('#showcase-more .webx-load-more')
    await expect(root.locator('.webx-pagination')).toBeVisible()
    await root.locator('.webx-load-more__button').click()
    await expect.poll(() => cards(page, 'more')).toEqual(numbers(1, 12))

    await expect(root.locator('.webx-pagination')).toBeVisible()
    expect(await root.locator('[data-page="1"]').getAttribute('class')).toContain('is-loaded')
    expect(await root.locator('[data-page="2"]').getAttribute('aria-current')).toBe('page')
    expect(new URL(page.url()).searchParams.get('more')).toBe('2')
    for (const link of await root.locator('.webx-pagination__link').all()) {
      const box = await link.boundingBox()
      expect(box.height).toBeGreaterThanOrEqual(44)
      expect(box.width).toBeGreaterThanOrEqual(44)
    }
    await context.close()
  })

  test('a page that cannot be loaded says so and gives the links back', async ({ browser }) => {
    const { context, page } = await open(375, { browser })
    await page.route(/[?&]page=2/, (route) => route.abort())
    const root = page.locator('#showcase-page .webx-load-more')
    await root.locator('.webx-load-more__button').click()

    await expect(root.locator('.webx-load-more__status')).toHaveText(
      'The next page could not be loaded. Try again, or use the links to the pages.',
    )
    await expect(root.locator('.webx-load-more__pages')).toBeVisible()
    expect(await cards(page, 'page')).toEqual(numbers(1, 6))
    expect(new URL(page.url()).search).toBe('')

    await page.unroute(/[?&]page=2/)
    await root.locator('a[rel="next"]').click()
    await page.waitForURL(/page=2/)
    expect(await cards(page, 'page')).toEqual(numbers(7, 12))
    await context.close()
  })

  test('without JavaScript the links of the pages lead on, and no button', async ({ browser }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(width, {
        browser,
        context: { javaScriptEnabled: false },
      })
      expect(await page.locator('.webx-load-more__button:visible').count()).toBe(0)
      expect(await page.locator('.webx-pagination:visible').count()).toBe(3)

      await page.locator('#showcase-page a[rel="next"]').click()
      await page.waitForURL(/page=2/)
      expect(await cards(page, 'page')).toEqual(numbers(7, 12))
      expect(await sideways(page)).toBe(0)
      await context.close()
    }
  })
})

test.describe('notice bar', () => {
  const PATH = '/kitchen-sink/notice-bar'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  const headerHeight = (page) =>
    page.evaluate(() =>
      parseFloat(
        getComputedStyle(document.documentElement).getPropertyValue('--webx-header-height'),
      ),
    )

  async function open(width, options = {}) {
    const context = await options.browser.newContext({
      viewport: { width, height: width === 375 ? 812 : 900 },
      ...(options.context ?? {}),
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(options.path ?? PATH)
    if (options.context?.javaScriptEnabled !== false) await still(page)
    return { context, page }
  }

  test('stands above the header, named, and the sticky header measures what it did', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      // The same page without the bar: what the header and the corner measure there.
      const { context, page } = await open(width, { browser, path: '/kitchen-sink' })
      expect(await page.locator('[data-webx-notice-bar]').count()).toBe(0)
      const plain = {
        height: await headerHeight(page),
        corner: await page.locator('.webx-contact-button__toggle').boundingBox(),
      }

      await page.goto(PATH)
      await still(page)
      const bar = page.getByRole('region', { name: 'Announcement' })
      await expect(bar).toBeVisible()
      await expect(bar).toContainText('Open on Saturdays from November.')
      const box = await bar.boundingBox()
      const header = await page.locator('.webx-header').boundingBox()
      expect(box.y).toBe(0)
      expect(Math.abs(header.y - (box.y + box.height))).toBeLessThanOrEqual(1)
      expect(box.width).toBe(width)

      const close = bar.getByRole('button', { name: 'Close the announcement' })
      const target = await close.boundingBox()
      expect(target.height).toBeGreaterThanOrEqual(44)
      expect(target.width).toBeGreaterThanOrEqual(44)
      expect(target.x + target.width).toBeLessThanOrEqual(width)

      expect(await headerHeight(page)).toBe(plain.height)
      expect(await page.locator('.webx-contact-button__toggle').boundingBox()).toEqual(plain.corner)

      // Scrolled past: the bar goes, the header sticks at the top as it always did.
      await page.evaluate(() => window.scrollTo(0, 600))
      await expect
        .poll(async () => (await page.locator('.webx-header__bar').boundingBox()).y)
        .toBeLessThanOrEqual(1)
      expect((await bar.boundingBox()).y + box.height).toBeLessThanOrEqual(0)
      expect(await headerHeight(page)).toBe(plain.height)
      expect(await sideways(page)).toBe(0)
      await context.close()
    }
  })

  test('closed, it stays closed on the next page without a flash; new words come back', async ({
    browser,
  }) => {
    const { context, page } = await open(375, { browser })
    const bar = page.locator('[data-webx-notice-bar]')
    const version = await bar.getAttribute('data-webx-notice-bar')

    await bar.getByRole('button', { name: 'Close the announcement' }).click()
    await expect(bar).toBeHidden()
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem('webx-notice-bar')))).toEqual([
      version,
    ])
    // The header moved up to the top of the page.
    expect((await page.locator('.webx-header').boundingBox()).y).toBe(0)

    // The next page with the same words, its script held back: the head alone hides the bar,
    // so it is never painted, not even before the script arrives.
    await page.route(/notice-bar\.js/, (route) => route.abort())
    await page.goto('/kitchen-sink/notice-bar/same-words')
    const same = page.locator('[data-webx-notice-bar]')
    expect(await same.getAttribute('data-webx-notice-bar')).toBe(version)
    expect(await same.evaluate((el) => getComputedStyle(el).display)).toBe('none')
    expect((await page.locator('.webx-header').boundingBox()).y).toBe(0)
    await page.unroute(/notice-bar\.js/)

    // Other words are another version: back.
    await page.goto('/kitchen-sink/notice-bar/new-words')
    const fresh = page.getByRole('region', { name: 'Announcement' })
    await expect(fresh).toBeVisible()
    await expect(fresh).toContainText('free delivery until Sunday')
    expect(await fresh.getAttribute('data-webx-notice-bar')).not.toBe(version)
    await context.close()
  })

  test('closed from the keyboard, the next Tab is "Skip to content"', async ({ browser }) => {
    const { context, page } = await open(1280, { browser })
    const close = page.getByRole('button', { name: 'Close the announcement' })
    await close.focus()
    await page.keyboard.press('Enter')
    await expect(page.locator('[data-webx-notice-bar]')).toBeHidden()
    expect(await page.evaluate(() => document.activeElement === document.body)).toBe(true)
    await page.keyboard.press('Tab')
    await expect(page.locator('.site-skip')).toBeFocused()
    await context.close()
  })

  test('without JavaScript the bar says its words, with no button to close it', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(width, {
        browser,
        context: { javaScriptEnabled: false },
      })
      const bar = page.locator('[data-webx-notice-bar]')
      await expect(bar).toBeVisible()
      expect(await bar.locator('.webx-notice-bar__close').isVisible()).toBe(false)
      const box = await bar.boundingBox()
      expect(
        Math.abs((await page.locator('.webx-header').boundingBox()).y - box.height),
      ).toBeLessThanOrEqual(1)
      expect(await sideways(page)).toBe(0)
      await context.close()
    }
  })
})

test.describe('zoom', () => {
  const PATH = '/kitchen-sink/zoom'
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-09', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  const sideways = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)

  async function open(browser, width, context = {}) {
    const made = await browser.newContext({
      viewport: { width, height: width === 375 ? 812 : 900 },
      ...context,
    })
    await made.addCookies([answered])
    const page = await made.newPage()
    await page.goto(PATH)
    if (context.javaScriptEnabled !== false) await still(page)
    return { context: made, page }
  }

  // The zoom of a block: its picture, the view over it, the lens, the panel and where the large
  // picture stands — all as the screen has them.
  const state = (root) =>
    root.evaluate((el) => {
      const rect = (node) => {
        if (!node) return null
        const r = node.getBoundingClientRect()
        const style = getComputedStyle(node)
        return {
          left: r.left,
          top: r.top,
          right: r.right,
          bottom: r.bottom,
          width: r.width,
          height: r.height,
          shown: style.display !== 'none' && Number(style.opacity) > 0,
        }
      }
      const panel = document.querySelector('.webx-zoom__panel')
      return {
        classes: el.className,
        picture: rect(el.querySelector('.webx-zoom__picture, img')),
        view: rect(el.querySelector('.webx-zoom__view')),
        large: rect(el.querySelector('.webx-zoom__view .webx-zoom__large')),
        lens: rect(el.querySelector('.webx-zoom__lens')),
        panel: rect(panel),
        panelLarge: rect(panel?.querySelector('.webx-zoom__large')),
        src: el.querySelector('.webx-zoom__view .webx-zoom__large')?.currentSrc ?? null,
        window: document.documentElement.clientWidth,
      }
    })

  test('a mouse magnifies inside the frame, the point under the cursor staying under it', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 1280)
    const root = page.locator('[data-wx-block="gallery"] [data-webx-zoom]').first()
    await root.scrollIntoViewIfNeeded()
    const box = await root.locator('img').first().boundingBox()

    for (const [fx, fy] of [
      [0.3, 0.4],
      [0.75, 0.6],
    ]) {
      const x = box.x + box.width * fx
      const y = box.y + box.height * fy
      await page.mouse.move(x, y, { steps: 3 })
      const s = await state(root)
      expect(s.classes).toContain('is-active')
      expect(s.view.shown, 'the view over the picture').toBe(true)
      expect(
        Math.abs(s.view.left - s.picture.left) + Math.abs(s.view.width - s.picture.width),
      ).toBeLessThan(1)
      expect(s.large.width / s.picture.width, 'the scale').toBeCloseTo(2.5, 1)
      // The same point of the picture under the cursor, at its size and magnified.
      expect((x - s.large.left) / s.large.width).toBeCloseTo(
        (x - s.picture.left) / s.picture.width,
        2,
      )
      expect((y - s.large.top) / s.large.height).toBeCloseTo(
        (y - s.picture.top) / s.picture.height,
        2,
      )
    }

    // Out of the picture: as it was.
    await page.mouse.move(box.x + box.width / 2, box.y - 60)
    const out = await state(root)
    expect(out.classes).not.toContain('is-active')
    expect(out.view.shown).toBe(false)
    await context.close()
  })

  test('the original is asked for on the first hover only, the smaller copy shown until then', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 1280)
    const asked = []
    page.on('request', (request) => asked.push(request.url()))
    const root = page.locator('[data-wx-block="showcase-zoom"] [data-webx-zoom]').first()
    const original = new URL(await root.getAttribute('data-webx-zoom'), site).href
    const shown = await root.locator('img').evaluate((img) => img.currentSrc)
    expect(shown).not.toBe(original)
    await root.scrollIntoViewIfNeeded()
    await page.waitForLoadState('networkidle')
    expect(asked.filter((url) => url === original)).toEqual([])

    const box = await root.boundingBox()
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 2 })
    await expect.poll(() => asked.filter((url) => url === original).length).toBe(1)
    await expect
      .poll(() =>
        root.evaluate(
          () => document.querySelector('.webx-zoom__panel .webx-zoom__large')?.currentSrc,
        ),
      )
      .toBe(original)

    await page.mouse.move(box.x + box.width / 2, box.y - 80)
    await page.mouse.move(box.x + box.width / 3, box.y + box.height / 3, { steps: 2 })
    await page.waitForTimeout(200)
    expect(asked.filter((url) => url === original).length).toBe(1)
    await context.close()
  })

  test('beside: a lens on the picture, the panel right of it in the window; no room — inside', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 1280)
    const [product, narrow] = await page
      .locator('[data-wx-block="showcase-zoom"] [data-webx-zoom]')
      .all()
    await product.scrollIntoViewIfNeeded()
    const box = await product.locator('img').first().boundingBox()
    const x = box.x + box.width * 0.4
    const y = box.y + box.height * 0.55
    await page.mouse.move(x, y, { steps: 3 })

    const s = await state(product)
    expect(s.classes).toContain('is-side')
    expect(s.view.shown, 'not inside the frame').toBe(false)
    expect(s.lens.shown && s.panel.shown).toBe(true)
    // The panel: the frame's height, right of it by the gap, inside the window.
    expect(s.panel.left - s.picture.right).toBeCloseTo(16, 0)
    expect(s.panel.top).toBeCloseTo(s.picture.top, 0)
    expect(s.panel.height).toBeCloseTo(s.picture.height, 0)
    expect(s.panel.width).toBeGreaterThanOrEqual(s.picture.width * 0.6 - 1)
    expect(s.panel.right).toBeLessThanOrEqual(s.window)
    // The lens on the picture, centred on the cursor, its size the panel's by the scale.
    expect(s.lens.left).toBeGreaterThanOrEqual(s.picture.left - 0.5)
    expect(s.lens.right).toBeLessThanOrEqual(s.picture.right + 0.5)
    expect(s.lens.width * 2.5).toBeCloseTo(s.panel.width, 0)
    expect((s.lens.left + s.lens.right) / 2).toBeCloseTo(x, 0)
    // The middle of the panel shows the middle of the lens.
    const cx = (s.panel.left + s.panel.right) / 2
    const cy = (s.panel.top + s.panel.bottom) / 2
    expect((cx - s.panelLarge.left) / s.panelLarge.width).toBeCloseTo(
      ((s.lens.left + s.lens.right) / 2 - s.picture.left) / s.picture.width,
      2,
    )
    expect((cy - s.panelLarge.top) / s.panelLarge.height).toBeCloseTo(
      ((s.lens.top + s.lens.bottom) / 2 - s.picture.top) / s.picture.height,
      2,
    )
    expect(await sideways(page)).toBe(0)

    // A column 20rem wide has no room beside: inside the frame.
    await narrow.scrollIntoViewIfNeeded()
    const small = await narrow.locator('img').first().boundingBox()
    expect(small.width).toBeLessThanOrEqual(20 * 16)
    await page.mouse.move(small.x + small.width / 2, small.y + small.height / 2, { steps: 3 })
    const n = await state(narrow)
    expect(n.classes).not.toContain('is-side')
    expect(n.view.shown).toBe(true)
    expect(n.panel.shown).toBe(false)
    await context.close()

    // At 375 the product page puts its text under the picture: no room beside either.
    const phone = await open(browser, 375)
    const first = phone.page.locator('[data-wx-block="showcase-zoom"] [data-webx-zoom]').first()
    await first.scrollIntoViewIfNeeded()
    const at = await first.locator('img').first().boundingBox()
    await phone.page.mouse.move(at.x + at.width / 2, at.y + at.height / 2, { steps: 3 })
    const p = await state(first)
    expect(p.classes).not.toContain('is-side')
    expect(p.view.shown).toBe(true)
    expect(await sideways(phone.page)).toBe(0)
    await phone.context.close()
  })

  test('two fingers pinch and pan inside the frame; one finger still scrolls and swipes; a double tap is 2× and back', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 375, { hasTouch: true, isMobile: true })
    const cdp = await context.newCDPSession(page)
    const touch = (type, points) =>
      cdp.send('Input.dispatchTouchEvent', {
        type,
        touchPoints: points.map(([x, y], id) => ({ x: Math.round(x), y: Math.round(y), id })),
      })
    const gallery = page.locator('[data-wx-block="gallery"]')
    const root = gallery.locator('[data-webx-zoom]').first()
    await root.scrollIntoViewIfNeeded()
    const index = () =>
      gallery.locator('.webx-slider__viewport').evaluate((el) => el.swiper.activeIndex)
    const ratio = async () => {
      const s = await state(root)
      return s.large && s.view.shown ? s.large.width / s.picture.width : 1
    }

    // Two fingers apart: twice the distance, twice the size.
    let box = await root.locator('img').first().boundingBox()
    const cx = box.x + box.width / 2
    const cy = box.y + box.height / 2
    await touch('touchStart', [
      [cx - 40, cy],
      [cx + 40, cy],
    ])
    for (const spread of [50, 60, 70, 80]) {
      await touch('touchMove', [
        [cx - spread, cy],
        [cx + spread, cy],
      ])
    }
    await touch('touchEnd', [])
    expect(await root.evaluate((el) => el.classList.contains('is-zoomed'))).toBe(true)
    expect(await ratio()).toBeCloseTo(2, 1)
    expect(await index(), 'the slider held still').toBe(0)

    // One finger now pans the picture, and neither the page nor the slider moves.
    const scrolled = await page.evaluate(() => scrollY)
    const before = (await state(root)).large
    await touch('touchStart', [[cx, cy]])
    for (const step of [1, 2, 3, 4]) await touch('touchMove', [[cx - step * 15, cy - step * 10]])
    await touch('touchEnd', [])
    const after = (await state(root)).large
    expect(after.left).toBeLessThan(before.left - 20)
    expect(await page.evaluate(() => scrollY)).toBe(scrolled)
    expect(await index()).toBe(0)

    // Pinched back: as it was.
    await touch('touchStart', [
      [cx - 80, cy],
      [cx + 80, cy],
    ])
    for (const spread of [60, 40, 20]) {
      await touch('touchMove', [
        [cx - spread, cy],
        [cx + spread, cy],
      ])
    }
    await touch('touchEnd', [])
    expect(await root.evaluate((el) => el.classList.contains('is-zoomed'))).toBe(false)
    expect(await ratio()).toBe(1)

    // One finger at its own size: up and down is the page's, sideways the slider's.
    box = await root.locator('img').first().boundingBox()
    const y0 = await page.evaluate(() => scrollY)
    await touch('touchStart', [[box.x + box.width / 2, box.y + box.height * 0.8]])
    for (const step of [1, 2, 3, 4, 5, 6]) {
      await touch('touchMove', [[box.x + box.width / 2, box.y + box.height * 0.8 - step * 25]])
    }
    await touch('touchEnd', [])
    await expect.poll(() => page.evaluate(() => scrollY)).toBeGreaterThan(y0 + 50)
    await root.scrollIntoViewIfNeeded()
    box = await root.locator('img').first().boundingBox()
    await touch('touchStart', [[box.x + box.width * 0.8, box.y + box.height / 2]])
    for (const step of [1, 2, 3, 4, 5, 6]) {
      await touch('touchMove', [[box.x + box.width * 0.8 - step * 30, box.y + box.height / 2]])
    }
    await touch('touchEnd', [])
    await expect.poll(index).toBe(1)
    expect(await sideways(page)).toBe(0)

    // A double tap on the slide now in view: 2× and back, and no lightbox for either.
    const second = gallery.locator('[data-webx-zoom]').nth(1)
    box = await second.locator('img').first().boundingBox()
    await page.touchscreen.tap(box.x + box.width / 2, box.y + box.height / 2)
    await page.touchscreen.tap(box.x + box.width / 2, box.y + box.height / 2)
    expect(await second.evaluate((el) => el.classList.contains('is-zoomed'))).toBe(true)
    await page.waitForTimeout(500)
    expect(await page.locator('.pswp').count()).toBe(0)
    await page.touchscreen.tap(box.x + box.width / 2, box.y + box.height / 2)
    await page.touchscreen.tap(box.x + box.width / 2, box.y + box.height / 2)
    expect(await second.evaluate((el) => el.classList.contains('is-zoomed'))).toBe(false)
    await page.waitForTimeout(500)
    expect(await page.locator('.pswp').count()).toBe(0)

    // A single tap is still the lightbox's, a moment later.
    await page.waitForTimeout(400)
    await page.touchscreen.tap(box.x + box.width / 2, box.y + box.height / 2)
    await expect(page.locator('.pswp')).toBeVisible()
    expect(await sideways(page)).toBe(0)
    await context.close()
  })

  test('a keyboard opens the lightbox, Esc gives the focus back; the link says what it does', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 1280)
    // PhotoSwipe closes on the end of its animation, which `still` would never let come.
    await page.emulateMedia({ reducedMotion: 'reduce' })
    const root = page.locator('[data-wx-block="showcase-zoom"] [data-webx-zoom]').first()
    expect(await root.getAttribute('aria-label')).toBe(
      'Zoom: A watch on a grid, a card of fine lines and a ruler',
    )
    await root.focus()
    await page.keyboard.press('Enter')
    await expect(page.locator('.pswp')).toBeVisible()
    await expect(page.locator('.pswp')).toHaveAttribute('aria-modal', 'true')
    await page.keyboard.press('Escape')
    await expect(page.locator('.pswp')).toHaveCount(0)
    await expect(root).toBeFocused()
    expect(await root.evaluate((el) => getComputedStyle(el).outlineStyle)).toBe('solid')
    await context.close()
  })

  test('without JavaScript the picture is a link to the original, and nothing scrolls sideways', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(browser, width, { javaScriptEnabled: false })
      const roots = await page.locator('[data-webx-zoom]').evaluateAll((all) =>
        all.map((el) => ({
          href:
            el.closest('a')?.getAttribute('href') ?? el.querySelector('a')?.getAttribute('href'),
          large: el.getAttribute('data-webx-zoom'),
          shown: el.querySelector('img').getBoundingClientRect().width > 0,
          view: el.querySelectorAll('.webx-zoom__view').length,
        })),
      )
      expect(roots.length, `${width}`).toBe(2 + 6)
      for (const r of roots) {
        expect(r.href).toBeTruthy()
        if (r.large) expect(r.href).toBe(r.large)
        expect(r.view).toBe(0)
      }
      expect(roots[0].shown).toBe(true)
      const response = await page.request.get(roots[0].href)
      expect(response.status()).toBe(200)
      expect(response.headers()['content-type']).toMatch(/^image\//)
      expect(await sideways(page)).toBe(0)
      await context.close()
    }
  })
})

test.describe('blocks of the theme', () => {
  const BASE = '/kitchen-sink/blocks'
  const PAGES = ['hero', 'text', 'media-text', 'cards', 'cta', 'contact']
  const SIX = PAGES.map((slug) => `[data-wx-block="${slug}"]`).join(', ')
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-11', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  async function open(browser, width, path) {
    const context = await browser.newContext({
      viewport: { width, height: width === 375 ? 812 : 900 },
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(path)
    await still(page)
    return { context, page }
  }

  // Where each block of a kind stands on the page, and its parts, as the screen has them — the
  // one in the narrow column of the showcase marked `narrow`.
  const layout = (page, slug, parts) =>
    page.locator(`[data-wx-block="${slug}"]`).evaluateAll(
      (all, parts) =>
        all.map((el) => {
          const rect = (node) => {
            if (!node) return null
            const r = node.getBoundingClientRect()
            return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width }
          }
          const found = Object.fromEntries(parts.map((p) => [p, rect(el.querySelector(p))]))
          const grid = el.querySelector('.b-cards__grid')
          const title = el.querySelector('.b-hero__title')
          return {
            narrow: !!el.closest('.b-showcase-column__frame'),
            classes: el.className,
            width: el.getBoundingClientRect().width,
            columns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : null,
            title: title ? parseFloat(getComputedStyle(title).fontSize) : null,
            ...found,
          }
        }),
      parts,
    )

  // Side by side: the second starts where the first ends, on the same rows.
  const beside = (a, b) => b.left >= a.right - 1 && b.top < a.bottom
  const under = (a, b) => b.top >= a.bottom - 1

  test('every page of a block opens and nothing on it scrolls sideways at 360, 375 and 1280', async ({
    browser,
  }) => {
    for (const width of [360, 375, 1280]) {
      for (const path of [BASE, ...PAGES.map((p) => `${BASE}/${p}`)]) {
        const { context, page } = await open(browser, width, path)
        const { scroll, client, blocks } = await page.evaluate(
          (six) => ({
            scroll: document.documentElement.scrollWidth,
            client: document.documentElement.clientWidth,
            blocks: document.querySelectorAll(six).length,
          }),
          SIX,
        )
        expect(blocks, `${path}: the blocks of the theme are on it`).toBeGreaterThan(0)
        expect(scroll, `${path} at ${width}`).toBeLessThanOrEqual(client)
        await context.close()
      }
    }
  })

  test('the cards take as many columns as their column has room for', async ({ browser }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(browser, width, `${BASE}/cards`)
      const grids = await layout(page, 'cards', [])
      expect(grids.length).toBe(4)
      for (const grid of grids) {
        const across = Number(/b-cards--(\d)/.exec(grid.classes)[1])
        // 1280: the column of the page has room for each block's own most; 375 and the narrow
        // column of the showcase: one card a row, whatever the block asks for.
        const expected = width === 1280 && !grid.narrow ? across : 1
        const label = `${width} ${grid.classes}${grid.narrow ? ' narrow' : ''}`
        expect(grid.columns, label).toBe(expected)
      }
      await context.close()
    }
  })

  test('picture and text stand side by side in a wide column, one under the other in a narrow one', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(browser, width, `${BASE}/media-text`)
      const blocks = await layout(page, 'media-text', [
        '.b-media-text__figure',
        '.b-media-text__words',
      ])
      expect(blocks.length).toBe(4)
      for (const b of blocks) {
        const figure = b['.b-media-text__figure']
        const words = b['.b-media-text__words']
        if (!figure) continue
        const end = b.classes.includes('b-media-text--end')
        const label = `${width} ${b.classes}${b.narrow ? ' narrow' : ''}`
        if (width === 1280 && !b.narrow)
          expect(end ? beside(words, figure) : beside(figure, words), label).toBe(true)
        else expect(under(figure, words), label).toBe(true)
      }
      await context.close()
    }
  })

  test('the call to action puts its buttons beside the words only where there is room', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(browser, width, `${BASE}/cta`)
      const bands = await layout(page, 'cta', ['.b-cta__words', '.b-cta__actions'])
      expect(bands.length).toBe(4)
      for (const b of bands) {
        const label = `${width} ${b.classes}${b.narrow ? ' narrow' : ''}`
        if (width === 1280 && !b.narrow)
          expect(beside(b['.b-cta__words'], b['.b-cta__actions']), label).toBe(true)
        else expect(under(b['.b-cta__words'], b['.b-cta__actions']), label).toBe(true)
      }
      await context.close()
    }
  })

  test('the contacts and the form stand side by side in a wide column, the form under them in a narrow one', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(browser, width, `${BASE}/contact`)
      const blocks = await layout(page, 'contact', ['.b-contact__details', '.b-contact__form'])
      const both = blocks.filter((b) => b['.b-contact__details'] && b['.b-contact__form'])
      expect(both.length, 'the starter has contacts and the form named contact').toBe(2)
      for (const b of both) {
        const label = `${width}${b.narrow ? ' narrow' : ''}`
        if (width === 1280 && !b.narrow)
          expect(beside(b['.b-contact__details'], b['.b-contact__form']), label).toBe(true)
        else expect(under(b['.b-contact__details'], b['.b-contact__form']), label).toBe(true)
      }
      await expect(page.locator('[data-wx-block="contact"] form').first()).toBeVisible()
      await context.close()
    }
  })

  test('the hero sets a smaller headline in a narrow column, and edge to edge is the window', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 1280, `${BASE}/hero`)
    const heroes = await layout(page, 'hero', [])
    const wide = heroes.find((h) => !h.narrow && h.classes.includes('b-hero--accent'))
    const narrow = heroes.find((h) => h.narrow)
    expect(narrow.title).toBeLessThan(wide.title)
    const bleed = heroes.find((h) => h.classes.includes('site-bleed'))
    expect(bleed.width).toBe(await page.evaluate(() => document.documentElement.clientWidth))
    expect(heroes.find((h) => h.classes.includes('b-hero--plain')).width).toBeLessThan(bleed.width)
    await context.close()
  })

  test('every word of every block reads in every preset, the night one included', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 1280, BASE)
    for (const path of PAGES.map((p) => `${BASE}/${p}`)) {
      await page.goto(path)
      // A new page has lost the style that stops transitions: a colour would be measured half way.
      await still(page)
      for (const preset of Object.keys(presets)) {
        await paint(page, preset)
        const low = await page.evaluate((six) => {
          const canvas = document
            .createElement('canvas')
            .getContext('2d', { willReadFrequently: true })
          // Whatever form the computed colour takes (rgb, oklch, color-mix), as sRGB bytes.
          const rgba = (color) => {
            canvas.clearRect(0, 0, 1, 1)
            canvas.fillStyle = color
            canvas.fillRect(0, 0, 1, 1)
            return Array.from(canvas.getImageData(0, 0, 1, 1).data)
          }
          const luminance = (color) => {
            const [r, g, b] = rgba(color)
              .slice(0, 3)
              .map((v) => {
                v /= 255
                return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
              })
            return 0.2126 * r + 0.7152 * g + 0.0722 * b
          }
          const ratio = (a, b) => {
            const [x, y] = [luminance(a), luminance(b)].sort((p, q) => q - p)
            return (x + 0.05) / (y + 0.05)
          }
          // The first painted background up the tree is what the words stand on.
          const behind = (el) => {
            for (let node = el; node; node = node.parentElement) {
              const bg = getComputedStyle(node).backgroundColor
              if (rgba(bg)[3] > 0) return bg
            }
            return 'white'
          }
          // The form and the widgets inside the contacts are the inbox's and the widgets' own,
          // checked where they live.
          return [
            ...document.querySelectorAll(`:is(${six}) :is(h1, h2, h3, p, li, a, address, span)`),
          ]
            .filter((el) => {
              if (el.closest('form, .webx-hours, .webx-phones, .webx-socials')) return false
              if (el.querySelector('h1, h2, h3, p, li, a, address, span')) return false
              const r = el.getBoundingClientRect()
              return r.width > 0 && r.height > 0 && el.textContent.trim() !== ''
            })
            .map((el) => ({
              what: `${el.className || el.tagName} "${el.textContent.trim().slice(0, 30)}"`,
              ratio: Math.round(ratio(getComputedStyle(el).color, behind(el)) * 100) / 100,
            }))
            .filter((w) => w.ratio < 4.5)
        }, SIX)
        expect(low, `${path} ${preset}`).toEqual([])
      }
    }
    await context.close()
  })
})

test.describe('modules in the theme', () => {
  // THEMES §10.2, TH3.1: what the modules print, in their own base look under theme-default.
  const BASE = '/kitchen-sink/modules'
  const PAGES = [BASE, `${BASE}/form`, `${BASE}/faq-and-links`, `${BASE}/long-trail`]
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-11', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  async function open(browser, width, path) {
    const context = await browser.newContext({
      viewport: { width, height: width === 1280 ? 900 : 812 },
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    await page.goto(path)
    await still(page)
    return { context, page }
  }

  // The error of a field is on the page before anything is wrong, hidden: shown to be measured.
  const showErrors = (page) =>
    page.evaluate(() => {
      for (const error of document.querySelectorAll('.webx-inbox-form .webx-field__error')) {
        error.hidden = false
        error.textContent = 'Fill this in.'
      }
    })

  // Each form of the showcase: the boxes of its name and e-mail fields (both half width), the
  // smallest height of a control and of a choice, and whether it stands in the narrow column.
  const forms = (page) =>
    page.locator('form.webx-inbox-form[data-webx-form="showcase"]').evaluateAll((all) =>
      all.map((form) => {
        const box = (node) => {
          const r = node.getBoundingClientRect()
          return { left: r.left, right: r.right, top: r.top, bottom: r.bottom }
        }
        const field = (key) => box(form.querySelector(`[data-webx-field="fields.${key}"]`))
        const lowest = (selector) =>
          Math.min(
            ...[...form.querySelectorAll(selector)].map((n) => n.getBoundingClientRect().height),
          )
        return {
          narrow: !!form.closest('.b-showcase-column__frame'),
          name: field('name'),
          email: field('email'),
          message: field('message'),
          control: lowest('.webx-field__control:not([type="file"])'),
          choice: lowest('.webx-field__choice'),
          submit: form.querySelector('.webx-form__submit').getBoundingClientRect().height,
        }
      }),
    )

  test('the modules stylesheet is linked under the theme, and nothing scrolls sideways at 360, 375 and 1280', async ({
    browser,
  }) => {
    for (const width of [360, 375, 1280]) {
      for (const path of PAGES) {
        const { context, page } = await open(browser, width, path)
        const { scroll, client, links } = await page.evaluate(() => ({
          scroll: document.documentElement.scrollWidth,
          client: document.documentElement.clientWidth,
          links: [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => l.href),
        }))
        expect(scroll, `${path} at ${width}`).toBeLessThanOrEqual(client)
        const modules = links.findIndex((href) =>
          /\/themes\/modules\/[0-9a-f]{12}\/modules\.css$/.test(href),
        )
        const theme = links.findIndex((href) => href.includes('/themes/webx-ui/theme-default/'))
        expect(modules, `${path}: the modules' base look is linked`).toBeGreaterThan(-1)
        expect(modules, 'and before the theme, which restyles it by coming later').toBeLessThan(
          theme,
        )
        await context.close()
      }
    }
  })

  test('the form puts half-width fields side by side only where it has room, every target 44px tall', async ({
    browser,
  }) => {
    for (const width of [375, 1280]) {
      const { context, page } = await open(browser, width, `${BASE}/form`)
      const all = await forms(page)
      expect(all.length, 'the form in the column of the page and in the narrow one').toBe(2)
      for (const f of all) {
        const label = `${width}${f.narrow ? ' narrow' : ''}`
        if (width === 1280 && !f.narrow) {
          expect(f.email.left, `${label}: e-mail beside the name`).toBeGreaterThanOrEqual(
            f.name.right - 1,
          )
          expect(f.email.top, label).toBe(f.name.top)
        } else {
          expect(f.email.top, `${label}: e-mail under the name`).toBeGreaterThanOrEqual(
            f.name.bottom - 1,
          )
        }
        expect(f.control, `${label}: controls`).toBeGreaterThanOrEqual(44)
        expect(f.choice, `${label}: choices`).toBeGreaterThanOrEqual(44)
        expect(f.submit, `${label}: the button`).toBeGreaterThanOrEqual(44)
      }
      await context.close()
    }
  })

  test('a preset repaints the form: its controls, its button and its error', async ({
    browser,
  }) => {
    const { context, page } = await open(browser, 1280, `${BASE}/form`)
    await showErrors(page)
    const read = () =>
      page.evaluate(() => {
        const style = (selector) => getComputedStyle(document.querySelector(selector))
        // A token as the browser resolves a colour, so the two compare as the same string.
        const token = (name) => {
          const probe = document.createElement('i')
          probe.style.color = `var(--site-${name})`
          document.body.append(probe)
          const value = getComputedStyle(probe).color
          probe.remove()
          return value
        }
        return {
          control: style('.webx-inbox-form .webx-field__control').backgroundColor,
          border: style('.webx-inbox-form .webx-field__control').borderTopColor,
          submit: style('.webx-inbox-form .webx-form__submit').backgroundColor,
          error: style('.webx-inbox-form .webx-field__error').color,
          bg: token('color-bg'),
          line: token('color-border'),
          accent: token('color-accent'),
          danger: token('color-danger'),
        }
      })
    const before = await read()
    expect(before.control).toBe(before.bg)
    expect(before.border).toBe(before.line)
    expect(before.submit).toBe(before.accent)
    expect(before.error).toBe(before.danger)
    await paint(page, 'night')
    const after = await read()
    expect(after.control).toBe(after.bg)
    expect(after.submit).toBe(after.accent)
    expect(after.control).not.toBe(before.control)
    expect(after.submit).not.toBe(before.submit)
    await context.close()
  })

  test('the trail is one line of links without markers, and wraps on a phone without going sideways', async ({
    browser,
  }) => {
    for (const [width, path] of [
      [1280, `${BASE}/form`],
      [360, `${BASE}/long-trail`],
    ]) {
      const { context, page } = await open(browser, width, path)
      const trail = await page.locator('.webx-breadcrumbs').evaluate((nav) => {
        const list = nav.querySelector('.webx-breadcrumbs__list')
        const items = [...list.querySelectorAll('.webx-breadcrumbs__item')]
        const links = [...list.querySelectorAll('.webx-breadcrumbs__link')]
        return {
          marker: getComputedStyle(items[0]).listStyleType,
          display: getComputedStyle(list).display,
          // By the middle: a link is as tall as a finger, the current page's name only as its words.
          rows: items.map((item) => {
            const r = item.getBoundingClientRect()
            return Math.round((r.top + r.bottom) / 2)
          }),
          right: Math.max(...items.map((item) => item.getBoundingClientRect().right)),
          link: Math.min(...links.map((a) => a.getBoundingClientRect().height)),
          client: document.documentElement.clientWidth,
          current: list
            .querySelector('[aria-current="page"]')
            .classList.contains('webx-breadcrumbs__item--current'),
        }
      })
      expect(trail.marker, path).toBe('none')
      expect(trail.display, path).toBe('flex')
      expect(trail.current, path).toBe(true)
      expect(trail.link, `${path}: a link a finger can hit`).toBeGreaterThanOrEqual(44)
      expect(trail.right, path).toBeLessThanOrEqual(trail.client)
      if (width === 1280) expect(new Set(trail.rows).size, `${path}: one line`).toBe(1)
      else expect(new Set(trail.rows).size, `${path}: the long one wraps`).toBeGreaterThan(1)
      await context.close()
    }
  })

  test('the FAQ and then the links close the page, in its column, every target 44px tall', async ({
    browser,
  }) => {
    for (const width of [360, 1280]) {
      const { context, page } = await open(browser, width, `${BASE}/faq-and-links`)
      const m = await page.evaluate(() => {
        const main = document.querySelector('.site-main')
        const children = [...main.children].filter((el) => el.getBoundingClientRect().height > 0)
        const faq = main.querySelector(':scope > .webx-faq')
        const links = main.querySelector(':scope > .webx-links')
        const height = (selector) =>
          Math.min(
            ...[...main.querySelectorAll(selector)].map((n) => n.getBoundingClientRect().height),
          )
        return {
          order: children.slice(-2).map((el) => el.className),
          // The trail, the FAQ and the links: the column's edge (the narrow text block is inset by design).
          lefts: [main.querySelector('.webx-breadcrumbs'), faq, links]
            .filter(Boolean)
            .map((el) => Math.round(el.getBoundingClientRect().left)),
          questions: main.querySelectorAll('.webx-faq__question').length,
          question: height('.webx-faq__question'),
          link: height('.webx-links__link'),
          list: getComputedStyle(main.querySelector('.webx-links__list')).display,
          marker: getComputedStyle(main.querySelector('.webx-links__item')).listStyleType,
          scroll: document.documentElement.scrollWidth,
          client: document.documentElement.clientWidth,
          both: !!faq && !!links,
        }
      })
      const label = `at ${width}`
      expect(m.both, `${label}: both under the page`).toBe(true)
      expect(m.order, `${label}: the FAQ, then the links, last in the column`).toEqual([
        'webx-faq',
        'webx-links',
      ])
      expect(m.lefts.length, label).toBe(3)
      for (const left of m.lefts) expect(left, `${label}: in the column`).toBe(m.lefts[0])
      expect(m.questions, label).toBe(3)
      expect(m.question, `${label}: a question a finger can open`).toBeGreaterThanOrEqual(44)
      expect(m.link, `${label}: links a finger can hit`).toBeGreaterThanOrEqual(44)
      expect(m.list).toBe('flex')
      expect(m.marker).toBe('none')
      expect(m.scroll, label).toBeLessThanOrEqual(m.client)
      await context.close()
    }
  })

  test('a question opens without a script, and night repaints the links', async ({ browser }) => {
    const { context, page } = await open(browser, 1280, `${BASE}/faq-and-links`)
    const answer = page.locator('.webx-faq__answer').first()
    await expect(answer).toBeHidden()
    await page.locator('.webx-faq__question').first().click()
    await expect(answer).toBeVisible()
    const read = () =>
      page.evaluate(() => {
        const token = (name) => {
          const probe = document.createElement('i')
          probe.style.color = `var(--site-${name})`
          document.body.append(probe)
          const value = getComputedStyle(probe).color
          probe.remove()
          return value
        }
        return {
          link: getComputedStyle(document.querySelector('.webx-links__link')).backgroundColor,
          line: getComputedStyle(document.querySelector('.webx-faq__item')).borderBottomColor,
          surface: token('color-surface'),
          border: token('color-border'),
        }
      })
    const before = await read()
    expect(before.link).toBe(before.surface)
    expect(before.line).toBe(before.border)
    await paint(page, 'night')
    const after = await read()
    expect(after.link).toBe(after.surface)
    expect(after.line).toBe(after.border)
    expect(after.link).not.toBe(before.link)
    await context.close()
  })

  test('every word of the form and the trail reads in every preset, the night one included', async ({
    browser,
  }) => {
    for (const path of [`${BASE}/form`, `${BASE}/faq-and-links`]) {
      const { context, page } = await open(browser, 1280, path)
      await showErrors(page)
      // Every answer open, so its words are measured too.
      await page.evaluate(() =>
        document.querySelectorAll('.webx-faq__item').forEach((d) => (d.open = true)),
      )
      for (const preset of Object.keys(presets)) {
        await paint(page, preset)
        const low = await page.evaluate(() => {
          const canvas = document
            .createElement('canvas')
            .getContext('2d', { willReadFrequently: true })
          const rgba = (color) => {
            canvas.clearRect(0, 0, 1, 1)
            canvas.fillStyle = color
            canvas.fillRect(0, 0, 1, 1)
            return Array.from(canvas.getImageData(0, 0, 1, 1).data)
          }
          const luminance = (color) => {
            const [r, g, b] = rgba(color)
              .slice(0, 3)
              .map((v) => {
                v /= 255
                return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
              })
            return 0.2126 * r + 0.7152 * g + 0.0722 * b
          }
          const ratio = (a, b) => {
            const [x, y] = [luminance(a), luminance(b)].sort((p, q) => q - p)
            return (x + 0.05) / (y + 0.05)
          }
          const behind = (el) => {
            for (let node = el; node; node = node.parentElement) {
              const bg = getComputedStyle(node).backgroundColor
              if (rgba(bg)[3] > 0) return bg
            }
            return 'white'
          }
          const words = [
            '.webx-inbox-form .webx-field__label',
            '.webx-inbox-form .webx-field__hint',
            '.webx-inbox-form .webx-field__error',
            '.webx-inbox-form .webx-field__choice-label',
            '.webx-inbox-form .webx-form__submit',
            '.webx-inbox-form .webx-field__control',
            '.webx-breadcrumbs__link',
            '.webx-breadcrumbs__item--current',
            '.webx-faq__heading',
            '.webx-faq__question',
            '.webx-faq__answer',
            '.webx-links__heading',
            '.webx-links__link',
          ]
          return [...document.querySelectorAll(words.join(', '))]
            .filter((el) => {
              const r = el.getBoundingClientRect()
              return r.width > 0 && r.height > 0
            })
            .map((el) => ({
              what: `${el.className} "${(el.textContent || el.value || '').trim().slice(0, 30)}"`,
              ratio: Math.round(ratio(getComputedStyle(el).color, behind(el)) * 100) / 100,
            }))
            .filter((w) => w.ratio < 4.5)
        })
        expect(low, `${path} in ${preset}`).toEqual([])
      }
      await context.close()
    }
  })
})

test.describe('module pages in the theme', () => {
  // THEMES §10.2, TH3.2–TH3.3: the pages a module prints itself, in its base look under
  // theme-default — on a starter built with --with=blog,services,events,vacancies,press,recipes
  // (recipes since TH3.4). A module
  // the starter was built without is skipped, not failed.
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin

  const answered = {
    name: 'webx_consent',
    value: encodeURIComponent(JSON.stringify({ v: 1, d: '2026-10-11', c: ['necessary'] })),
    domain: new URL(site).hostname,
    path: '/',
  }

  // `list` — the list of cards on the index; `grid` — whether it puts several to a row;
  // `detail` — the link from the index to the first page of one record; `words` — what has to
  // read in every preset; `targets` — what a finger has to hit.
  const MODULES = [
    {
      name: 'blog',
      index: '/blog',
      list: '.webx-blog-cards',
      card: '.webx-blog-card',
      grid: true,
      detail: '.webx-blog-card__link',
      words: [
        '.webx-blog-card__title',
        '.webx-blog-card__date',
        '.webx-blog-card__lead',
        '.webx-blog-rubrics__link',
        '.webx-blog-article__meta',
        '.webx-blog-tags__link',
      ],
      targets: ['.webx-blog-rubrics__link', '.webx-blog-tags__link', '.webx-blog-pager__link'],
    },
    {
      name: 'services',
      index: '/services',
      list: '.webx-services-cards',
      card: '.webx-services-card',
      grid: true,
      detail: '.webx-services-card__link',
      words: [
        '.webx-services-card__title',
        '.webx-services-card__lead',
        '.webx-services-group__link',
        '.webx-services-header__lead',
      ],
      targets: [],
    },
    {
      name: 'events',
      index: '/events',
      list: '.wx-events__grid',
      card: '.wx-events__card',
      grid: true,
      detail: '.wx-events__link',
      words: [
        '.wx-events__name',
        '.wx-events__meta',
        '.wx-events__nav-link',
        '.wx-event__lead',
        '.wx-event__term',
        '.wx-event__value',
        '.wx-event__book',
        '.wx-event__calendar',
        '.wx-event__highlight-title',
        '.wx-event__highlight-text',
        '.wx-event__service-link',
      ],
      targets: [
        '.wx-events__nav-link',
        '.wx-events__category',
        '.wx-events__page-link',
        '.wx-event__book',
        '.wx-event__calendar',
        '.wx-event__map',
        '.wx-event__service-link',
      ],
    },
    {
      name: 'vacancies',
      index: '/careers',
      list: '.wx-vacancies__list',
      card: '.wx-vacancies__card',
      grid: false,
      detail: '.wx-vacancies__link',
      words: [
        '.wx-vacancies__link',
        '.wx-vacancies__meta',
        '.wx-vacancies__lead',
        '.wx-vacancies__filter-link',
        '.wx-vacancy__closed',
        '.wx-vacancy__lead',
        '.wx-vacancy__term',
        '.wx-vacancy__value',
      ],
      targets: ['.wx-vacancies__filter-link'],
    },
    {
      name: 'press',
      // The index is a page of blocks; the module's own page is an outlet's.
      index: '/press',
      list: null,
      card: null,
      grid: false,
      detail: '.site-main a[href*="/press/"]',
      words: [
        '.wx-press-outlet__summary',
        '.wx-press-outlet__facts',
        '.wx-press-article__link',
        '.wx-press-article__pdf',
        '.wx-press-article__meta',
        '.wx-press-article__excerpt',
        '.wx-press-article__links',
      ],
      targets: ['.wx-press-outlet__website', '.wx-press-article__read', '.wx-press-article__file'],
    },
    {
      name: 'recipes',
      index: '/recipes',
      list: '.wx-recipes__grid',
      card: '.wx-recipes__card',
      grid: true,
      detail: '.wx-recipes__link',
      words: [
        '.wx-recipes__name',
        '.wx-recipes__meta',
        '.wx-recipes__nav-link',
        '.wx-recipes__chip',
        '.wx-recipe__lead',
        '.wx-recipe__fact',
        '.wx-recipe__chip',
        '.wx-recipe__nutrient',
        '.wx-recipe__amount',
        '.wx-recipe__service-link',
      ],
      targets: [
        '.wx-recipes__nav-link',
        '.wx-recipes__chip',
        '.wx-recipes__category',
        '.wx-recipes__service',
        '.wx-recipes__page-link',
        '.wx-recipe__category',
        '.wx-recipe__service-link',
      ],
    },
  ]

  async function open(browser, width, path) {
    const context = await browser.newContext({
      viewport: { width, height: width === 1280 ? 900 : 812 },
    })
    await context.addCookies([answered])
    const page = await context.newPage()
    const response = await page.goto(path)
    await still(page)
    return { context, page, status: response?.status() ?? 0 }
  }

  let found = null

  /** The modules the starter has, each with its index and the address of one of its records. */
  async function installed(browser) {
    if (found) return found
    found = []
    for (const module of MODULES) {
      const { context, page, status } = await open(browser, 1280, module.index)
      if (status === 200) {
        const href = await page
          .locator(module.detail)
          .first()
          .getAttribute('href')
          .catch(() => null)
        const detail = href ? new URL(href, site).pathname : null
        found.push({ ...module, pages: detail ? [module.index, detail] : [module.index] })
      }
      await context.close()
    }
    return found
  }

  test('every page of a module keeps to the main column, prints no style inline and scrolls nothing sideways', async ({
    browser,
  }) => {
    const modules = await installed(browser)
    test.skip(modules.length === 0, 'the starter was built without --with')
    for (const width of [360, 375, 1280]) {
      for (const module of modules) {
        for (const path of module.pages) {
          const { context, page } = await open(browser, width, path)
          const m = await page.evaluate(() => {
            const main = document.querySelector('.site-main')
            // A child of the column centred by margin-inline: auto; one with `margin: 0` of a
            // module's class stands at the left edge of the page instead.
            const lefts = [...main.children]
              .filter((el) => !el.classList.contains('site-bleed'))
              .filter((el) => el.getBoundingClientRect().height > 0)
              .map((el) => ({
                what: el.className || el.tagName,
                left: Math.round(el.getBoundingClientRect().left),
              }))
            return {
              scroll: document.documentElement.scrollWidth,
              client: document.documentElement.clientWidth,
              lefts,
              inline: [...document.querySelectorAll('style')]
                .map((style) => style.textContent)
                .filter((css) =>
                  /\.wx-(events|event|vacancies|vacancy|press|recipes|recipe)[-_]/.test(css),
                ),
            }
          })
          const label = `${path} at ${width}`
          expect(m.scroll, label).toBeLessThanOrEqual(m.client)
          expect(m.lefts.length, label).toBeGreaterThan(0)
          for (const { what, left } of m.lefts) {
            expect(left, `${label}: ${what} in the column`).toBe(m.lefts[0].left)
          }
          expect(m.inline, `${label}: the module's inline look stays off under a theme`).toEqual([])
          await context.close()
        }
      }
    }
  })

  test('cards stand several to a row at 1280 and one under another at 375', async ({ browser }) => {
    const modules = (await installed(browser)).filter((m) => m.list)
    test.skip(modules.length === 0, 'the starter was built without --with')
    for (const module of modules) {
      for (const width of [375, 1280]) {
        const { context, page } = await open(browser, width, module.index)
        const rows = await page.evaluate(
          ({ list, card }) => {
            const first = document.querySelector(list)
            const cards = [...first.querySelectorAll(card)].slice(0, 3)
            const style = getComputedStyle(first)
            return {
              marker: cards.length ? getComputedStyle(cards[0]).listStyleType : 'none',
              padding: style.paddingInlineStart,
              tops: cards.map((c) => Math.round(c.getBoundingClientRect().top)),
            }
          },
          { list: module.list, card: module.card },
        )
        const label = `${module.index} at ${width}`
        expect(rows.marker, `${label}: no list markers`).toBe('none')
        expect(rows.padding, label).toBe('0px')
        if (rows.tops.length < 2) {
          await context.close()
          continue
        }
        if (width === 1280 && module.grid) {
          expect(rows.tops[1], `${label}: the second card beside the first`).toBe(rows.tops[0])
        } else {
          expect(rows.tops[1], `${label}: the second card under the first`).toBeGreaterThan(
            rows.tops[0],
          )
        }
        await context.close()
      }
    }
  })

  test('every link a finger reaches on a module page is at least 44px tall', async ({
    browser,
  }) => {
    const modules = (await installed(browser)).filter((m) => m.targets.length)
    test.skip(modules.length === 0, 'the starter was built without --with')
    for (const module of modules) {
      for (const path of module.pages) {
        const { context, page } = await open(browser, 375, path)
        const low = await page.evaluate(
          (selectors) =>
            [...document.querySelectorAll(selectors.join(', '))]
              .map((el) => ({
                what: `${el.className} "${el.textContent.trim().slice(0, 30)}"`,
                height: el.getBoundingClientRect().height,
              }))
              .filter((t) => t.height > 0 && t.height < 44),
          module.targets,
        )
        expect(low, path).toEqual([])
        await context.close()
      }
    }
  })

  test('a preset repaints an event, a vacancy, an outlet and a recipe: buttons, frames and lines on tokens', async ({
    browser,
  }) => {
    const modules = await installed(browser)
    const checks = [
      ['events', '.wx-event__book', 'backgroundColor', 'color-accent'],
      ['events', '.wx-event__facts', 'backgroundColor', 'color-surface'],
      ['vacancies', '.wx-vacancies__card', 'backgroundColor', 'color-surface'],
      ['vacancies', '.wx-vacancy__facts', 'borderTopColor', 'color-border'],
      ['press', '.wx-press-article', 'borderBottomColor', 'color-border'],
      ['recipes', '.wx-recipes__chip[aria-current]', 'backgroundColor', 'color-accent'],
      ['recipes', '.wx-recipe__facts', 'backgroundColor', 'color-surface'],
      ['recipes', '.wx-recipe__row', 'borderBottomColor', 'color-border'],
    ]
    let checked = 0
    for (const module of modules) {
      for (const path of module.pages) {
        const mine = checks.filter(([name]) => name === module.name)
        if (!mine.length) continue
        const { context, page } = await open(browser, 1280, path)
        const read = () =>
          page.evaluate((mine) => {
            const token = (name) => {
              const probe = document.createElement('i')
              probe.style.color = `var(--site-${name})`
              document.body.append(probe)
              const value = getComputedStyle(probe).color
              probe.remove()
              return value
            }
            return mine
              .map(([, selector, property, name]) => {
                const el = document.querySelector(selector)
                return el && { selector, value: getComputedStyle(el)[property], token: token(name) }
              })
              .filter(Boolean)
          }, mine)
        const before = await read()
        await paint(page, 'night')
        const after = await read()
        for (const [i, b] of before.entries()) {
          expect(b.value, `${path} ${b.selector}`).toBe(b.token)
          expect(after[i].value, `${path} ${b.selector} at night`).toBe(after[i].token)
          expect(after[i].value, `${path} ${b.selector} changes`).not.toBe(b.value)
          checked++
        }
        await context.close()
      }
    }
    test.skip(checked === 0, 'the starter was built without events, vacancies, press and recipes')
  })

  test('a recipes block keeps to the columns it asks for, and a phone gets one', async ({
    browser,
  }) => {
    // The demo's page of blocks: a showcase of three (the default) and a catalogue of two
    // (wx-recipes__grid--2) — as many to a row as asked at 1280 and never more, one at 375.
    for (const width of [375, 1280]) {
      const { context, page, status } = await open(browser, width, '/recipes-showcase')
      test.skip(status !== 200, 'the starter was built without recipes')
      const grids = await page.evaluate(() =>
        [...document.querySelectorAll('.wx-recipes__grid')].map((grid) => ({
          two: grid.classList.contains('wx-recipes__grid--2'),
          tops: [...grid.children].map((c) => Math.round(c.getBoundingClientRect().top)),
        })),
      )
      expect(grids.length, 'the showcase and the catalogue').toBe(2)
      expect(grids.filter((grid) => grid.two).length, 'the catalogue asks for two').toBe(1)
      for (const grid of grids) {
        const label = `${grid.two ? 'two' : 'three'} at ${width}`
        const first = grid.tops.filter((top) => top === grid.tops[0]).length
        if (width === 1280) expect(first, label).toBe(Math.min(grid.two ? 2 : 3, grid.tops.length))
        else expect(first, label).toBe(1)
      }
      await context.close()
    }
  })

  test('every word of a module page reads in every preset, the night one included', async ({
    browser,
  }) => {
    const modules = await installed(browser)
    test.skip(modules.length === 0, 'the starter was built without --with')
    for (const module of modules) {
      for (const path of module.pages) {
        const { context, page } = await open(browser, 1280, path)
        for (const preset of Object.keys(presets)) {
          await paint(page, preset)
          const low = await page.evaluate((selectors) => {
            const canvas = document
              .createElement('canvas')
              .getContext('2d', { willReadFrequently: true })
            const rgba = (color) => {
              canvas.clearRect(0, 0, 1, 1)
              canvas.fillStyle = color
              canvas.fillRect(0, 0, 1, 1)
              return Array.from(canvas.getImageData(0, 0, 1, 1).data)
            }
            const luminance = (color) => {
              const [r, g, b] = rgba(color)
                .slice(0, 3)
                .map((v) => {
                  v /= 255
                  return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
                })
              return 0.2126 * r + 0.7152 * g + 0.0722 * b
            }
            const ratio = (a, b) => {
              const [x, y] = [luminance(a), luminance(b)].sort((p, q) => q - p)
              return (x + 0.05) / (y + 0.05)
            }
            const behind = (el) => {
              for (let node = el; node; node = node.parentElement) {
                const bg = getComputedStyle(node).backgroundColor
                if (rgba(bg)[3] > 0) return bg
              }
              return 'white'
            }
            return [...document.querySelectorAll(selectors.join(', '))]
              .filter((el) => {
                const r = el.getBoundingClientRect()
                return r.width > 0 && r.height > 0 && el.textContent.trim() !== ''
              })
              .map((el) => ({
                what: `${el.className} "${el.textContent.trim().slice(0, 30)}"`,
                ratio: Math.round(ratio(getComputedStyle(el).color, behind(el)) * 100) / 100,
              }))
              .filter((w) => w.ratio < 4.5)
          }, module.words)
          expect(low, `${path} in ${preset}`).toEqual([])
        }
        await context.close()
      }
    }
  })

  test('the menu component keeps every level on the page, links 44px tall, nothing sideways at 360', async ({
    browser,
  }) => {
    for (const width of [360, 1280]) {
      const { context, page, status } = await open(browser, width, '/kitchen-sink/modules/menu')
      test.skip(status !== 200, 'the showcase has no menu page')
      const m = await page.evaluate(() => {
        const menu = document.querySelector('.wx-menu')
        if (!menu) return null
        const links = [...menu.querySelectorAll('.wx-menu__link')].filter(
          (a) => a.getBoundingClientRect().height > 0,
        )
        return {
          scroll: document.documentElement.scrollWidth,
          client: document.documentElement.clientWidth,
          display: getComputedStyle(menu).display,
          marker: getComputedStyle(menu.querySelector('.wx-menu__item')).listStyleType,
          hidden: [...menu.querySelectorAll('.wx-menu__link')].length - links.length,
          low: links.map((a) => a.getBoundingClientRect().height).filter((h) => h < 44),
        }
      })
      test.skip(m === null, 'the starter has no menus module')
      expect(m.scroll, `at ${width}`).toBeLessThanOrEqual(m.client)
      expect(m.display).toBe('flex')
      expect(m.marker).toBe('none')
      expect(m.hidden, 'every level on the page').toBe(0)
      expect(m.low, 'links a finger can hit').toEqual([])
      await context.close()
    }
  })
})

test.describe('icons', () => {
  test('every icon of the sprite draws, from one published file', async ({ page }) => {
    await page.goto('/kitchen-sink/icons')
    await still(page)
    const icons = await page.$$eval('.b-showcase-icons__item', (items) =>
      items.map((item) => {
        const use = item.querySelector('.theme-icon use')
        return {
          name: item.querySelector('.b-showcase-icons__name').textContent.trim(),
          href: use?.getAttribute('href') ?? null,
          // A line has no height (minus), so either side will do.
          drawn: use ? use.getBBox().width + use.getBBox().height > 0 : false,
        }
      }),
    )
    expect(icons.length, 'the default set').toBeGreaterThanOrEqual(27)
    const files = new Set(icons.map((icon) => icon.href?.split('#')[0]))
    expect(files.size, 'one sprite').toBe(1)
    const [file] = files
    expect(file).toMatch(/\/themes\/icons\/[0-9a-f]{12}\/icons\.svg$/)
    for (const icon of icons) {
      expect(icon.href, icon.name).toBe(`${file}#${icon.name}`)
      expect(icon.drawn, `${icon.name} draws`).toBe(true)
    }
    const sprite = await page.request.get(file)
    expect(sprite.status()).toBe(200)
    expect(sprite.headers()['content-type']).toContain('image/svg+xml')
  })

  test('an icon is a letter wide on the line, in the colour of the text, and decoration unless named', async ({
    page,
  }) => {
    await page.goto('/kitchen-sink/icons')
    await still(page)
    const m = await page.evaluate(() => {
      const box = (el) => el.getBoundingClientRect()
      const line = document.querySelector('.b-showcase-icons__line')
      const icon = line.querySelector('.theme-icon')
      const font = parseFloat(getComputedStyle(line).fontSize)
      const sizes = [...document.querySelectorAll('.b-showcase-icons__sizes .theme-icon')]
      return {
        font,
        width: box(icon).width,
        height: box(icon).height,
        // The icon sits inside the line box of the words around it.
        inside: box(icon).top >= box(line).top - 1 && box(icon).bottom <= box(line).bottom + 1,
        colour: getComputedStyle(icon).color === getComputedStyle(line).color,
        sizes: sizes.map((s) => Math.round((box(s).width / font) * 4) / 4),
        named: sizes.map((s) => [
          s.getAttribute('role'),
          s.getAttribute('aria-label'),
          s.getAttribute('aria-hidden'),
        ]),
      }
    })
    expect(Math.abs(m.width - m.font)).toBeLessThanOrEqual(0.5)
    expect(Math.abs(m.height - m.font)).toBeLessThanOrEqual(0.5)
    expect(m.inside).toBe(true)
    expect(m.colour).toBe(true)
    expect(m.sizes).toEqual([1, 1.5, 2, 3])
    expect(m.named.slice(0, 3)).toEqual([
      [null, null, 'true'],
      [null, null, 'true'],
      [null, null, 'true'],
    ])
    expect(m.named[3][0]).toBe('img')
    expect(m.named[3][1]).toBeTruthy()
    expect(m.named[3][2]).toBeNull()
  })

  test('a preset repaints the icons with the words', async ({ page }) => {
    await page.goto('/kitchen-sink/blocks/contact')
    await still(page)
    const colours = async () =>
      page.evaluate(() => ({
        label: getComputedStyle(document.querySelector('.b-contact__label .theme-icon')).color,
        accent: (() => {
          const probe = document.createElement('span')
          probe.style.color = 'var(--site-color-accent)'
          document.body.append(probe)
          const colour = getComputedStyle(probe).color
          probe.remove()
          return colour
        })(),
      }))
    const before = await colours()
    expect(before.label, 'the contacts block draws its labels in the accent').toBe(before.accent)
    await paint(page, 'night')
    const after = await colours()
    expect(after.label).toBe(after.accent)
    expect(after.label).not.toBe(before.label)
  })

  test('the labels of the contacts block carry their icon on the middle of the line', async ({
    page,
  }) => {
    await page.goto('/kitchen-sink/blocks/contact')
    await still(page)
    const labels = await page.$$eval('.b-contact__label', (all) =>
      all.map((label) => {
        const icon = label.querySelector('.theme-icon')
        const a = icon?.getBoundingClientRect()
        const b = label.getBoundingClientRect()
        return icon ? Math.abs(a.top + a.height / 2 - (b.top + b.height / 2)) : null
      }),
    )
    expect(labels.length).toBeGreaterThan(0)
    for (const offset of labels) {
      expect(offset, 'an icon in every label').not.toBeNull()
      expect(offset).toBeLessThanOrEqual(2)
    }
  })
})

test.describe('assets', () => {
  test('a picture of the theme answers 200 from a hashed path, cached forever', async ({
    page,
  }) => {
    await page.goto('/kitchen-sink/assets')
    await still(page)
    const m = await page.evaluate(() => {
      const picture = document.querySelector('.b-showcase-assets__picture')
      return {
        src: picture.src,
        loaded: picture.complete && picture.naturalWidth > 0,
        address: document.querySelector('.b-showcase-assets__address').textContent.trim(),
      }
    })
    expect(m.src).toMatch(/\/themes\/webx-ui\/theme-default\/[0-9a-f]{12}\/assets\/img\/wave\.svg$/)
    expect(m.loaded, 'the browser drew it').toBe(true)
    expect(m.address).toBe(m.src)
    const response = await page.request.get(m.src)
    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toContain('image/svg+xml')
    const cache = response.headers()['cache-control'] ?? ''
    expect(cache, 'cached forever').toContain('immutable')
    expect(Number(/max-age=(\d+)/.exec(cache)?.[1] ?? 0)).toBeGreaterThanOrEqual(31_536_000)
  })

  test('the same file as a mask takes the accent, and night repaints it', async ({ page }) => {
    await page.goto('/kitchen-sink/assets')
    await still(page)
    const colours = async () =>
      page.evaluate(() => {
        const mask = document.querySelector('.b-showcase-assets__mask')
        const probe = document.createElement('span')
        probe.style.color = 'var(--site-color-accent)'
        document.body.append(probe)
        const accent = getComputedStyle(probe).color
        probe.remove()
        const style = getComputedStyle(mask)
        return {
          paint: style.backgroundColor,
          accent,
          image: style.maskImage || style.webkitMaskImage,
          height: mask.getBoundingClientRect().height,
        }
      })
    const before = await colours()
    expect(before.image).toMatch(/assets\/img\/wave\.svg/)
    expect(before.height).toBeGreaterThan(0)
    expect(before.paint).toBe(before.accent)
    await paint(page, 'night')
    const after = await colours()
    expect(after.paint).toBe(after.accent)
    expect(after.paint).not.toBe(before.paint)
  })
})

test.describe('fonts', () => {
  // The font files the document downloaded, once the browser has settled which faces it needs.
  async function fontFiles(page) {
    await page.evaluate(() => document.fonts.ready)
    return page.evaluate(() =>
      performance
        .getEntriesByType('resource')
        .map((entry) => new URL(entry.name).pathname)
        .filter((path) => /\/assets\/fonts\/[^/]+\.woff2$/.test(path))
        .map((path) => path.split('/').pop()),
    )
  }

  // What actually draws the text of an element: the fonts Chromium picked for its glyphs.
  async function drawnWith(page, selector) {
    const cdp = await page.context().newCDPSession(page)
    await cdp.send('DOM.enable')
    await cdp.send('CSS.enable')
    const { root } = await cdp.send('DOM.getDocument', { depth: -1 })
    const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector })
    const { fonts } = await cdp.send('CSS.getPlatformFontsForNode', { nodeId })
    await cdp.detach()
    return fonts
  }

  test('an English page downloads no Cyrillic and no Latin Extended; Russian and Polish add theirs', async ({
    browser,
  }) => {
    for (const [path, wanted, unwanted] of [
      ['/', ['inter-latin.woff2'], ['inter-cyrillic.woff2', 'inter-latin-ext.woff2']],
      ['/ru', ['inter-latin.woff2', 'inter-cyrillic.woff2'], ['inter-latin-ext.woff2']],
      ['/pl', ['inter-latin.woff2', 'inter-latin-ext.woff2'], ['inter-cyrillic.woff2']],
    ]) {
      // A context of its own each: nothing in the memory cache from the page before.
      const context = await browser.newContext()
      const page = await context.newPage()
      await page.goto(path)
      await still(page)
      const files = await fontFiles(page)
      for (const file of wanted) expect(files, `${path} downloads ${file}`).toContain(file)
      for (const file of unwanted)
        expect(files, `${path} does not download ${file}`).not.toContain(file)
      await context.close()
    }
  })

  test('the faces come from the theme, a file per script, cached forever', async ({ page }) => {
    await page.goto('/kitchen-sink/fonts')
    await still(page)
    const faces = await page.evaluate(() =>
      [...document.querySelectorAll('style[data-webx-fonts]')]
        .map((style) => style.textContent)
        .join(''),
    )
    expect(faces.match(/@font-face/g)?.length, 'three scripts, upright and italic').toBe(6)
    expect(faces).not.toMatch(/fonts\.googleapis|fonts\.gstatic/)
    // Any absolute address in the faces is the site's own, whichever host the run points at.
    const own = new URL(process.env.STARTER_URL ?? 'http://webx-starter-test.local').origin
    for (const address of faces.match(/https?:\/\/[^\s)'"]+/g) ?? []) {
      expect(address.startsWith(own), address).toBe(true)
    }
    const files = await fontFiles(page)
    // Every script on the page at once: the three upright files.
    for (const file of ['inter-latin.woff2', 'inter-latin-ext.woff2', 'inter-cyrillic.woff2']) {
      expect(files).toContain(file)
    }
    const url = /url\("([^"]+inter-cyrillic\.woff2)"\)/.exec(faces)?.[1]
    expect(url).toMatch(/\/themes\/webx-ui\/theme-default\/[0-9a-f]{12}\/assets\/fonts\//)
    const response = await page.request.get(url)
    expect(response.status()).toBe(200)
    expect(response.headers()['cache-control'] ?? '').toContain('immutable')
  })

  test('Polish ą ł, Turkish ğ ş and Cyrillic are drawn in Inter; Greek, which has no file, is not', async ({
    page,
  }) => {
    await page.goto('/pl/witryna/fonty')
    await still(page)
    await page.evaluate(() => document.fonts.ready)
    const inter = (fonts) =>
      fonts.every((font) => font.isCustomFont && /inter/i.test(font.familyName))
    for (const lang of ['pl', 'tr', 'ru', 'uk', 'en']) {
      const fonts = await drawnWith(page, `.b-showcase-fonts__sample[lang="${lang}"]`)
      expect(fonts.length, lang).toBeGreaterThan(0)
      expect(inter(fonts), `${lang}: ${JSON.stringify(fonts)}`).toBe(true)
    }
    // The control: the same measurement says "not Inter" where the theme has no file.
    const greek = await drawnWith(page, '.b-showcase-fonts__sample[lang="el"]')
    expect(
      greek.some((font) => !font.isCustomFont),
      JSON.stringify(greek),
    ).toBe(true)

    // And the Polish of the page itself, not only the sample.
    await page.goto('/pl')
    await still(page)
    await page.evaluate(() => document.fonts.ready)
    const marked = await page.evaluate(() => {
      const walker = document.createTreeWalker(
        document.querySelector('main') ?? document.body,
        NodeFilter.SHOW_TEXT,
      )
      for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (
          /[ąęłńśźżó]/.test(node.textContent) &&
          node.parentElement.closest('[hidden]') === null
        ) {
          node.parentElement.setAttribute('data-latin-ext-probe', '')
          return node.textContent.trim().slice(0, 40)
        }
      }
      return null
    })
    expect(marked, 'the Polish home page has a letter past U+00FF').not.toBeNull()
    const fonts = await drawnWith(page, '[data-latin-ext-probe]')
    expect(inter(fonts), `"${marked}": ${JSON.stringify(fonts)}`).toBe(true)
  })

  test('the switcher names another language in the reader font, the current one in the theme font', async ({
    page,
  }) => {
    await page.goto('/')
    await still(page)
    const m = await page.evaluate(() => {
      const family = (selector) => {
        const element = document.querySelector(selector)
        return element ? getComputedStyle(element).fontFamily : null
      }
      return {
        other: family('.webx-language-switcher__name.is-other'),
        current: family('.webx-language-switcher__link.is-current .webx-language-switcher__name'),
      }
    })
    expect(m.other).toMatch(/^system-ui/)
    expect(m.current).toMatch(/^Inter/)
  })
})

test.describe('mail', () => {
  // THEMES §7.5: the letter module-inbox sends, in the theme's frame, every colour a token's value
  // written on the server — the showcase builds it for answers nobody sent.
  async function letter(page, path) {
    await page.goto(path)
    await still(page)
    const frame = page.locator('iframe.b-showcase-mail__letter')
    await frame.scrollIntoViewIfNeeded()
    const srcdoc = await frame.getAttribute('srcdoc')
    const inside = page.frameLocator('iframe.b-showcase-mail__letter')
    await inside.locator('a[href*="inbox/submissions"]').waitFor()
    return { srcdoc, inside }
  }

  test('the letter stands in the theme’s frame, painted with the values of the tokens', async ({
    page,
  }) => {
    const { srcdoc, inside } = await letter(page, '/kitchen-sink/mail')
    expect(srcdoc, 'no CSS variable reaches a mail client').not.toContain('var(')
    expect(srcdoc).not.toMatch(/<link\b|class="/)
    const accent = await page.evaluate(() => {
      const probe = document.createElement('span')
      probe.style.color = 'var(--site-color-accent)'
      document.body.append(probe)
      const value = getComputedStyle(probe).color
      probe.remove()
      return value
    })
    const m = await inside.locator('body').evaluate((body) => {
      const button = body.querySelector('a[href*="inbox/submissions"]')
      const brand = body.querySelector('a')
      return {
        button: getComputedStyle(button).backgroundColor,
        brand: getComputedStyle(brand).color,
        text: body.innerText,
        width: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      }
    })
    expect(m.button, 'the button is the accent of the site').toBe(accent)
    expect(m.brand, 'so is the name of the site above').toBe(accent)
    expect(m.text).toContain('Ada Lovelace')
    expect(m.text).toContain('ada@example.com')
    expect(m.text).toContain('Sent from')
    expect(m.width, 'the letter fits its frame').toBeLessThanOrEqual(0)
  })

  test('the letter speaks the language of the page', async ({ page }) => {
    const { inside } = await letter(page, '/pl/witryna/poczta')
    const text = await inside.locator('body').innerText()
    expect(text).toContain('Wysłano ze strony')
    expect(text).toContain('Otwórz w panelu')
    expect(text).not.toContain('Sent from')
  })
})

test.describe('fields', () => {
  // WIDGETS §17.2, W6.1: the fields of a form — Kitchen sink › Forms › Fields shows each type in
  // each state, long German and Polish labels, and a column 20rem wide.
  const PATH = '/kitchen-sink/forms/fields'
  const TYPES = ['text', 'email', 'tel', 'url', 'password', 'number', 'textarea', 'select']
  const GROUPS = ['radio', 'checkbox', 'consent', 'date', 'file']

  /** The same page in another language: the address the English page names as its alternate. */
  async function inLanguage(page, locale) {
    await page.goto(PATH)
    const there = await page
      .locator(`link[rel="alternate"][hreflang="${locale}"]`)
      .getAttribute('href')
    expect(there, `${PATH} has a page in ${locale}`).toBeTruthy()
    await page.goto(there)
    await still(page)
  }

  test('every type stands in every state, and nothing scrolls sideways at 360', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)

    const m = await page.evaluate(() => {
      const block = document.querySelector('[data-wx-block="showcase-fields"]')
      return {
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        states: [...block.querySelectorAll('.b-showcase-fields__state')].map((state) => ({
          state: state.dataset.state,
          types: [...state.querySelectorAll('.webx-field')].map((f) =>
            [...f.classList].find((c) => c.startsWith('webx-field--')).slice(12),
          ),
          hidden: state.querySelectorAll('input[type=hidden][name$="[page]"]').length,
          invalid: state.querySelectorAll('.webx-field.is-invalid').length,
          disabled: state.querySelectorAll('.webx-field.is-disabled').length,
        })),
        // Wider than its field: a label or an error that pushed out of its column.
        wide: [...document.querySelectorAll('.webx-field *')]
          .filter((el) => {
            const field = el.closest('.webx-field').getBoundingClientRect()
            const r = el.getBoundingClientRect()
            return r.width > 0 && r.right > field.right + 1
          })
          .map((el) => el.className || el.tagName),
      }
    })

    expect(m.scroll, 'no horizontal scroll at 360').toBeLessThanOrEqual(0)
    expect(m.wide, 'everything of a field stays inside it').toEqual([])
    expect(m.states.map((s) => s.state)).toEqual(['empty', 'filled', 'invalid', 'disabled'])
    for (const s of m.states) {
      // Fourteen frames — a checkbox twice, a group and one tick — and the hidden input alone.
      expect(new Set(s.types), s.state).toEqual(new Set([...TYPES, ...GROUPS]))
      expect(s.types.filter((t) => t === 'checkbox').length, s.state).toBe(2)
      expect(s.hidden, `${s.state}: the hidden field`).toBe(1)
    }
    expect(m.states[2].invalid, 'every field but the one tick has an error to show').toBe(13)
    expect(m.states[3].disabled).toBe(14)
  })

  test('a control, a choice and a button are 44px at least on a phone', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)

    const low = await page.evaluate(() =>
      [
        ...document.querySelectorAll(
          '.webx-field__control:not(.webx-field__control--file), .webx-field__choice, .webx-field__show, .webx-field__file-button, .webx-field__file',
        ),
      ]
        .map((el) => ({ what: el.className, height: el.getBoundingClientRect().height }))
        .filter((el) => el.height > 0 && el.height < 44),
    )
    expect(low).toEqual([])

    // The password's Show is the script's: here, and named.
    const show = page.locator('.webx-field__show').first()
    await expect(show).toBeVisible()
    await expect(show).toHaveAttribute('aria-label', 'Show password')
  })

  test('the focus is visible on a control, a box and a button', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto(PATH)
    await still(page)

    // From the keyboard, as :focus-visible wants it: into the first field, then Tab along.
    await page
      .locator('.b-showcase-fields__state[data-state="empty"] input[type=text]')
      .first()
      .focus()
    const seen = new Map()
    for (let i = 0; i < 40 && seen.size < 5; i++) {
      const m = await page.evaluate(() => {
        const el = document.activeElement
        const target = el.matches('input[type=file]') ? el.closest('.webx-field__file') : el
        const s = getComputedStyle(target)
        return {
          kind: el.matches('input[type=file]')
            ? 'file'
            : el.matches('.webx-field__check')
              ? 'check'
              : el.matches('.webx-field__show')
                ? 'show'
                : el.matches('select')
                  ? 'select'
                  : el.matches('.webx-field__control')
                    ? 'control'
                    : 'other',
          style: s.outlineStyle,
          width: parseFloat(s.outlineWidth),
        }
      })
      if (m.kind !== 'other' && !seen.has(m.kind)) seen.set(m.kind, m)
      await page.keyboard.press('Tab')
    }
    expect([...seen.keys()].sort()).toEqual(['check', 'control', 'file', 'select', 'show'])
    for (const [kind, m] of seen) {
      expect(m.style, `${kind}: an outline`).not.toBe('none')
      expect(m.width, `${kind}: 2px at least`).toBeGreaterThanOrEqual(2)
    }
  })

  test('an error is words under its field, told to a screen reader, and reads in every preset', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto(PATH)
    await still(page)

    const wiring = await page.evaluate(() =>
      [
        // The first block: every type once in each state.
        ...document
          .querySelector('[data-wx-block="showcase-fields"]')
          .querySelectorAll(
            '.b-showcase-fields__state[data-state="invalid"] .webx-field.is-invalid',
          ),
      ].map((field) => {
        const error = field.querySelector('.webx-field__error')
        const group = field.matches('fieldset')
        const described = group ? field : field.querySelector('[aria-describedby]')
        const controls = [...field.querySelectorAll('input, select, textarea')]
        return {
          text: error.textContent.trim(),
          shown: !error.hidden && error.getBoundingClientRect().height > 0,
          described: (described?.getAttribute('aria-describedby') ?? '')
            .split(' ')
            .includes(error.id),
          invalid: controls.every((c) => c.getAttribute('aria-invalid') === 'true'),
          legend: group ? field.querySelector(':scope > legend')?.textContent.trim() : null,
        }
      }),
    )
    expect(wiring.length).toBe(13)
    for (const w of wiring) {
      expect(w.text.length, 'the error is words').toBeGreaterThan(3)
      expect(w.shown).toBe(true)
      expect(w.described, `"${w.text}" is the field's description`).toBe(true)
      expect(w.invalid, `"${w.text}": the control says it is wrong`).toBe(true)
      if (w.legend !== null) expect(w.legend.length).toBeGreaterThan(0)
    }

    for (const preset of Object.keys(presets)) {
      await paint(page, preset)
      const low = await page.evaluate(() => {
        const canvas = document
          .createElement('canvas')
          .getContext('2d', { willReadFrequently: true })
        const rgba = (color) => {
          canvas.clearRect(0, 0, 1, 1)
          canvas.fillStyle = color
          canvas.fillRect(0, 0, 1, 1)
          return Array.from(canvas.getImageData(0, 0, 1, 1).data)
        }
        const luminance = (color) => {
          const [r, g, b] = rgba(color)
            .slice(0, 3)
            .map((v) => {
              v /= 255
              return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
            })
          return 0.2126 * r + 0.7152 * g + 0.0722 * b
        }
        const ratio = (a, b) => {
          const [x, y] = [luminance(a), luminance(b)].sort((p, q) => q - p)
          return (x + 0.05) / (y + 0.05)
        }
        const behind = (el) => {
          for (let node = el; node; node = node.parentElement) {
            const bg = getComputedStyle(node).backgroundColor
            if (rgba(bg)[3] > 0) return bg
          }
          return 'white'
        }
        return [
          ...document.querySelectorAll(
            '.webx-field__error:not([hidden]), .webx-field__label, .webx-field__hint, .webx-field__choice-label, .webx-field__control:not(.webx-field__control--file)',
          ),
        ]
          .filter((el) => el.getBoundingClientRect().height > 0 && !el.closest('.is-disabled'))
          .map((el) => ({
            what: `${el.className} "${(el.value || el.textContent).trim().slice(0, 30)}"`,
            ratio: Math.round(ratio(getComputedStyle(el).color, behind(el)) * 100) / 100,
          }))
          .filter((w) => w.ratio < 4.5)
      })
      expect(low, preset).toEqual([])
    }
  })

  test('Show turns a password to text and back; a file is named in the page’s language', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await inLanguage(page, 'pl')

    const field = page
      .locator('[data-wx-block="showcase-fields"]')
      .first()
      .locator('.b-showcase-fields__state[data-state="empty"] .webx-field--password')
    const input = field.locator('input')
    const show = field.locator('.webx-field__show')
    await expect(show).toHaveText('Pokaż')
    await expect(show).toHaveAttribute('aria-label', 'Pokaż hasło')
    await show.click()
    await expect(input).toHaveAttribute('type', 'text')
    await expect(show).toHaveAttribute('aria-pressed', 'true')
    await show.click()
    await expect(input).toHaveAttribute('type', 'password')

    const file = page
      .locator('[data-wx-block="showcase-fields"]')
      .first()
      .locator('.b-showcase-fields__state[data-state="empty"] .webx-field--file')
    await expect(file.locator('.webx-field__file-button')).toHaveText('Wybierz pliki')
    await expect(file.locator('.webx-field__file-name')).toHaveText('Nie wybrano pliku')
    const blob = { mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') }
    await file.locator('input[type=file]').setInputFiles([{ name: 'umowa.pdf', ...blob }])
    await expect(file.locator('.webx-field__file-name')).toHaveText('umowa.pdf')
    await file.locator('input[type=file]').setInputFiles([
      { name: 'a.pdf', ...blob },
      { name: 'b.pdf', ...blob },
    ])
    // Choosing again in a field of several adds to what was chosen (files.js, W6.3).
    await expect(file.locator('.webx-field__file-name')).toHaveText('Wybrane pliki: 3')
    // The words beside a label too.
    await expect(page.locator('.webx-field__optional').first()).toHaveText('(opcjonalnie)')
  })

  test('the words of a field are Russian on the Russian page', async ({ page }) => {
    await inLanguage(page, 'ru')
    const main = await page.locator('main').innerText()
    for (const word of ['Показать', 'необязательно', 'Выбрать файлы', 'Выберите…']) {
      expect(main, word).toContain(word)
    }
    for (const word of ['Show', 'optional', 'Choose a file', 'Choose files', 'No file chosen']) {
      expect(main, word).not.toContain(word)
    }
  })

  test('long labels break inside the word; a narrow column puts row choices one under another', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto(PATH)
    await still(page)

    const m = await page.evaluate(() => {
      const blocks = [...document.querySelectorAll('[data-wx-block="showcase-fields"]')]
      const choices = (b, type) =>
        [
          ...blocks[b].querySelectorAll(
            `.b-showcase-fields__state[data-state="${b === 2 ? 'invalid' : 'empty'}"] .webx-field--${type} .webx-field__choice`,
          ),
        ].map((el) => {
          const r = el.getBoundingClientRect()
          return { top: Math.round(r.top), left: Math.round(r.left) }
        })
      return {
        wide: choices(0, 'radio'),
        narrow: choices(2, 'radio'),
        column: blocks[2].querySelector('.b-showcase-fields__states').getBoundingClientRect().width,
      }
    })

    // At 1280 the radios that stand in a row share their top; in 20rem they stand one under another.
    expect(new Set(m.wide.map((c) => c.top)).size).toBe(1)
    expect(m.wide.length).toBe(3)
    expect(m.column).toBeLessThanOrEqual(20 * 16 + 1)
    expect(new Set(m.narrow.map((c) => c.left)).size).toBe(1)
    expect(new Set(m.narrow.map((c) => c.top)).size).toBe(m.narrow.length)

    // Loaded at 360, not shrunk from 1280: under the load of the whole run a page measured right
    // after the window narrowed has not always laid itself out again.
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)
    const long = await page.evaluate(() => {
      const block = document.querySelectorAll('[data-wx-block="showcase-fields"]')[1]
      return {
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        wide: [...block.querySelectorAll('.webx-field *')]
          .filter(
            (el) => el.getBoundingClientRect().right > block.getBoundingClientRect().right + 1,
          )
          .map((el) => el.className || el.tagName),
      }
    })
    expect(long.wide, 'the German word breaks inside its field').toEqual([])
    expect(long.scroll, 'and does not push the page').toBeLessThanOrEqual(0)
  })

  test('without JavaScript: no Show, and the file input is the browser’s own', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 375, height: 812 },
    })
    const page = await context.newPage()
    await page.goto(PATH)

    const m = await page.evaluate(() => {
      const file = document.querySelector('.webx-field--file input[type=file]')
      return {
        show: [...document.querySelectorAll('.webx-field__show')].some(
          (b) => b.getBoundingClientRect().height > 0,
        ),
        fileOpacity: getComputedStyle(file).opacity,
        filePosition: getComputedStyle(file).position,
        button: getComputedStyle(document.querySelector('.webx-field__file-button')).display,
        errors: document.querySelectorAll('.webx-field__error:not([hidden])').length,
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      }
    })
    expect(m.show).toBe(false)
    expect(m.fileOpacity).toBe('1')
    expect(m.filePosition).toBe('static')
    expect(m.button).toBe('none')
    // The server's errors are HTML: they stand without a script.
    expect(m.errors).toBeGreaterThan(13)
    expect(m.scroll).toBeLessThanOrEqual(0)
    await context.close()
  })
})

test.describe('sending', () => {
  // WIDGETS §17.3, §17.4, W6.2: the shell of a form — Kitchen sink › Forms › Sending sends one
  // form to the showcase's door, on the page and in a dialog.
  const PATH = '/kitchen-sink/forms/sending'
  const PAGE_FORM = '[data-mode="page"] form'
  const DIALOG = '#showcase-sending-2'

  /** What the form shows wrong: each field's error by its validator name, and its alert. */
  const errorsOf = (page, scope) =>
    page.evaluate((scope) => {
      const form = document.querySelector(scope)
      return {
        fields: Object.fromEntries(
          [...form.querySelectorAll('[data-webx-field].is-invalid')].map((field) => [
            field.dataset.webxField,
            field.querySelector('.webx-field__error').textContent.trim(),
          ]),
        ),
        alert: form.querySelector('.webx-form__error').textContent.trim(),
      }
    }, scope)

  async function fill(page, scope, answer) {
    const form = page.locator(scope)
    await form.locator('input[name="showcase[name]"]').fill('Ann')
    await form.locator('input[name="showcase[email]"]').fill('ann@example.com')
    await form.locator(`input[name="showcase[answer]"][value="${answer}"]`).check()
    await form.locator('input[name="showcase[agree]"]').check()
  }

  /** Every request the form makes to the showcase's door or the token route, in order. */
  function watch(page) {
    const seen = []
    page.on('request', (request) => {
      const url = new URL(request.url())
      if (url.pathname.startsWith('/webx/widgets/'))
        seen.push(`${request.method()} ${url.pathname}`)
    })
    page.on('response', (response) => {
      const url = new URL(response.url())
      if (url.pathname.startsWith('/webx/widgets/')) seen.push(response.status())
    })
    return seen
  }

  test('an empty form comes back with the same errors with JavaScript and without', async ({
    browser,
    page,
  }) => {
    const plain = await browser.newContext({ javaScriptEnabled: false })
    const without = await plain.newPage()
    await without.goto(PATH)
    await without.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await without.waitForLoadState()
    expect(new URL(without.url()).pathname, 'back on the page it was sent from').toBe(PATH)
    const back = await errorsOf(without, PAGE_FORM)
    const returned = await without.locator(PAGE_FORM).getAttribute('data-webx-returned')
    await plain.close()

    await page.goto(PATH)
    await still(page)
    const seen = watch(page)
    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await expect(page.locator(`${PAGE_FORM} .webx-field.is-invalid`).first()).toBeVisible()
    const inPlace = await errorsOf(page, PAGE_FORM)

    expect(returned, 'the page knows which form was sent').toBe('')
    expect(Object.keys(back.fields).sort()).toEqual([
      'showcase.agree',
      'showcase.email',
      'showcase.name',
    ])
    expect(inPlace, 'the same words in the same places').toEqual(back)
    expect(seen, 'sent in place, no page loaded').toEqual(['POST /webx/widgets/showcase', 422])
    expect(new URL(page.url()).pathname).toBe(PATH)

    const m = await page.evaluate((scope) => {
      const form = document.querySelector(scope)
      const name = form.querySelector('input[name="showcase[name]"]')
      return {
        focus: document.activeElement === name,
        invalid: name.getAttribute('aria-invalid'),
        described: name.getAttribute('aria-describedby'),
        errorId: form.querySelector('[data-webx-field="showcase.name"] .webx-field__error').id,
      }
    }, PAGE_FORM)
    expect(m.focus, 'the focus on the first wrong field').toBe(true)
    expect(m.invalid).toBe('true')
    expect(m.described).toContain(m.errorId)

    // Corrected, the field's error goes as it is typed.
    await page.locator(`${PAGE_FORM} input[name="showcase[name]"]`).fill('Ann')
    await expect(page.locator(`${PAGE_FORM} [data-webx-field="showcase.name"]`)).not.toHaveClass(
      /is-invalid/,
    )
    await expect(page.locator(`${PAGE_FORM} [data-webx-field="showcase.email"]`)).toHaveClass(
      /is-invalid/,
    )
  })

  test('each answer: a thank-you in place, another page, the next step, or only the event', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)

    await fill(page, PAGE_FORM, 'message')
    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    const answer = page.locator(`${PAGE_FORM} .webx-form__message .webx-form__answer`)
    await expect(answer.locator('.webx-form__heading')).toHaveText('Thank you')
    await expect(answer.locator('.webx-form__text')).toContainText('The form arrived, Ann.')
    expect(
      await page.locator(`${PAGE_FORM} input[name="showcase[name]"]`).inputValue(),
      'emptied',
    ).toBe('')

    await page.goto(PATH)
    await still(page)
    await fill(page, PAGE_FORM, 'html')
    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await expect(page.locator(`${PAGE_FORM} .webx-form__body .webx-showcase-step`)).toContainText(
      'Step two, Ann',
    )
    expect(await page.locator(`${PAGE_FORM} .webx-form__body [data-webx-field]`).count()).toBe(0)

    await page.goto(PATH)
    await still(page)
    await page.evaluate((scope) => {
      window.__answers = []
      document
        .querySelector(scope)
        .addEventListener('webx:form:success', (event) => window.__answers.push(event.detail))
    }, PAGE_FORM)
    await fill(page, PAGE_FORM, 'nothing')
    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await expect.poll(() => page.evaluate(() => window.__answers.length)).toBe(1)
    const nothing = await page.evaluate(
      (scope) => ({
        answer: window.__answers[0],
        region: document.querySelector(`${scope} .webx-form__message`).childElementCount,
        busy: document.querySelector(scope).getAttribute('aria-busy'),
      }),
      PAGE_FORM,
    )
    expect(nothing.answer).toEqual({
      status: 200,
      response: { ok: true, heading: null, message: null, redirect: null, html: null },
    })
    expect(nothing.region, 'nothing to show').toBe(0)
    expect(nothing.busy).toBeNull()

    await fill(page, PAGE_FORM, 'redirect')
    await Promise.all([
      page.waitForURL('**/kitchen-sink/forms/fields'),
      page.locator(`${PAGE_FORM} .webx-form__submit`).click(),
    ])
  })

  test('a second press while the first is on its way sends nothing, and the form does not move', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    let posts = 0
    await page.route('**/webx/widgets/showcase', async (route) => {
      posts++
      await new Promise((resolve) => setTimeout(resolve, 800))
      await route.continue()
    })
    const button = page.locator(`${PAGE_FORM} .webx-form__submit`)
    // On the page, not in the window: pressing Enter in a field scrolls the field into view.
    const place = (scope) => {
      const r = document.querySelector(`${scope} .webx-form__submit`).getBoundingClientRect()
      return { x: r.x + scrollX, y: r.y + scrollY, width: r.width, height: r.height }
    }
    const before = await page.evaluate(place, PAGE_FORM)

    await button.click()
    // Held back, the button is not enabled to Playwright either: pressed as a person would.
    await button.click({ force: true })
    await page.locator(`${PAGE_FORM} input[name="showcase[name]"]`).press('Enter')
    const box = await page.evaluate(place, PAGE_FORM)
    const during = await page.evaluate((scope) => {
      const form = document.querySelector(scope)
      return {
        sending: form.classList.contains('is-sending'),
        busy: form.getAttribute('aria-busy'),
        held: form.querySelector('.webx-form__submit').getAttribute('aria-disabled'),
        focus: document.activeElement?.name ?? document.activeElement?.className,
      }
    }, PAGE_FORM)

    expect(during.sending).toBe(true)
    expect(during.busy).toBe('true')
    expect(during.held).toBe('true')
    expect(box, 'held back, the button keeps its place and size').toEqual(before)
    await expect(page.locator(`${PAGE_FORM} .webx-field.is-invalid`).first()).toBeVisible()
    expect(posts, 'three presses, one submission').toBe(1)
    expect(await page.locator(PAGE_FORM).getAttribute('aria-busy')).toBeNull()
  })

  test('an expired token is renewed and the form sent once more — once', async ({
    page,
    context,
  }) => {
    await page.goto(PATH)
    await still(page)
    const seen = watch(page)
    // What a page cached for hours carries: a token the session no longer knows — in place of
    // Laravel's cookie, at its path, not beside it at the page's.
    const expired = (page) => ({
      name: 'XSRF-TOKEN',
      value: 'expired',
      domain: new URL(page.url()).hostname,
      path: '/',
    })
    await context.addCookies([expired(page)])

    await fill(page, PAGE_FORM, 'message')
    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await expect(page.locator(`${PAGE_FORM} .webx-form__heading`)).toHaveText('Thank you')
    expect(seen).toEqual([
      'POST /webx/widgets/showcase',
      419,
      'GET /webx/widgets/csrf',
      204,
      'POST /webx/widgets/showcase',
      200,
    ])

    // A token route that hands nothing new: the second 419 is said, not tried a third time.
    await page.goto(PATH)
    await still(page)
    seen.length = 0
    await context.addCookies([expired(page)])
    await page.route('**/webx/widgets/csrf', (route) => route.fulfill({ status: 204 }))
    await fill(page, PAGE_FORM, 'message')
    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await expect(page.locator(`${PAGE_FORM} .webx-form__error`)).toContainText('open too long')
    expect(seen.filter((s) => s === 'POST /webx/widgets/showcase')).toHaveLength(2)
    expect(seen).not.toContain(200)
  })

  test('in a dialog the thank-you takes the place of the fields, and closed it is a fresh form', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)

    await page.locator('[data-mode="dialog"] .b-showcase-sending__open').click()
    const dialog = page.locator(DIALOG)
    await expect(dialog).toBeVisible()
    expect(await dialog.evaluate((d) => d.matches(':modal'))).toBe(true)

    await dialog.locator('.webx-form__submit').click()
    await expect(dialog.locator('.webx-field.is-invalid').first()).toBeVisible()
    expect(
      await page.evaluate(
        (id) => document.querySelector(id).contains(document.activeElement),
        DIALOG,
      ),
    ).toBe(true)

    await fill(page, `${DIALOG} form`, 'message')
    await dialog.locator('.webx-form__submit').click()
    await expect(dialog.locator('.webx-form__heading')).toHaveText('Thank you')
    const sent = await page.evaluate((id) => {
      const d = document.querySelector(id)
      const box = d.getBoundingClientRect()
      return {
        body: d.querySelector('.webx-form__body').hidden,
        done: document.activeElement === d.querySelector('.webx-form__done'),
        open: d.open,
        inside: box.left >= 0 && box.right <= window.innerWidth,
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        doneHeight: d.querySelector('.webx-form__done').getBoundingClientRect().height,
      }
    }, DIALOG)
    expect(sent).toEqual({
      body: true,
      done: true,
      open: true,
      inside: true,
      scroll: expect.any(Number),
      doneHeight: expect.any(Number),
    })
    expect(sent.scroll, 'nothing sideways').toBeLessThanOrEqual(0)
    expect(sent.doneHeight).toBeGreaterThanOrEqual(44)

    await dialog.locator('.webx-form__done').click()
    await expect(dialog).toBeHidden()
    await page.locator('[data-mode="dialog"] .b-showcase-sending__open').click()
    await expect(dialog.locator('.webx-form__body')).toBeVisible()
    expect(await dialog.locator('.webx-form__message').evaluate((r) => r.childElementCount)).toBe(0)
    expect(await dialog.locator('input[name="showcase[name]"]').inputValue()).toBe('')
  })

  test('without JavaScript a form in a dialog comes back in its dialog, open over the page', async ({
    browser,
  }) => {
    const context = await browser.newContext({
      javaScriptEnabled: false,
      viewport: { width: 375, height: 812 },
    })
    const page = await context.newPage()
    await page.goto(`${PATH}${DIALOG}`)
    await page.locator(`${DIALOG} .webx-form__submit`).click()
    await page.waitForLoadState()

    const m = await page.evaluate((id) => {
      const d = document.querySelector(id)
      return {
        open: d.open,
        position: getComputedStyle(d).position,
        errors: d.querySelectorAll('.webx-field.is-invalid').length,
        returned: d.querySelector('form').hasAttribute('data-webx-returned'),
      }
    }, DIALOG)
    expect(m).toEqual({ open: true, position: 'fixed', errors: 3, returned: true })
    await context.close()
  })

  test('at 360 nothing scrolls sideways, the button is 44px and the empty answer takes no room', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)

    const m = await page.evaluate((scope) => {
      const form = document.querySelector(scope)
      return {
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        button: form.querySelector('.webx-form__submit').getBoundingClientRect().height,
        message: form.querySelector('.webx-form__message').getBoundingClientRect().height,
        alert: form.querySelector('.webx-form__error').getBoundingClientRect().height,
        live: form.querySelector('.webx-form__message').getAttribute('aria-live'),
        role: form.querySelector('.webx-form__error').getAttribute('role'),
      }
    }, PAGE_FORM)
    expect(m.scroll).toBeLessThanOrEqual(0)
    expect(m.button).toBeGreaterThanOrEqual(44)
    expect(m.message).toBe(0)
    expect(m.alert).toBe(0)
    expect(m.live).toBe('polite')
    expect(m.role).toBe('alert')
  })

  test('on the Russian page the form, its errors and its thank-you are Russian', async ({
    page,
  }) => {
    await page.goto(PATH)
    const there = await page.locator('link[rel="alternate"][hreflang="ru"]').getAttribute('href')
    await page.goto(there)
    await still(page)

    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await expect(
      page.locator(`${PAGE_FORM} [data-webx-field="showcase.name"] .webx-field__error`),
    ).toContainText('Ваше имя')

    await fill(page, PAGE_FORM, 'message')
    await page.locator(`${PAGE_FORM} .webx-form__submit`).click()
    await expect(page.locator(`${PAGE_FORM} .webx-form__heading`)).toHaveText('Спасибо')
  })
})

test.describe('validation', () => {
  // WIDGETS §17.5, W6.3: Kitchen sink › Forms › Validation — one form checked in the browser by
  // FormRules and by the showcase's door with the same rules; the phone mask; the files.
  const PATH = '/kitchen-sink/forms/validation'
  const FORM = '.b-showcase-validation form'

  const errorsOf = (page) =>
    page.evaluate((scope) => {
      const form = document.querySelector(scope)
      return Object.fromEntries(
        [...form.querySelectorAll('[data-webx-field].is-invalid')].map((field) => [
          field.dataset.webxField,
          field.querySelector('.webx-field__error').textContent.trim(),
        ]),
      )
    }, FORM)

  function watch(page) {
    const seen = []
    page.on('request', (request) => {
      const url = new URL(request.url())
      if (url.pathname.startsWith('/webx/widgets/'))
        seen.push(`${request.method()} ${url.pathname}`)
    })
    page.on('response', (response) => {
      const url = new URL(response.url())
      if (url.pathname.startsWith('/webx/widgets/')) seen.push(response.status())
    })
    return seen
  }

  /** Wrong in every way the browser can tell. */
  async function wrong(page) {
    const form = page.locator(FORM)
    await form.locator('input[name="check[name]"]').fill('a')
    await form.locator('input[name="check[email]"]').fill('nope')
    await form.locator('input[name="check[phone]"]').fill('512')
    await form.locator('input[name="check[age]"]').fill('12')
    await form.locator('input[name="check[website]"]').fill('example')
    await form.locator('input[name="check[day]"]').fill('2025-06-01')
    await form.locator('input[name="check[password]"]').fill('short')
    await form.locator('input[name="check[password_confirmation]"]').fill('other')
  }

  /** Right in every way the browser can tell. */
  async function right(page, name = 'Ann') {
    const form = page.locator(FORM)
    await form.locator('input[name="check[name]"]').fill(name)
    await form.locator('input[name="check[email]"]').fill('ann@example.com')
    await form.locator('input[name="check[phone]"]').fill('+48512345678')
    await form.locator('input[name="check[age]"]').fill('')
    await form.locator('input[name="check[website]"]').fill('')
    await form.locator('input[name="check[day]"]').fill('2026-05-01')
    await form.locator('input[name="check[password]"]').fill('secret12')
    await form.locator('input[name="check[password_confirmation]"]').fill('secret12')
    await form.locator('input[name="check[plan]"][value="team"]').check()
    await form.locator('input[name="check[agree]"]').check()
  }

  const png = (name, bytes = 600) => ({
    name,
    mimeType: 'image/png',
    // A real PNG header: the server guesses the type by what is inside.
    buffer: Buffer.concat([
      Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'base64',
      ),
      Buffer.alloc(Math.max(0, bytes - 70)),
    ]),
  })

  test('the browser says what the server says, word for word, and sends nothing while one is wrong', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const seen = watch(page)
    await wrong(page)

    await page.locator(`${FORM} .webx-form__submit`).first().click()
    await expect(page.locator(`${FORM} .webx-field.is-invalid`).first()).toBeVisible()
    const browser = await errorsOf(page)
    expect(seen, 'nothing sent').toEqual([])
    expect(
      await page.evaluate(() => document.activeElement?.getAttribute('name')),
      'the focus on the first wrong field',
    ).toBe('check[name]')

    // The same, straight to the server: the button with formnovalidate skips the browser.
    await page.locator(`${FORM} .b-showcase-validation__server`).click()
    await expect.poll(() => seen.length).toBe(2)
    const server = await errorsOf(page)

    expect(seen).toEqual(['POST /webx/widgets/showcase/validation', 422])
    expect(Object.keys(browser).sort()).toEqual([
      'check.age',
      'check.agree',
      'check.day',
      'check.email',
      'check.name',
      'check.password',
      'check.phone',
      'check.plan',
      'check.website',
    ])
    expect(browser, 'one word in the browser and from the server').toEqual(server)
    expect(browser['check.password']).toContain('8')
    expect(browser['check.password']).toContain('confirmation')
  })

  test('a field is checked when it is left after typing, and loses its error as it is put right', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const name = page.locator(`${FORM} [data-webx-field="check.name"]`)
    const email = page.locator(`${FORM} [data-webx-field="check.email"]`)

    // Through a field without typing: nothing said.
    await email.locator('input').focus()
    await page.keyboard.press('Tab')
    await expect(email).not.toHaveClass(/is-invalid/)

    await name.locator('input').focus()
    await page.keyboard.type('a')
    await expect(name, 'not while typing').not.toHaveClass(/is-invalid/)
    await page.keyboard.press('Tab')
    await expect(name).toHaveClass(/is-invalid/)
    await expect(name.locator('.webx-field__error')).toContainText('Your name')
    expect(await name.locator('input').getAttribute('aria-invalid')).toBe('true')

    await name.locator('input').focus()
    await page.keyboard.type('nn')
    await expect(name, 'right again as it is typed').not.toHaveClass(/is-invalid/)

    // The confirmation puts its password right too.
    const password = page.locator(`${FORM} [data-webx-field="check.password"]`)
    await password.locator('input').fill('secret12')
    await password.locator('input').blur()
    await expect(password).toHaveClass(/is-invalid/)
    await page.locator(`${FORM} input[name="check[password_confirmation]"]`).fill('secret12')
    await expect(password).not.toHaveClass(/is-invalid/)
  })

  test('what only the server knows comes from the server; all right, the thank-you', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const seen = watch(page)

    await right(page, 'admin')
    await page.locator(`${FORM} .webx-form__submit`).first().click()
    await expect(page.locator(`${FORM} [data-webx-field="check.name"]`)).toHaveClass(/is-invalid/)
    expect(seen).toEqual(['POST /webx/widgets/showcase/validation', 422])
    expect(Object.keys(await errorsOf(page))).toEqual(['check.name'])

    await page.locator(`${FORM} input[name="check[name]"]`).fill('Ann')
    await page.locator(`${FORM} .webx-form__submit`).first().click()
    await expect(page.locator(`${FORM} .webx-form__heading`)).toHaveText('Everything is right')
  })

  test('the phone is put in international form as it is typed, pasted and filled in', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const phone = page.locator(`${FORM} input[name="check[phone]"]`)

    await phone.pressSequentially('48512345678')
    await expect(phone).toHaveValue('+48 512 345 678')
    expect(await phone.evaluate((input) => input.selectionStart), 'the caret at the end').toBe(15)

    // Backspace right after a space takes the digit before it, not only the space.
    await phone.press('ArrowLeft')
    await phone.press('ArrowLeft')
    await phone.press('ArrowLeft')
    await phone.press('Backspace')
    await expect(phone).toHaveValue('+48 512 346 78')

    // A paste in any form, and what an autofill sets.
    await phone.fill('')
    await phone.focus()
    await page.keyboard.insertText('+1 (212) 555-0123')
    await expect(phone).toHaveValue('+1 212 555 0123')
    await phone.evaluate((input) => {
      input.value = '00380441234567'
      input.dispatchEvent(new Event('change', { bubbles: true }))
    })
    await expect(phone).toHaveValue('+380 441 234 567')
  })

  test('files are dropped, listed with their sizes, refused by type and size before sending, removed, and sent through XMLHttpRequest', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const field = page.locator(`${FORM} [data-webx-field="check.photos"]`)
    const input = field.locator('input[type=file]')
    const seen = watch(page)

    await input.setInputFiles([
      png('one.png'),
      { name: 'cv.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n') },
    ])
    // A big one dropped on the field, as a person drags it from a folder.
    await page.evaluate(async (scope) => {
      const field = document.querySelector(`${scope} [data-webx-field="check.photos"]`)
      const data = new DataTransfer()
      data.items.add(new File([new Uint8Array(3 * 1024 * 1024)], 'huge.png', { type: 'image/png' }))
      field.dispatchEvent(
        new DragEvent('dragover', { dataTransfer: data, bubbles: true, cancelable: true }),
      )
      field.dispatchEvent(
        new DragEvent('drop', { dataTransfer: data, bubbles: true, cancelable: true }),
      )
    }, FORM)

    const rows = field.locator('.webx-field__files-item')
    await expect(rows).toHaveCount(3)
    expect(await rows.locator('.webx-field__files-name').allTextContents()).toEqual([
      'one.png',
      'cv.pdf',
      'huge.png',
    ])
    expect(await rows.nth(2).locator('.webx-field__files-size').textContent()).toMatch(/3\s*MB/)
    expect(
      await rows.nth(0).locator('.webx-field__files-preview').count(),
      'a picture shows itself',
    ).toBe(1)
    // Refused before anything is sent: the type of one, the size of the other, each by its row.
    await expect(field).toHaveClass(/is-invalid/)
    expect(
      await rows.evaluateAll((all) => all.map((row) => row.classList.contains('is-invalid'))),
    ).toEqual([false, true, true])
    const said = await field.locator('.webx-field__error').textContent()
    expect(said).toContain('Photos')
    expect(said).toContain('2048')
    expect(seen).toEqual([])

    await rows.nth(2).locator('.webx-field__files-remove').click()
    await rows.nth(1).locator('.webx-field__files-remove').click()
    await expect(rows).toHaveCount(1)
    await expect(field).not.toHaveClass(/is-invalid/)
    expect(
      await input.evaluate((el) => el.files.length),
      'the input holds what the list shows',
    ).toBe(1)

    // Sent through XMLHttpRequest — the one that tells the upload's progress.
    const kinds = []
    page.on('request', (request) => {
      if (request.url().includes('/showcase/validation')) kinds.push(request.resourceType())
    })
    await right(page)
    await page.locator(`${FORM} .webx-form__submit`).first().click()
    await expect(page.locator(`${FORM} .webx-form__heading`)).toHaveText('Everything is right')
    expect(kinds).toEqual(['xhr'])
  })

  test('without JavaScript the browser checks by itself, and the server says the same words', async ({
    browser,
    page,
  }) => {
    const plain = await browser.newContext({ javaScriptEnabled: false })
    const without = await plain.newPage()
    await without.goto(PATH)
    expect(
      await without.locator(FORM).getAttribute('novalidate'),
      'the browser’s checks on',
    ).toBeNull()
    await without.locator(`${FORM} .b-showcase-validation__server`).click()
    await without.waitForLoadState()
    const back = await errorsOf(without)
    await plain.close()

    await page.goto(PATH)
    await still(page)
    expect(
      await page.locator(FORM).evaluate((form) => form.noValidate),
      'the script’s instead',
    ).toBe(true)
    await page.locator(`${FORM} .webx-form__submit`).first().click()
    await expect(page.locator(`${FORM} .webx-field.is-invalid`).first()).toBeVisible()
    expect(await errorsOf(page)).toEqual(back)
  })

  test('at 360 nothing scrolls sideways; the rows of files and their buttons are 44px', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto(PATH)
    await still(page)
    await page
      .locator(`${FORM} [data-webx-field="check.photos"] input[type=file]`)
      .setInputFiles([png('a-very-long-name-of-a-photograph-taken-on-holiday-by-the-sea.png')])

    const m = await page.evaluate((scope) => {
      const form = document.querySelector(scope)
      const row = form.querySelector('.webx-field__files-item')
      const outside = [...form.querySelectorAll('*')].filter((el) => {
        const box = el.getBoundingClientRect()
        return box.width > 0 && box.right > document.documentElement.clientWidth + 0.5
      })
      return {
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        outside: outside.map((el) => el.className),
        row: row.getBoundingClientRect().height,
        remove: row.querySelector('.webx-field__files-remove').getBoundingClientRect().height,
        buttons: [...form.querySelectorAll('.webx-form__submit')].map(
          (b) => b.getBoundingClientRect().height,
        ),
      }
    }, FORM)
    expect(m.scroll).toBeLessThanOrEqual(0)
    expect(m.outside).toEqual([])
    expect(m.row).toBeGreaterThanOrEqual(44)
    expect(m.remove).toBeGreaterThanOrEqual(44)
    for (const height of m.buttons) expect(height).toBeGreaterThanOrEqual(44)
  })

  test('on the Polish page the names are Polish both ways, and so is the file list', async ({
    page,
  }) => {
    await page.goto(PATH)
    const there = await page.locator('link[rel="alternate"][hreflang="pl"]').getAttribute('href')
    await page.goto(there)
    await still(page)
    const seen = watch(page)

    await page.locator(`${FORM} .webx-form__submit`).first().click()
    const name = page.locator(`${FORM} [data-webx-field="check.name"] .webx-field__error`)
    await expect(name).toContainText('Twoje imię')
    const browser = await errorsOf(page)
    await page.locator(`${FORM} .b-showcase-validation__server`).click()
    await expect.poll(() => seen.length).toBe(2)
    expect(await errorsOf(page)).toEqual(browser)

    await page
      .locator(`${FORM} [data-webx-field="check.photos"] input[type=file]`)
      .setInputFiles([png('a.png')])
    await expect(page.locator(`${FORM} .webx-field__files-remove`)).toHaveText('Usuń')
    await expect(page.locator(`${FORM} .webx-field__drop`)).toHaveText('lub upuść pliki tutaj')
  })
})

test.describe('inbox on the form engine', () => {
  // WIDGETS §17, W6.4: a form built in the panel's Inbox — Kitchen sink › Forms › Inbox — drawn by
  // the same fields and shell as every form of the site, checked by the intake's own rules.
  const PATH = '/kitchen-sink/forms/inbox'
  const FORM = 'form.webx-inbox-form[data-webx-form="showcase"]'
  // The showcase form asks a submission to wait three seconds after the page (antispam.min_seconds).
  const WAIT = 3500

  /** What the form shows wrong: each field's error by its validator name, and its alert. */
  const errorsOf = (page) =>
    page.evaluate((scope) => {
      const form = document.querySelector(scope)
      return {
        fields: Object.fromEntries(
          [...form.querySelectorAll('[data-webx-field].is-invalid')].map((field) => [
            field.dataset.webxField,
            field.querySelector('.webx-field__error').textContent.trim(),
          ]),
        ),
        alert: form.querySelector('.webx-form__error').textContent.trim(),
      }
    }, FORM)

  /** Every request to the intake, and its answer, in order. */
  function watch(page) {
    const seen = []
    page.on('request', (request) => {
      const url = new URL(request.url())
      if (url.pathname.startsWith('/webx/forms/')) seen.push(`${request.method()} ${url.pathname}`)
    })
    page.on('response', (response) => {
      const url = new URL(response.url())
      if (url.pathname.startsWith('/webx/forms/')) seen.push(response.status())
    })
    return seen
  }

  test('is the engine: fields, the shell without a token, the captcha place and no script of its own', async ({
    page,
  }) => {
    await page.goto(PATH)
    const m = await page.evaluate((scope) => {
      const form = document.querySelector(scope)
      return {
        send: form.hasAttribute('data-webx-send'),
        token: !!form.querySelector('input[name="_token"]'),
        csrf: form.hasAttribute('data-webx-csrf'),
        id: form.querySelector('input[name="_webx_form"]')?.value,
        fields: [...form.querySelectorAll('.webx-field[data-webx-field]')].map(
          (field) => field.dataset.webxField,
        ),
        checks: form.querySelectorAll('[data-webx-rules]').length,
        legacy: document.querySelectorAll('[class*="wx-form"]').length,
        script: [...document.scripts].some((s) => /\/webx\/forms\/inbox/.test(s.src)),
        novalidate: form.noValidate,
      }
    }, FORM)
    expect(m.send).toBe(true)
    expect(m.token, 'the intake guards itself').toBe(false)
    expect(m.csrf).toBe(false)
    expect(m.id).toMatch(/^webx-inbox-showcase/)
    expect(m.fields).toEqual([
      'fields.name',
      'fields.email',
      'fields.phone',
      'fields.date',
      'fields.topic',
      'fields.reply',
      'fields.interests',
      'fields.attachment',
      'fields.message',
      'fields.consent',
    ])
    expect(m.checks, 'every field checks by the intake rules').toBe(10)
    expect(m.legacy, 'no class of before W6.4').toBe(0)
    expect(m.script, 'inbox.js comes only with a captcha').toBe(false)
    expect(m.novalidate, 'the browser bubbles off').toBe(true)
  })

  test('an empty form gets the same errors from the browser and from the intake', async ({
    browser,
    page,
  }) => {
    // Without JavaScript and without the browser's own bubbles: the intake answers, the page
    // comes back with its words under the fields.
    const plain = await browser.newContext({ javaScriptEnabled: false })
    const without = await plain.newPage()
    const seenWithout = watch(without)
    await without.goto(PATH)
    await without.waitForTimeout(WAIT)
    await without.evaluate((scope) => (document.querySelector(scope).noValidate = true), FORM)
    await without.locator(`${FORM} .webx-form__submit`).click()
    await without.waitForLoadState()
    expect(new URL(without.url()).pathname, 'back on the page it was sent from').toBe(PATH)
    const server = await errorsOf(without)
    const returned = await without.locator(FORM).getAttribute('data-webx-returned')
    await plain.close()

    // With JavaScript: the browser says it, and nothing is sent.
    await page.goto(PATH)
    await still(page)
    const seen = watch(page)
    await page.locator(`${FORM} .webx-form__submit`).click()
    await expect(page.locator(`${FORM} .webx-field.is-invalid`).first()).toBeVisible()
    const inBrowser = await errorsOf(page)

    expect(seenWithout).toEqual(['POST /webx/forms/showcase', 302])
    expect(returned).toBe('')
    expect(Object.keys(server.fields).sort()).toEqual([
      'fields.consent',
      'fields.email',
      'fields.message',
      'fields.name',
      'fields.topic',
    ])
    expect(server.fields['fields.name']).toBe('The Your name field is required.')
    expect(inBrowser, 'the same words in the same places').toEqual(server)
    expect(seen, 'nothing sent while one is wrong').toEqual([])
    expect(
      await page.evaluate(() => document.activeElement?.name),
      'the focus on the first wrong field',
    ).toBe('fields[name]')

    // A wrong address said when the field is left, in the intake's words.
    await page.locator(`${FORM} input[name="fields[email]"]`).fill('ann')
    await page.locator(`${FORM} input[name="fields[name]"]`).focus()
    await expect(
      page.locator(`${FORM} [data-webx-field="fields.email"] .webx-field__error`),
    ).toHaveText('The Email field must be a valid email address.')
  })

  test('filled in, it is sent in place and thanked under its fields', async ({ page }) => {
    await page.goto(PATH)
    await still(page)
    const seen = watch(page)
    const form = page.locator(FORM)
    await form.locator('input[name="fields[name]"]').fill('Ann')
    await form.locator('input[name="fields[email]"]').fill('ann@example.com')
    await form.locator('select[name="fields[topic]"]').selectOption('question')
    await form.locator('textarea[name="fields[message]"]').fill('Only a showcase.')
    await form.locator('input[name="fields[consent]"]').check()
    await page.waitForTimeout(WAIT)
    await page.evaluate((scope) => {
      window.__sent = []
      document
        .querySelector(scope)
        .addEventListener('webx:form:success', (event) => window.__sent.push(event.detail.response))
    }, FORM)
    await form.locator('.webx-form__submit').click()

    await expect(form.locator('.webx-form__message .webx-form__heading')).toHaveText('Thank you')
    await expect(form.locator('.webx-form__message .webx-form__text')).toContainText(
      'This form only shows how fields look.',
    )
    expect(seen).toEqual(['POST /webx/forms/showcase', 200])
    expect(new URL(page.url()).pathname).toBe(PATH)
    expect(await page.evaluate(() => window.__sent)).toEqual([
      {
        ok: true,
        heading: 'Thank you',
        message: 'This form only shows how fields look. Nobody is told about it.',
        redirect: null,
        html: null,
      },
    ])
    expect(await form.locator('input[name="fields[name]"]').inputValue(), 'emptied').toBe('')
  })

  test('at 360 nothing goes sideways, and every target is 44px tall', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 })
    await page.goto(PATH)
    await still(page)
    const m = await page.evaluate((scope) => {
      const form = document.querySelector(scope)
      const lowest = (selector) =>
        Math.min(
          ...[...form.querySelectorAll(selector)]
            .filter((n) => n.getBoundingClientRect().height > 0)
            .map((n) => n.getBoundingClientRect().height),
        )
      // The honeypot is clipped to a pixel on purpose: its input is wide and seen by no one.
      const right = Math.max(
        ...[...form.querySelectorAll('*')]
          .filter((n) => !n.closest('.webx-inbox-form__honeypot'))
          .map((n) => n.getBoundingClientRect().right),
      )
      return {
        scroll: document.documentElement.scrollWidth,
        client: document.documentElement.clientWidth,
        right,
        control: lowest('.webx-field__control:not([type="file"])'),
        choice: lowest('.webx-field__choice'),
        file: lowest('.webx-field__file'),
        submit: form.querySelector('.webx-form__submit').getBoundingClientRect().height,
        // Half-width fields one under another on a phone.
        stacked:
          form.querySelector('[data-webx-field="fields.email"]').getBoundingClientRect().top >=
          form.querySelector('[data-webx-field="fields.name"]').getBoundingClientRect().bottom - 1,
      }
    }, FORM)
    expect(m.scroll).toBeLessThanOrEqual(m.client)
    expect(m.right, 'nothing of the form beyond the screen').toBeLessThanOrEqual(m.client + 0.5)
    expect(m.control).toBeGreaterThanOrEqual(44)
    expect(m.choice).toBeGreaterThanOrEqual(44)
    expect(m.file).toBeGreaterThanOrEqual(44)
    expect(m.submit).toBeGreaterThanOrEqual(44)
    expect(m.stacked).toBe(true)
  })

  test('in its dialog the thank-you takes the place of the fields, with Close in the focus', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 360, height: 740 })
    await page.goto('/kitchen-sink/dropdown-and-form')
    await still(page)
    await page.locator('a[href="#webx-form-contact"]').first().click()
    const dialog = page.locator('#webx-form-contact')
    await expect(dialog).toHaveJSProperty('open', true)
    const form = dialog.locator('form.webx-inbox-form')
    await form.locator('input[name="fields[name]"]').fill('Ann')
    await form.locator('input[name="fields[email]"]').fill('ann@example.com')
    await form.locator('textarea[name="fields[message]"]').fill('Only a showcase.')
    await page.waitForTimeout(WAIT)
    await form.locator('.webx-form__submit').click()

    await expect(form.locator('.webx-form__heading')).toBeVisible()
    await expect(form.locator('.webx-form__body')).toBeHidden()
    const done = form.locator('.webx-form__done')
    await expect(done).toBeVisible()
    await expect(done).toBeFocused()
    expect((await done.boundingBox()).height).toBeGreaterThanOrEqual(44)
    // The dialog's own Close of before W6.4 is not there: the form brings its own.
    expect(await dialog.locator('.webx-form-dialog__done').count()).toBe(0)

    await done.click()
    await expect(dialog).toHaveJSProperty('open', false)
    await page.locator('a[href="#webx-form-contact"]').first().click()
    await expect(form.locator('.webx-form__body')).toBeVisible()
    await expect(form.locator('.webx-form__heading')).toHaveCount(0)
  })

  test('on the Russian page its errors are the intake’s Russian words', async ({ page }) => {
    await page.goto(PATH)
    const there = await page.locator('link[rel="alternate"][hreflang="ru"]').getAttribute('href')
    await page.goto(there)
    await still(page)
    await page.locator(`${FORM} .webx-form__submit`).click()
    await expect(
      page.locator(`${FORM} [data-webx-field="fields.name"] .webx-field__error`),
    ).toContainText('Ваше имя')
    await expect(page.locator(`${FORM} .webx-form__submit`)).toHaveText('Отправить')
  })
})

test.describe('suggestions', () => {
  // WIDGETS §18, W6.5: Kitchen sink › Forms › Suggestions — a search box asking the showcase's
  // door, which answers HTML from the block's goods; a city field inside a delivery form.
  const PATH = '/kitchen-sink/forms/suggestions'
  const SEARCH = '.b-showcase-suggest[data-mode="search"]'
  const FIELD = '.b-showcase-suggest[data-mode="field"]'
  const DOOR = '**/webx/widgets/showcase/suggest?**'

  const aria = (page, scope) =>
    page.evaluate((scope) => {
      const input = document.querySelector(`${scope} .webx-suggest__input`)
      const panel = document.getElementById(input.getAttribute('aria-controls'))
      const active = document.getElementById(input.getAttribute('aria-activedescendant') || '')
      const group = panel.querySelector('[role="group"]')
      return {
        role: input.getAttribute('role'),
        expanded: input.getAttribute('aria-expanded'),
        autocomplete: input.getAttribute('aria-autocomplete'),
        listbox: panel.getAttribute('role'),
        options: [...panel.querySelectorAll('[role="option"]')].map((o) => o.textContent.trim()),
        selected: [...panel.querySelectorAll('[aria-selected="true"]')].map((o) => o.id),
        active: active?.id ?? null,
        activeText: active?.textContent.trim() ?? null,
        group: group
          ? document.getElementById(group.getAttribute('aria-labelledby'))?.textContent
          : null,
        status: document.querySelector(`${scope} .webx-suggest__status`).textContent,
        focused: document.activeElement === input,
      }
    }, scope)

  test('the keyboard alone: the combobox opens, walks, jumps, closes and takes an option', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const input = page.locator(`${SEARCH} .webx-suggest__input`)
    await input.focus()
    await page.keyboard.type('oak')
    await expect(page.locator(`${SEARCH} .webx-suggest__panel`)).toBeVisible()

    let a = await aria(page, SEARCH)
    expect(a.role).toBe('combobox')
    expect(a.autocomplete).toBe('list')
    expect(a.expanded).toBe('true')
    expect(a.listbox).toBe('listbox')
    expect(a.group).toBe('Products (8)')
    // Five cards with their price, and «see all» — every one an option.
    expect(a.options).toHaveLength(6)
    expect(a.options[0]).toContain('€')
    expect(a.options[5]).toBe('See all results: 8')
    expect(a.status).toBe('Suggestions: 6')
    expect(a.active).toBeNull()

    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('ArrowDown')
    a = await aria(page, SEARCH)
    expect(a.selected).toEqual([a.active])
    expect(a.activeText).toContain('Oak desk 160 cm')
    expect(a.focused, 'the focus stays in the field').toBe(true)
    await page.keyboard.press('End')
    expect((await aria(page, SEARCH)).activeText).toBe('See all results: 8')
    await page.keyboard.press('Home')
    expect((await aria(page, SEARCH)).activeText).toContain('Oak desk 120 cm')

    await page.keyboard.press('Escape')
    a = await aria(page, SEARCH)
    expect(a.expanded).toBe('false')
    await expect(page.locator(`${SEARCH} .webx-suggest__panel`)).toBeHidden()
    await expect(input).toHaveValue('oak')

    await page.keyboard.press('ArrowDown')
    await expect(page.locator(`${SEARCH} .webx-suggest__panel`)).toBeVisible()
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('Enter')
    await page.waitForURL(/\?q=Oak%20desk%20120%20cm/)
    await expect(page.locator(`${SEARCH} .b-showcase-suggest__item`)).toHaveCount(1)
  })

  test('a stale question is cancelled and its late answer dropped', async ({ page }) => {
    await page.goto(PATH)
    await still(page)
    const asked = []
    const failed = []
    page.on('requestfailed', (request) => failed.push(new URL(request.url()).searchParams.get('q')))
    await page.route(DOOR, async (route) => {
      const q = new URL(route.request().url()).searchParams.get('q')
      asked.push(q)
      // The first question answers slowly: the next one overtakes it.
      if (q === 'oak') await new Promise((resolve) => setTimeout(resolve, 1500))
      try {
        await route.continue()
      } catch {
        // Cancelled by the page while it waited.
      }
    })

    const input = page.locator(`${SEARCH} .webx-suggest__input`)
    await input.focus()
    await page.keyboard.type('oak')
    await expect.poll(() => asked).toEqual(['oak'])
    await input.fill('')
    await page.keyboard.type('lamp')
    await expect(page.locator(`${SEARCH} [data-webx-heading]`)).toHaveText('Products (4)')
    await page.waitForTimeout(1800)

    expect(asked).toEqual(['oak', 'lamp'])
    expect(failed, 'the stale request aborted').toContain('oak')
    await expect(page.locator(`${SEARCH} [data-webx-heading]`)).toHaveText('Products (4)')
    expect(await page.locator(`${SEARCH} .webx-suggest__panel`).textContent()).not.toContain('Oak')
  })

  test('nothing found is said, and a screen reader hears it', async ({ page }) => {
    await page.goto(PATH)
    await still(page)
    await page.locator(`${SEARCH} .webx-suggest__input`).focus()
    await page.keyboard.type('zebra')
    await expect(page.locator(`${SEARCH} .b-showcase-suggest__nothing`)).toHaveText(
      'Nothing found for “zebra”.',
    )
    expect((await aria(page, SEARCH)).status).toBe('Nothing found')
    expect((await aria(page, SEARCH)).options).toEqual([])
  })

  test('recent searches are kept in the browser, shown in an empty field and cleared', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const input = page.locator(`${SEARCH} .webx-suggest__input`)
    await input.focus()
    await page.keyboard.type('lamp')
    await page.keyboard.press('Enter')
    await page.waitForURL(/\?q=lamp/)
    await still(page)

    await input.fill('')
    await expect(page.locator(`${SEARCH} .webx-suggest__panel`)).toBeVisible()
    const a = await aria(page, SEARCH)
    expect(a.group).toBe('Recent searches')
    expect(a.options).toEqual(['lamp', 'Clear recent searches'])

    await page.keyboard.press('ArrowUp')
    await page.keyboard.press('Enter')
    await expect(page.locator(`${SEARCH} .webx-suggest__panel`)).toBeHidden()
    expect(
      await page.evaluate(() =>
        Object.keys(localStorage).filter((k) => k.startsWith('webx-suggest:')),
      ),
    ).toEqual([])
  })

  test('at 360 the panel stays inside the screen and every target is 44px', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 })
    await page.goto(PATH)
    await still(page)
    await page.locator(`${SEARCH} .webx-suggest__input`).focus()
    await page.keyboard.type('o')
    await page.keyboard.type('a')
    await expect(page.locator(`${SEARCH} .webx-suggest__panel`)).toBeVisible()

    const m = await page.evaluate((scope) => {
      const root = document.querySelector(scope)
      const panel = root.querySelector('.webx-suggest__panel').getBoundingClientRect()
      return {
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        width: document.documentElement.clientWidth,
        height: window.visualViewport.height,
        panel: { left: panel.left, right: panel.right, bottom: panel.bottom },
        input: root.querySelector('.webx-suggest__input').getBoundingClientRect().height,
        submit: root.querySelector('.webx-suggest__submit').getBoundingClientRect(),
        options: [...root.querySelectorAll('[role="option"]')].map(
          (o) => o.getBoundingClientRect().height,
        ),
      }
    }, SEARCH)
    expect(m.scroll).toBeLessThanOrEqual(0)
    expect(m.panel.left).toBeGreaterThanOrEqual(0)
    expect(m.panel.right).toBeLessThanOrEqual(m.width)
    expect(m.panel.bottom, 'the panel ends above the bottom of the screen').toBeLessThanOrEqual(
      m.height,
    )
    expect(m.input).toBeGreaterThanOrEqual(44)
    expect(m.submit.height).toBeGreaterThanOrEqual(44)
    expect(m.submit.width).toBeGreaterThanOrEqual(44)
    expect(m.options.length).toBeGreaterThan(0)
    for (const height of m.options) expect(height).toBeGreaterThanOrEqual(44)
  })

  test('without JavaScript it is a search: Enter opens the results page', async ({ browser }) => {
    const plain = await browser.newContext({ javaScriptEnabled: false })
    const page = await plain.newPage()
    await page.goto(PATH)
    const input = page.locator(`${SEARCH} .webx-suggest__input`)
    expect(await input.getAttribute('role'), 'no combobox without its script').toBeNull()
    await input.fill('oak')
    await input.press('Enter')
    await page.waitForURL(/\?q=oak/)
    await expect(page.locator(`${SEARCH} .b-showcase-suggest__results-heading`)).toHaveText(
      'Found for “oak”: 8',
    )
    await expect(page.locator(`${SEARCH} .b-showcase-suggest__item`)).toHaveCount(8)
    await plain.close()
  })

  test('inside another form a value fills the field and sends nothing; the form sends it', async ({
    page,
  }) => {
    await page.goto(PATH)
    await still(page)
    const asked = []
    page.on('request', (request) => {
      if (request.url().includes('/webx/widgets/showcase/suggest')) asked.push(request.url())
    })
    const input = page.locator(`${FIELD} .webx-suggest__input`)
    expect(await page.locator(`${FIELD} form`).count(), 'one form, the delivery’s').toBe(1)
    await input.focus()
    await page.keyboard.type('kra')
    await expect(page.locator(`${FIELD} [role="option"]`)).toHaveText(['Kraków'])
    expect(new URL(asked[0]).searchParams.get('as')).toBe('values')

    const url = page.url()
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('Enter')
    await expect(input).toHaveValue('Kraków')
    await expect(page.locator(`${FIELD} .webx-suggest__panel`)).toBeHidden()
    expect(page.url(), 'chosen, not sent').toBe(url)

    await page.locator(`${FIELD} .webx-form__submit`).click()
    await page.waitForURL(/city=Krak/)
    await expect(page.locator(`${FIELD} .b-showcase-suggest__chosen`)).toHaveText(
      'Delivery to Kraków.',
    )
  })

  test('on the Polish page the panel speaks Polish', async ({ page }) => {
    await page.goto(PATH)
    const there = await page.locator('link[rel="alternate"][hreflang="pl"]').getAttribute('href')
    await page.goto(there)
    await still(page)
    await page.locator(`${SEARCH} .webx-suggest__input`).focus()
    await page.keyboard.type('lamp')
    await expect(page.locator(`${SEARCH} [data-webx-heading]`)).toHaveText('Produkty (4)')
    const a = await aria(page, SEARCH)
    expect(a.status).toBe('Podpowiedzi: 5')
    expect(a.options.at(-1)).toBe('Wszystkie wyniki: 4')
  })
})

test.describe('words of the layout', () => {
  // The words the shell can print: the theme's own (THEMES §15.2) and those of the widgets it is
  // built of. Read from the dictionaries themselves, so a word added later is checked too.
  const words = (file) =>
    [
      ...readFileSync(new URL(`../../php/packages/${file}`, import.meta.url), 'utf8').matchAll(
        /=>\s*'((?:[^'\\]|\\.)*)'/g,
      ),
    ].map(([, value]) => value.replace(/\\'/g, "'"))
  const dictionary = (locale) => [
    ...words(`theme-default/lang/${locale}/theme.php`),
    ...words(`widgets/lang/${locale}/widgets.php`),
  ]

  test('a page in Russian or Polish prints no English word of the shell', async ({ page }) => {
    const english = dictionary('en').filter((w) => w.length >= 4 && !w.includes(':'))
    for (const [path, locale] of [
      ['/ru', 'ru'],
      ['/pl', 'pl'],
      ['/ru/no-such-page', 'ru'],
      ['/pl/no-such-page', 'pl'],
    ]) {
      await page.goto(path)
      await still(page)
      const own = new Set(dictionary(locale))
      // Everything but the page's own content: the shell, the banner, the dialogs, an error.
      const shell = await page.evaluate(() => {
        const body = document.body.cloneNode(true)
        body.querySelectorAll('script, style, template').forEach((node) => node.remove())
        body.querySelectorAll('main > :not(.site-error)').forEach((node) => node.remove())
        const attributes = [...body.querySelectorAll('[aria-label], [title], [alt], [placeholder]')]
          .flatMap((node) =>
            ['aria-label', 'title', 'alt', 'placeholder'].map((a) => node.getAttribute(a)),
          )
          .filter(Boolean)
        return [body.textContent, ...attributes].join('\n')
      })
      const left = english.filter((w) => !own.has(w) && shell.includes(w))
      expect(left, `${path} prints English words of the shell`).toEqual([])
      expect(await page.locator('.site-skip').textContent(), path).not.toBe('Skip to content')
      expect(shell, `${path} prints a key instead of a word`).not.toMatch(/\btheme\.[a-z_]+\./)
    }
  })

  // The demo of the modules beyond the default set (THEMES §15.2, TH4.4): a module page under
  // /ru or /pl reads in that language — none of the demo's English where the demo has the
  // language, none of the module's English words, and some of the demo's own words in it.
  const demoWords = (pkg, locale) => {
    const english = []
    const own = []
    const walk = (value) => {
      if (Array.isArray(value)) return value.forEach(walk)
      if (!value || typeof value !== 'object') return
      if (typeof value.en === 'string' && typeof value[locale] === 'string') {
        // Words a reader sees, not markup: tags dropped, the first line of a longer text.
        const plain = (text) =>
          text
            .replace(/<[^>]+>/g, ' ')
            .split('\n')[0]
            .trim()
        if (plain(value.en) !== plain(value[locale])) {
          english.push(plain(value.en))
          own.push(plain(value[locale]))
        }
        return
      }
      Object.values(value).forEach(walk)
    }
    const dir = new URL(`../../php/packages/${pkg}/resources/demo/`, import.meta.url)
    for (const file of readdirSync(dir).filter((f) => f.endsWith('.json'))) {
      walk(JSON.parse(readFileSync(new URL(file, dir), 'utf8')))
    }
    return { english: english.filter((w) => w.length >= 6), own }
  }
  const moduleWords = (pkg, locale) => {
    const dir = new URL(`../../php/packages/${pkg}/lang/${locale}/`, import.meta.url)
    return readdirSync(dir)
      .filter((f) => f.endsWith('.php') && f !== 'panel.php')
      .flatMap((f) => words(`${pkg}/lang/${locale}/${f}`))
  }

  for (const [pkg, index] of [
    ['module-blog', '/blog'],
    ['module-services', '/services'],
    ['module-events', '/events'],
    ['module-vacancies', '/careers'],
    ['module-press', '/press'],
    ['module-recipes', '/recipes'],
  ]) {
    test(`${index} in Russian and Polish reads in them, the index and a record`, async ({
      page,
    }) => {
      for (const locale of ['ru', 'pl']) {
        const demo = demoWords(pkg, locale)
        const own = new Set([...moduleWords(pkg, locale), ...dictionary(locale)])
        const english = [
          ...demo.english,
          ...moduleWords(pkg, 'en').filter((w) => w.length >= 6 && !w.includes(':')),
        ].filter((w) => !own.has(w))
        await page.goto(`/${locale}${index}`)
        await still(page)
        const record = await page
          .locator(`.site-main a[href*="/${locale}${index}/"]`)
          .first()
          .getAttribute('href')
        expect(record, `${locale}${index} links a record in its language`).toBeTruthy()
        for (const path of [`/${locale}${index}`, record]) {
          if (path !== `/${locale}${index}`) {
            await page.goto(path)
            await still(page)
          }
          const main = await page.locator('main').innerText()
          const left = english.filter((w) => main.includes(w))
          expect(left, `${path} prints English`).toEqual([])
          expect(
            demo.own.some((w) => main.includes(w)),
            `${path} prints the demo in ${locale}`,
          ).toBe(true)
        }
      }
    })
  }

  test('the showcase prints no English of its own types in Russian or Polish', async ({ page }) => {
    // The words the showcase types used to print from their templates, now fields (TH4.4).
    const english = [
      'What to try',
      'In this column',
      'A column 20rem wide',
      'Showcase watch',
      'that lasts',
      'More about',
      'Card 7',
      'Page 1 of',
    ]
    for (const locale of ['ru', 'pl']) {
      for (const path of [
        'background-video',
        'table-of-contents',
        'before-and-after',
        'zoom',
        'slider',
        'show-more',
        'counters-and-countdown',
      ]) {
        // The showcase's slugs are translated: the address in that language is the one the
        // English page names as its alternate.
        await page.goto(`/kitchen-sink/${path}`)
        const there = await page
          .locator(`link[rel="alternate"][hreflang="${locale}"]`)
          .getAttribute('href')
        expect(there, `/kitchen-sink/${path} has a page in ${locale}`).toBeTruthy()
        await page.goto(there)
        await still(page)
        const main = await page.locator('main').innerText()
        const left = english.filter((w) => main.includes(w))
        expect(left, `${there} prints English`).toEqual([])
      }
    }
  })
})
