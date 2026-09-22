// A Composer package and its npm half are one thing installed twice, and nothing but
// `extra.webx.npm` records which is which. The npm halves version one by one, the Composer
// ones share a version, and the two sets are bumped by the same `changeset version` run — so
// this pushes the freshly written npm versions into the Composer manifests, and the pairing is
// fixed in the same commit as the versions themselves.
//
// Run by `pnpm version-packages`, right after `changeset version`.
//
// Only keys that name a package in this repository are touched: `vue` and friends sit in the
// same map because the panel does not build without them, and their ranges are ours to choose.

import { readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const packagesRoot = join(repoRoot, 'php', 'packages')

/** @param {string} path */
const readJson = (path) => JSON.parse(readFileSync(path, 'utf8'))

/** Every npm package in this repository, by name. */
const versions = new Map()

for (const entry of readdirSync(join(repoRoot, 'packages'), { withFileTypes: true })) {
  if (!entry.isDirectory()) continue

  try {
    const {
      name,
      version,
      private: isPrivate,
    } = readJson(join(repoRoot, 'packages', entry.name, 'package.json'))

    if (!isPrivate) versions.set(name, version)
  } catch {
    continue // A directory without a package.json is not a package.
  }
}

const rewritten = []
const unknown = []

for (const entry of readdirSync(packagesRoot, { withFileTypes: true })) {
  if (!entry.isDirectory()) continue

  const manifestPath = join(packagesRoot, entry.name, 'composer.json')
  let manifest

  try {
    manifest = readJson(manifestPath)
  } catch {
    continue // A directory without a composer.json is not a package.
  }

  const pairs = manifest.extra?.webx?.npm

  if (!pairs) continue // A package with no npm half, or one that declares nothing.

  let touched = false

  for (const name of Object.keys(pairs)) {
    if (!name.startsWith('@webx-ui/')) continue

    const version = versions.get(name)

    if (version === undefined) {
      unknown.push(`${entry.name} → ${name}`)
      continue
    }

    const constraint = `^${version}`

    if (pairs[name] === constraint) continue

    pairs[name] = constraint
    touched = true
  }

  if (!touched) continue

  // Composer's own indentation, so `composer require` does not reformat the file back.
  writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 4)}\n`)
  rewritten.push(entry.name)
}

if (unknown.length > 0) {
  // A name that resolves to nothing would install as whatever npm happens to have under it.
  console.error(
    `extra.webx.npm names packages this repository does not build: ${unknown.join(', ')}`,
  )
  process.exit(1)
}

if (rewritten.length === 0) {
  console.log('npm halves are already on the published versions; nothing to rewrite.')
} else {
  console.log(`Rewrote npm halves in: ${rewritten.join(', ')}`)
}
