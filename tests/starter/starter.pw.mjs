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
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter.local').origin

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
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter.local').origin
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
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter.local').origin
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
      '1 Example Street, London',
      'Pariser Platz, Berlin',
      'Paris',
      '1 Example Street, London',
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
  const site = new URL(process.env.STARTER_URL ?? 'http://webx-starter.local').origin
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
