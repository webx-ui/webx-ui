<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use WebxUi\Themes\ThemeCritical;

/**
 * The page half of `webx:theme:critical` — `resources/critical/page.mjs` — in Chromium, on a
 * page of tests/Fixtures/critical-fold: the rules it keeps are read, and the first screen with
 * them alone (every stylesheet refused) is drawn against the full CSS, as `--check` does.
 *
 * Playwright comes from the monorepo's node_modules; without it or its Chromium the test skips.
 */
class CriticalFoldTest extends BaseTestCase
{
    #[Test]
    public function a_footer_the_first_screen_cuts_keeps_the_rules_of_the_row_below_that_sizes_it(): void
    {
        // The footer starts at 700px of a 900px screen, its texture `inset: 0; object-fit: cover`
        // scales by the footer's height, and that height comes from the bottom row, which is
        // below the first screen. Without the row's rules the footer is shorter and the texture
        // seen at the top of it is drawn otherwise.
        $answer = $this->browser();

        $this->assertStringContainsString('.site-footer__bottom', $answer['css'], 'The row that sizes the footer is kept.');
        $this->assertStringContainsString('.site-footer__texture', $answer['css']);
        $this->assertStringNotContainsString('.site-note', $answer['css'], 'What is wholly below and hangs on nothing seen stays out.');

        foreach ($answer['results'] as $result) {
            $this->assertSame(0, $result['differ'], 'The first screen at '.implode('×', $result['viewport']).' differs.');
        }
    }

    /**
     * @return array{css: string, results: list<array{viewport: list<int>, differ: int}>}
     */
    private function browser(): array
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node is not installed.');
        }

        $critical = realpath(__DIR__.'/../resources/critical');
        $script = <<<'JS'
            import { createRequire } from 'node:module'
            import { createServer } from 'node:http'
            import { readFileSync } from 'node:fs'
            import { extname, join } from 'node:path'
            import { pathToFileURL } from 'node:url'

            const [dir, root, critical, below] = process.argv.slice(1)
            const out = (value) => process.stdout.write(JSON.stringify(value))
            const require = createRequire(join(root, 'package.json'))
            let chromium
            for (const name of ['playwright', '@playwright/test']) {
              try {
                ;({ chromium } = require(name))
                break
              } catch {}
            }
            if (!chromium) {
              out({ skip: 'Playwright is not installed' })
              process.exit(0)
            }
            const { collect, compare } = await import(pathToFileURL(join(critical, 'page.mjs')))
            const { mergeRules, serializeRules, splitSelectors } = await import(
              pathToFileURL(join(critical, 'rules.mjs'))
            )

            const types = { '.html': 'text/html', '.css': 'text/css', '.svg': 'image/svg+xml' }
            const server = createServer((request, response) => {
              const path = request.url === '/' ? '/index.html' : request.url.split('?')[0]
              try {
                const body = readFileSync(join(dir, path))
                response.writeHead(200, { 'content-type': types[extname(path)] ?? 'text/plain' })
                response.end(body)
              } catch {
                response.writeHead(404)
                response.end()
              }
            })
            await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve))
            const base = `http://127.0.0.1:${server.address().port}/`

            let browser
            try {
              browser = await chromium.launch(
                process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {},
              )
            } catch (error) {
              server.close()
              out({ skip: 'Chromium: ' + String(error.message).split('\n')[0] })
              process.exit(0)
            }

            const viewports = [[1440, 900], [390, 844]]
            const open = async (viewport, css) => {
              const context = await browser.newContext({
                viewport: { width: viewport[0], height: viewport[1] },
              })
              if (css !== undefined)
                // As `@webxTheme` prints it: inline in the head, every stylesheet refused.
                await context.route('**/*', async (route) => {
                  const type = route.request().resourceType()
                  if (type === 'stylesheet') return route.abort()
                  if (type !== 'document') return route.continue()
                  const response = await route.fetch()
                  const html = (await response.text()).replace('</head>', `<style>${css}</style></head>`)
                  return route.fulfill({ response, body: html })
                })
              const page = await context.newPage()
              await page.goto(base, { waitUntil: 'load' })
              await page.evaluate(() => document.fonts.ready)
              return { context, page }
            }

            const screens = []
            for (const viewport of viewports) {
              const { context, page } = await open(viewport)
              const found = await page.evaluate(`(${collect})(${below}, ${splitSelectors})`)
              screens.push(found.flatMap((sheet) => sheet.rules))
              await context.close()
            }
            const css = serializeRules(mergeRules(screens).rules)

            const blank = await (await browser.newContext()).newPage()
            const results = []
            for (const viewport of viewports) {
              const shots = []
              for (const inline of [undefined, css]) {
                const { context, page } = await open(viewport, inline)
                shots.push((await page.screenshot({ animations: 'disabled', caret: 'hide' })).toString('base64'))
                await context.close()
              }
              results.push({ viewport, differ: await blank.evaluate(compare, shots) })
            }
            await browser.close()
            server.close()
            out({ css, results })
            JS;

        $process = new Process(
            [$node, '--input-type=module', '-e', $script, __DIR__.'/Fixtures/critical-fold', realpath(__DIR__.'/../../../..'), $critical, (string) ThemeCritical::BELOW],
            null,
            null,
            null,
            180,
        );
        $process->mustRun();

        $answer = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        if (isset($answer['skip'])) {
            $this->markTestSkipped($answer['skip']);
        }

        return $answer;
    }
}
