// Every Composer package under php/packages ships on one shared version, the way illuminate/*
// does. Changesets writes that version into php/package.json; this script pushes it into the
// constraints our packages put on each other, so the bump and the constraints land in the same
// commit and a published package can never ask for a sibling version that does not exist.
//
// Run by `pnpm version-packages`, right after `changeset version`.

import { readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const phpRoot = join(repoRoot, 'php')
const packagesRoot = join(phpRoot, 'packages')

const { version } = JSON.parse(readFileSync(join(phpRoot, 'package.json'), 'utf8'))
const constraint = `^${version}`

const rewritten = []

for (const entry of readdirSync(packagesRoot, { withFileTypes: true })) {
  if (!entry.isDirectory()) continue

  const manifestPath = join(packagesRoot, entry.name, 'composer.json')
  let raw

  try {
    raw = readFileSync(manifestPath, 'utf8')
  } catch {
    continue // A directory without a composer.json is not a package.
  }

  const manifest = JSON.parse(raw)
  let touched = false

  for (const section of ['require', 'require-dev']) {
    const dependencies = manifest[section]
    if (!dependencies) continue

    for (const name of Object.keys(dependencies)) {
      if (!name.startsWith('webx-ui/')) continue
      if (dependencies[name] === constraint) continue

      dependencies[name] = constraint
      touched = true
    }
  }

  if (!touched) continue

  // Composer's own indentation, so `composer require` does not reformat the file back.
  writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 4)}\n`)
  rewritten.push(entry.name)
}

if (rewritten.length === 0) {
  console.log(`PHP packages are on ${version}; no internal constraint needed rewriting.`)
} else {
  console.log(`Rewrote internal constraints to ${constraint} in: ${rewritten.join(', ')}`)
}

// The development root installs the packages from a path repository, where they would
// otherwise take the version of the current git branch — and a constraint like ^0.2.0 between
// two of them could then never be satisfied locally. Pinning them here keeps `composer install`
// in php/ resolving the same way Composer will once the packages are on Packagist.
const rootPath = join(phpRoot, 'composer.json')
const root = JSON.parse(readFileSync(rootPath, 'utf8'))
const pathRepository = (root.repositories ?? []).find((repository) => repository.type === 'path')

if (!pathRepository) {
  console.error('php/composer.json has no path repository to pin versions in.')
  process.exit(1)
}

const names = readdirSync(packagesRoot, { withFileTypes: true })
  .filter((entry) => entry.isDirectory())
  .map((entry) => {
    try {
      return JSON.parse(readFileSync(join(packagesRoot, entry.name, 'composer.json'), 'utf8')).name
    } catch {
      return null
    }
  })
  .filter((name) => typeof name === 'string')
  .sort()

pathRepository.options = {
  ...pathRepository.options,
  versions: Object.fromEntries(names.map((name) => [name, version])),
}

writeFileSync(rootPath, `${JSON.stringify(root, null, 4)}\n`)

console.log(`Pinned ${names.length} path packages to ${version} in php/composer.json.`)
