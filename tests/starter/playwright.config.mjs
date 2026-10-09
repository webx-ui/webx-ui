// `pnpm test:starter` — the browser checks of the starter site and its showcase (THEMES §18.4,
// WIDGETS §16). Local only, not in CI: it needs the site that `scripts/starter-site.sh` builds
// (http://webx-starter.local by default, STARTER_URL to point elsewhere), and the gate already
// runs into memory and time.
//
// The browser is the Edge every Windows machine has, so the run downloads nothing; elsewhere,
// or with PW_CHANNEL=chromium, Playwright's own Chromium (`npx playwright install chromium`).
//
// The report with a screenshot of every page at 375 and 1280 in every preset is what goes to a
// person, not "the tests are green": tests/starter/report/index.html.

import { defineConfig } from '@playwright/test'

const channel = process.env.PW_CHANNEL ?? (process.platform === 'win32' ? 'msedge' : undefined)

export default defineConfig({
  testDir: '.',
  testMatch: '*.pw.mjs',
  outputDir: './results',
  timeout: 60_000,
  fullyParallel: false,
  workers: 1,
  reporter: [['list'], ['html', { outputFolder: './report', open: 'never' }]],
  use: {
    baseURL: process.env.STARTER_URL ?? 'http://webx-starter.local',
    channel: channel === 'chromium' ? undefined : channel,
    ignoreHTTPSErrors: true,
  },
})
