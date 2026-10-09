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
    await expect(page.locator('#webx-form-contact form')).toHaveClass(/wx-form--modal/)
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

  // The demo's Contacts tab: three numbers, Mon–Fri 9–19 and Sat 10–16 in London.
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
    await expect(main).toHaveText('+44 20 7946 0958')
    await expect(main).toHaveAttribute('href', 'tel:+44-20-7946-0958')
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
    await expect(panel.locator('.webx-phones__label')).toHaveText(['Support', 'North America'])

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
    // 14:30 in New York on a Monday is 19:30 in London: closed there, not open here.
    const context = await browser.newContext({ timezoneId: 'America/New_York' })
    const page = await context.newPage()
    await page.clock.setFixedTime(new Date('2026-10-12T14:30:00-04:00'))
    await page.goto(PATH)
    const status = page.locator('.webx-header__topbar [data-webx-hours-status]')
    await expect(status).toHaveText('Closed, opens tomorrow at 9:00 AM')
    await expect(status).toHaveClass(/is-closed/)

    await page.clock.setFixedTime(new Date('2026-10-12T10:00:00+01:00'))
    await page.reload()
    await expect(status).toHaveText('Open until 7:00 PM')
    await expect(status).toHaveClass(/is-open/)

    // Sunday: a day off, and Monday next.
    await page.clock.setFixedTime(new Date('2026-10-18T12:00:00+01:00'))
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
            '<a class="webx-contact-bar__item webx-contact-bar__item--call" href="tel:+442079460958"><span>Call</span></a>' +
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

  // The starter site speaks English, Russian and Polish. The home page is in all three; the
  // showcase pages are in English only.
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
  })

  test('leads to the home page of a language without a translation, and marks it', async ({
    page,
  }) => {
    await page.goto(PATH)
    const all = await links(header(page))
    expect(all.find((l) => l.hreflang === 'en')).toMatchObject({
      path: PATH,
      current: 'page',
      fallback: false,
    })
    for (const code of ['ru', 'pl']) {
      const link = all.find((l) => l.hreflang === code)
      expect(link).toMatchObject({ path: `/${code}`, fallback: true, current: null })
      expect(link.title, `the fallback to ${code} says nothing`).toMatch(/\S/)
      await answers(page, link)
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
    const url = new URL(process.env.STARTER_URL ?? 'http://webx-starter.local')
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

      const hero = await measure(page, HERO)
      expect([hero.whole, hero.seen]).toEqual([1, 1])
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
    const url = new URL(process.env.STARTER_URL ?? 'http://webx-starter.local')
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

  // The section of the page by its heading: grid, gallery slider, links by hand.
  const section = (page, heading) =>
    page
      .locator('.b-showcase-lightbox')
      .filter({ has: page.getByRole('heading', { name: heading }) })

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

    // 2400 × 1600 on 375 × 667: the width of the screen, all of it on the screen.
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
    const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter.local').origin
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

    // No data-width, no data-height: measured before it opens — 2400 × 1600 at its proportions.
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

    const file = await page.request.get(hrefs[0])
    expect(file.status()).toBe(200)
    expect(file.headers()['content-type']).toContain('image/svg+xml')

    // Neither the script nor the words of a page that has no lightbox.
    expect(await page.locator('script[src*="lightbox.js"]').count()).toBe(1)
    const plain = await context.newPage()
    await plain.goto('/kitchen-sink/slider')
    expect(await plain.locator('script[src*="lightbox.js"], #webx-lightbox').count()).toBe(0)
    await context.close()
  })
})
