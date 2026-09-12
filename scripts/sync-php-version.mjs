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
