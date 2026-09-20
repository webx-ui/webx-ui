import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { resolveIcon } from '@webx-ui/core'

const packages = resolve(dirname(fileURLToPath(import.meta.url)), '../../../php/packages')

function walk(dir: string): string[] {
  const found: string[] = []

  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name)

    if (entry.isDirectory()) found.push(...walk(path))
    else if (entry.name.endsWith('.php')) found.push(path)
  }

  return found
}

/**
 * The code and the configuration of every package, and nothing else: `'icon' => …` is also how
 * a language file spells the word "Icon", and a fixture may name a picture it never draws.
 */
function sources(): string[] {
  const found: string[] = []

  for (const entry of readdirSync(packages, { withFileTypes: true })) {
    if (!entry.isDirectory()) continue

    for (const room of ['src', 'config']) {
      const dir = join(packages, entry.name, room)

      if (existsSync(dir)) found.push(...walk(dir))
    }
  }

  return found
}

/**
 * The playground's fixtures, which are a site's data rather than a test's: every block type
 * there carries an icon the panel draws. All eight named icons from another set entirely, and
 * nothing said so until a picker put the set on screen beside them.
 */
const fixtures = resolve(
  dirname(fileURLToPath(import.meta.url)),
  '../../../apps/playground/server/panel',
)

function demoNames(): Array<[string, string]> {
  const names: Array<[string, string]> = []

  if (!existsSync(fixtures)) return names

  for (const entry of readdirSync(fixtures)) {
    if (!entry.endsWith('.ts')) continue

    for (const match of readFileSync(join(fixtures, entry), 'utf8').matchAll(/icon: '([^']+)'/g)) {
      names.push([match[1]!, `playground/${entry}`])
    }
  }

  return names
}

/** Every name a server-side module hands the front end to draw, with the file that named it. */
function named(): Array<[string, string]> {
  const names: Array<[string, string]> = [...demoNames()]

  for (const file of sources()) {
    const source = readFileSync(file, 'utf8')
    const relative = file.slice(packages.length + 1).replace(/\\/g, '/')

    // `public function icon(): string { return 'tag'; }` — what a section shows.
    for (const match of source.matchAll(/function icon\(\): string\s*\{\s*return '([^']+)';/g)) {
      names.push([match[1]!, relative])
    }

    // `'icon' => 'newspaper'` — what a navigation group shows.
    for (const match of source.matchAll(/'icon' => '([^']+)'/g)) {
      names.push([match[1]!, relative])
    }
  }

  return names
}

/**
 * An icon name that is not in the set draws nothing at all.
 *
 * `WxIcon` renders no `<svg>` when `resolveIcon` comes back empty — no warning, no placeholder,
 * just a menu line whose label has slid left into the room the picture was meant to take. That
 * is how `file-text` stood in `ArticlesModule` unnoticed: it reads like a name from the set, it
 * type-checks on both halves, and the only thing that knows better is the set itself. Neither
 * half can catch this alone, so the seam is checked here.
 */
describe('the icons the server names are icons that exist', () => {
  const entries = named()

  it('finds the names to check at all', () => {
    // A regex that stops matching would otherwise turn this whole file green.
    expect(entries.length).toBeGreaterThan(10)
  })

  it.each(entries)('%s (%s)', (name) => {
    expect(resolveIcon(name)).toBeDefined()
  })
})
