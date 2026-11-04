// @vitest-environment node
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  annotations,
  check,
  isGreen,
  mirrorUrl,
  npmUrl,
  packagistUrl,
  readPackages,
  report,
} from './release-preflight.mjs'

const fromCi = {
  versions: { '0.1.0': { dist: { attestations: { url: 'https://example.test' } } } },
}
const byHand = { versions: { '0.1.0': { dist: {} } } }

const packages = {
  npm: [{ dir: 'packages/module-a', name: '@webx-ui/module-a', version: '0.1.0' }],
  composer: ['module-a'],
}

/** Every service answers 200 unless the address is listed; a function may throw. */
function services(answers = {}) {
  return vi.fn(async (url) => {
    const answer = answers[url] ?? { status: 200 }
    if (answer instanceof Error) throw answer
    const { status, body = fromCi } = answer
    return { ok: status < 400, status, json: async () => body }
  })
}

const run = (answers, options = {}) =>
  check(packages, { fetcher: services(answers), pause: 0, ...options })

describe('release-preflight', () => {
  const temporary = []

  afterEach(() => {
    temporary.splice(0).forEach((dir) => rmSync(dir, { recursive: true, force: true }))
  })

  it('passes when everything is there', async () => {
    const result = await run()

    expect(isGreen(result)).toBe(true)
    expect(report(result)).toContain('npm 1/1, зеркала 1/1, Packagist 1/1')
    expect(annotations(result)).toEqual([])
  })

  it('asks for the first publish of a package npm does not have', async () => {
    const result = await run({ [npmUrl('@webx-ui/module-a')]: { status: 404 } })
    const text = report(result, { runId: '123' })

    expect(isGreen(result)).toBe(false)
    expect(text).toContain('нужна первая ручная публикация')
    expect(text).toContain('git worktree add ~/work/webx-ui-release origin/changeset-release/main')
    expect(text).toContain('pnpm install --frozen-lockfile && pnpm --filter "@webx-ui/module-a..."')
    expect(text).toContain('cd ~/work/webx-ui-release/packages/module-a && pnpm pack')
    expect(text).toContain("jq '.version, .dependencies, .peerDependencies'")
    expect(text).toContain('npm publish webx-ui-module-a-0.1.0.tgz --access public')
    expect(text).toContain('webx-ui / webx-ui / release.yml')
    expect(text).toContain('npm logout')
    expect(text).toContain('git worktree remove ~/work/webx-ui-release')
    expect(text).toContain('gh run rerun 123 --failed')
    expect(annotations(result)[0]).toMatch(/^::error::.*@webx-ui\/module-a/)
  })

  it('asks for the mirror that does not exist', async () => {
    const result = await run({ [mirrorUrl('module-a')]: { status: 404 } })
    const text = report(result)

    expect(isGreen(result)).toBe(false)
    expect(text).toContain('gh repo create webx-ui/module-a --public')
    expect(text).toContain('gh workflow run php-split.yml --ref main')
  })

  it('asks for the Submit of a package Packagist does not have', async () => {
    const result = await run({ [packagistUrl('module-a')]: { status: 404 } })
    const text = report(result)

    expect(isGreen(result)).toBe(false)
    expect(text).toContain('https://packagist.org/packages/submit')
    expect(text).toContain('https://github.com/webx-ui/module-a')
  })

  it('checks only the mirrors on the day pull request', async () => {
    const fetcher = services({ [mirrorUrl('module-a')]: { status: 404 } })
    const result = await check(packages, { fetcher, pause: 0, mirrorsOnly: true })
    const text = report(result)

    expect(fetcher.mock.calls.map(([url]) => url)).toEqual([mirrorUrl('module-a')])
    expect(text).toContain('Preflight релиза (зеркала 0/1)')
    expect(text).toContain('после мержа этого PR')
    expect(text).not.toContain('gh workflow run')
  })

  it('never reads an outage as a missing package', async () => {
    const url = packagistUrl('module-a')
    const fetcher = services({ [url]: { status: 503 } })
    const result = await check(packages, { fetcher, pause: 0 })

    expect(isGreen(result)).toBe(false)
    expect(result.packagist).toEqual([])
    expect(result.unknown).toEqual(['Packagist module-a: HTTP 503'])
    expect(report(result)).toContain('сбой сервиса')
    expect(fetcher.mock.calls.filter(([address]) => address === url)).toHaveLength(3)
  })

  it('survives a network error the same way', async () => {
    const result = await run({ [mirrorUrl('module-a')]: new Error('socket hang up') })

    expect(result.unknown).toEqual(['зеркало module-a: socket hang up'])
    expect(isGreen(result)).toBe(false)
  })

  it('counts what it could not check apart from what it found', async () => {
    const result = await check(
      { npm: packages.npm, composer: ['module-a', 'module-b', 'module-c'] },
      {
        fetcher: services({
          [mirrorUrl('module-b')]: { status: 503 },
          [mirrorUrl('module-c')]: { status: 404 },
        }),
        pause: 0,
      },
    )
    const text = report(result)

    expect(isGreen(result)).toBe(false)
    expect(text).toContain(
      'Preflight релиза (npm 1/1, зеркала 1/3, не проверено 1, Packagist 3/3): релиз не готов.',
    )
    expect(text).not.toContain('GH_TOKEN')
  })

  it.each([403, 429])('points at the token when GitHub answers %i', async (status) => {
    const fetcher = services({ [mirrorUrl('module-a')]: { status } })
    const text = report(await check(packages, { fetcher, pause: 0, mirrorsOnly: true }))

    expect(text).toContain('Preflight релиза (зеркала 0/1, не проверено 1): релиз не готов.')
    expect(text).toContain('GH_TOKEN=$(gh auth token) node scripts/release-preflight.mjs')
  })

  it('does not advise a token to a run that sent one, or for another service', async () => {
    const limited = await run({ [mirrorUrl('module-a')]: { status: 403 } }, { token: 'secret' })
    const npm = await run({ [npmUrl('@webx-ui/module-a')]: { status: 429 } })

    expect(report(limited)).not.toContain('GH_TOKEN')
    expect(report(npm)).not.toContain('GH_TOKEN')
  })

  it('retries a passing failure', async () => {
    const fetcher = vi
      .fn(services())
      .mockResolvedValueOnce({ ok: false, status: 502 })
      .mockResolvedValueOnce({ ok: false, status: 502 })
    const result = await check({ npm: [], composer: ['module-a'] }, { fetcher, pause: 0 })

    expect(isGreen(result)).toBe(true)
  })

  it('warns, and only warns, about a package that never came from CI', async () => {
    const result = await run({ [npmUrl('@webx-ui/module-a')]: { status: 200, body: byHand } })

    expect(isGreen(result)).toBe(true)
    expect(result.unproven).toEqual(['@webx-ui/module-a'])
    expect(report(result)).toContain('ни одна версия не вышла из CI')
    expect(annotations(result)).toEqual([expect.stringMatching(/^::warning::/)])
  })

  it('sends the token to GitHub alone', async () => {
    const fetcher = services()
    await check(packages, { fetcher, pause: 0, token: 'secret' })

    for (const [url, { headers }] of fetcher.mock.calls) {
      expect(headers.authorization).toBe(
        url === mirrorUrl('module-a') ? 'Bearer secret' : undefined,
      )
    }
  })

  it('reads the public npm packages, the composer packages and the skeleton', () => {
    const root = mkdtempSync(join(tmpdir(), 'preflight-'))
    temporary.push(root)
    const write = (file, content) => {
      mkdirSync(dirname(join(root, file)), { recursive: true })
      writeFileSync(join(root, file), JSON.stringify(content))
    }

    write('packages/core/package.json', { name: '@webx-ui/core', version: '1.0.0' })
    write('packages/internal/package.json', { name: '@webx-ui/internal', private: true })
    write('php/packages/cache/composer.json', { name: 'webx-ui/cache' })
    write('php/site/composer.json', { name: 'webx-ui/site' })

    expect(readPackages(root)).toEqual({
      npm: [{ dir: 'packages/core', name: '@webx-ui/core', version: '1.0.0' }],
      composer: ['cache', 'site'],
    })
  })
})
