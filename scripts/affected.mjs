// Decides what CI has to check for a change: everything, only what the change can break, or
// nothing beyond lint. Both workflows run it — ci.yml for the npm half, php-ci.yml for the
// Composer half — so the rules for "what counts" live in one place.
//
//   node scripts/affected.mjs <base-sha>    compare HEAD with <base-sha>
//   node scripts/affected.mjs               no base: a push to main or a nightly run, check all
//   node scripts/affected.mjs --php <sha>   the Composer half only, for a job without pnpm
//
// Prints `key=value` lines and appends them to $GITHUB_OUTPUT when it is set:
//
//   node          all | some | none
//   node_filters  `--filter <name>` for every npm project the change reaches (pnpm's
//                 `...[<base>]`: the changed projects and everything that depends on them)
//   node_tests    the packages/* directories among them, as Vitest path filters
//   docs          true when the documentation site has to be built
//   php           all | some | none
//   php_tests     test directories of the Composer packages the change reaches, relative to php/
//   manticore     true when the catalogue's Manticore engine has to be tested on a real server
//
// "Reaches" follows `require` in the packages' composer.json and the workspace dependencies
// pnpm knows about: a change in module-catalog tests its satellites, a change in module-admin
// tests nearly everything, a change in module-blog tests module-blog alone.

import { execFileSync } from 'node:child_process'
import { appendFileSync, readdirSync, readFileSync } from 'node:fs'
import { dirname, join, relative, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const phpPackagesRoot = join(repoRoot, 'php', 'packages')
const phpOnly = process.argv.includes('--php')
const base = process.argv
  .slice(2)
  .find((arg) => !arg.startsWith('--'))
  ?.trim()

const git = (...args) => execFileSync('git', args, { cwd: repoRoot, encoding: 'utf8' })

// Text only: nothing here is compiled, tested or rendered. The guides under apps/docs are
// pages of the documentation site and do get built.
const isDocumentation = (file) =>
  file.startsWith('docs/') ||
  file.startsWith('.changeset/') ||
  (file.endsWith('.md') && !file.startsWith('apps/docs/'))

// Anything every test depends on. The ui core and the tokens are imported by every npm
// package; the rest is configuration of the runs themselves.
const touchesAllNode = (file) =>
  file.startsWith('packages/core/') ||
  file.startsWith('packages/tokens/') ||
  file === 'package.json' ||
  file === 'pnpm-lock.yaml' ||
  file === 'pnpm-workspace.yaml' ||
  /^(vitest|tsconfig|eslint)\b/.test(file)

const touchesAllPhp = (file) =>
  file.startsWith('php/') && !file.startsWith('php/packages/') && file !== 'php/package.json'

function decide() {
  if (!base) return { node: 'all', php: 'all' }

  const changed = git('diff', '--name-only', '--no-renames', base, 'HEAD')
    .split('\n')
    .filter(Boolean)
    .filter((file) => !isDocumentation(file))

  // The workflows and this script decide what the others run, so a change to them proves
  // itself on everything. So does any file outside the places sorted below — a new tool at the
  // root is safer checked in full than skipped.
  const sorted = (file) =>
    file.startsWith('packages/') ||
    file.startsWith('apps/') ||
    file.startsWith('php/') ||
    touchesAllNode(file)
  if (changed.some((file) => !sorted(file))) return { node: 'all', php: 'all' }

  const npmFiles = changed.filter((file) => !file.startsWith('php/'))
  const phpFiles = changed.filter((file) => file.startsWith('php/packages/'))

  return {
    node: npmFiles.length === 0 ? 'none' : npmFiles.some(touchesAllNode) ? 'all' : 'some',
    php: changed.some(touchesAllPhp) ? 'all' : phpFiles.length === 0 ? 'none' : 'some',
    phpChanged: new Set(phpFiles.map((file) => file.split('/')[2])),
  }
}

// pnpm's own `...[<base>]`, so the npm half follows exactly the graph the workspace installs.
// The root and the private @webx-ui/php are projects too, but neither has anything to test:
// the root's `typecheck` would recurse into every package and undo the point.
function npmProjects() {
  const json = execFileSync(
    'pnpm',
    ['ls', '-r', '--depth', '-1', '--json', '--filter', `...[${base}]`],
    { cwd: repoRoot, encoding: 'utf8', shell: process.platform === 'win32' },
  )
  return JSON.parse(json)
    .filter((project) => project.name !== 'webx-ui-monorepo' && project.name !== '@webx-ui/php')
    .map((project) => ({
      name: project.name,
      dir: relative(repoRoot, project.path).split(sep).join('/'),
    }))
}

// Everything that requires one of `changed`, directly or through another package.
function phpDependents(changed) {
  const requires = new Map(
    readdirSync(phpPackagesRoot, { withFileTypes: true })
      .filter((entry) => entry.isDirectory())
      .map((entry) => {
        const manifest = JSON.parse(
          readFileSync(join(phpPackagesRoot, entry.name, 'composer.json'), 'utf8'),
        )
        const own = Object.keys(manifest.require ?? {})
          .filter((name) => name.startsWith('webx-ui/'))
          .map((name) => name.slice('webx-ui/'.length))
        return [entry.name, own]
      }),
  )

  const reached = new Set([...changed].filter((name) => requires.has(name)))
  let grew = true
  while (grew) {
    grew = false
    for (const [name, own] of requires) {
      if (!reached.has(name) && own.some((dependency) => reached.has(dependency))) {
        reached.add(name)
        grew = true
      }
    }
  }
  return [...reached].sort()
}

const decision = decide()
const output = { node: decision.node, node_filters: '', node_tests: '', docs: 'false' }

if (decision.node === 'all') output.docs = 'true'
if (decision.node === 'some' && !phpOnly) {
  const projects = npmProjects()
  if (projects.length === 0) output.node = 'none'
  output.node_filters = projects.map((project) => `--filter ${project.name}`).join(' ')
  output.node_tests = projects
    .filter((project) => project.dir.startsWith('packages/'))
    .map((project) => `${project.dir}/`)
    .join(' ')
  output.docs = String(projects.some((project) => project.name === '@webx-ui/docs'))
}

output.php = decision.php
output.php_tests = ''
output.manticore = String(decision.php === 'all')
if (decision.php === 'some') {
  const reached = phpDependents(decision.phpChanged)
  if (reached.length === 0) output.php = 'none'
  output.php_tests = reached.map((name) => `packages/${name}/tests`).join(' ')
  output.manticore = String(reached.includes('module-catalog-manticore'))
}

const lines = Object.entries(output).map(([key, value]) => `${key}=${value}`)
console.log(lines.join('\n'))
if (process.env.GITHUB_OUTPUT) appendFileSync(process.env.GITHUB_OUTPUT, `${lines.join('\n')}\n`)
