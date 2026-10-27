// The gate before a push, run locally: what CI would check for the same change, and nothing
// more. scripts/affected.mjs decides what the change reaches — the very answer ci.yml and
// php-ci.yml read — and this script runs the steps those workflows run for that answer.
//
//   pnpm gate:affected <base>    a stage: `git rev-parse HEAD` before its first edit is the base
//   pnpm gate:affected --full    the day's release: everything, as CLAUDE.md §5 lists it
//
// The JS half and the PHP half run one after the other: side by side they run out of memory.
// The first red step stops the run; a summary of every step and its time closes it either way.
//
// Locally there is one PHP (CI also tests on 8.5) and no sharding. What CI caches by file
// content — eslint, prettier — is decided here by the files the change touches, which is the
// same verdict: neither linter reads types across files.

import { execFileSync, spawnSync } from 'node:child_process'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')

const lintable = /\.(c|m)?(j|t)sx?$|\.vue$/

/** `key=value` lines of affected.mjs into an object. */
export function parseAffected(text) {
  return Object.fromEntries(
    text
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line.includes('='))
      .map((line) => [line.slice(0, line.indexOf('=')), line.slice(line.indexOf('=') + 1)]),
  )
}

const words = (list) => list.split(/\s+/).filter(Boolean)

/**
 * The steps CI runs for an answer of affected.mjs, in order. A half the change does not reach
 * is a step with `skipped` and the reason, so that the summary says it was not forgotten.
 *
 * @param affected  the parsed answer of affected.mjs
 * @param options   base — null for the full gate; changed — files changed since the base, deleted
 *                  ones left out; typecheckFilters — `--filter` flags of the projects to typecheck
 *                  (ci.yml picks them from `pnpm ls`, see typecheckFilters())
 */
export function plan(affected, { base, changed, typecheckFilters = [] }) {
  const steps = []
  const node = affected.node
  const php = affected.php
  const step = (name, cmd, args, extra = {}) => steps.push({ name, cmd, args, cwd: '.', ...extra })
  const skip = (name, why) => steps.push({ name, skipped: why })

  // ci.yml `names`: the tree always, the commit messages of what the change brings in.
  step('names: tree', 'node', ['scripts/check-names.mjs'])
  if (base)
    step('names: commits', 'node', ['scripts/check-names.mjs', '--commits', `${base}..HEAD`])

  // ci.yml `lint`: runs on every change, whatever it reaches.
  if (node === 'all') {
    step('tokens: generated up to date', 'pnpm', ['--filter', '@webx-ui/tokens', 'generate'])
    step('tokens: no diff', 'git', ['diff', '--exit-code', '--', 'packages/tokens/src/generated'])
  }
  if (!base || node === 'all') {
    step('eslint', 'pnpm', ['lint', ...lintCache('eslint')])
  } else {
    const files = changed.filter((file) => lintable.test(file))
    if (files.length > 0)
      step('eslint: changed files', 'pnpm', ['exec', 'eslint', '--no-warn-ignored', ...files])
    else skip('eslint', 'no changed script files')
  }
  if (!base) {
    step('prettier', 'pnpm', ['format:check', ...lintCache('prettier')])
  } else if (changed.length > 0) {
    step('prettier: changed files', 'pnpm', [
      'exec',
      'prettier',
      '--check',
      '--ignore-unknown',
      ...changed,
    ])
  } else {
    skip('prettier', 'no changed files')
  }

  if (node === 'none') {
    skip('js: build, typecheck, test', 'not affected')
  } else {
    // ci.yml `typecheck` builds with declarations; `test` and `docs` read the same dist.
    step('js: build', 'pnpm', ['build'])
    step('js: typecheck', 'pnpm', [...typecheckFilters, 'typecheck'])
    if (node === 'all') step('js: test', 'pnpm', ['test'])
    else if (affected.node_tests) step('js: test', 'pnpm', ['test', ...words(affected.node_tests)])
    else skip('js: test', 'no package among the affected projects')
  }
  if (affected.docs === 'true') step('docs: build', 'pnpm', ['docs:build'])
  else skip('docs: build', 'not affected')

  if (php === 'none') {
    skip('php: validate, lint, analyse, test', 'not affected')
  } else {
    // php-ci.yml on 8.4, without its timeouts: phpunit straight, not through composer. The lock
    // is ignored by git and CI resolves a fresh one every run, so a local lock left behind by a
    // release is a note before the run (see main), not a red step CI could never show.
    const inPhp = { cwd: 'php' }
    step(
      'php: validate root manifest',
      'composer',
      ['validate', '--no-check-all', '--no-check-publish', '--no-check-lock'],
      inPhp,
    )
    step('php: validate package manifests', 'sh', ['-c', VALIDATE_PACKAGES], inPhp)
    step(
      'php: lint',
      'vendor/bin/pint',
      ['--test', '--parallel', '--cache-file=.pint.cache'],
      inPhp,
    )
    step(
      'php: analyse',
      'php',
      ['-d', 'memory_limit=-1', 'vendor/bin/phpstan', 'analyse', '--no-progress'],
      inPhp,
    )
    // The same tests as `vendor/bin/phpunit <php_tests>`, a package or a slice of one per
    // process and several at a time: 8 minutes of the full suite become about 2.
    step('php: test', 'node', ['scripts/phpunit-parallel.mjs', ...words(affected.php_tests ?? '')])
  }

  // ci.yml `manticore`: a real server, and a skipped test is a failure, not a pass. Locally
  // it is devbox's shared server; the tests keep to tables under a prefix of their own.
  if (affected.manticore === 'true') {
    step(
      'php: Manticore',
      'php',
      [
        '-d',
        'memory_limit=-1',
        'vendor/bin/phpunit',
        '--fail-on-skipped',
        'packages/module-catalog-manticore/tests',
      ],
      {
        cwd: 'php',
        env: {
          MANTICORE_TEST_HOST:
            process.env.MANTICORE_TEST_HOST || process.env.MANTICORE_HOST || 'manticore',
          MANTICORE_TEST_PORT:
            process.env.MANTICORE_TEST_PORT || process.env.MANTICORE_PORT || '9308',
        },
      },
    )
  } else {
    skip('php: Manticore', 'not affected')
  }

  return steps
}

const VALIDATE_PACKAGES =
  'for manifest in packages/*/composer.json site/composer.json; do ' +
  '( cd "$(dirname "$manifest")" && composer validate --strict --no-check-lock --quiet ) || exit 1; done'

const lintCache = (tool) => [
  '--cache',
  '--cache-strategy',
  'content',
  '--cache-location',
  `.cache/lint/${tool}`,
]

// ci.yml's typecheck job: the @webx-ui projects among those affected, the private PHP one aside.
// For `all` it is every such project — not the root, whose typecheck recurses into them all again.
function typecheckFilters(affected) {
  const json = execFileSync(
    'pnpm',
    ['ls', '-r', '--depth', '-1', '--json', ...words(affected.node_filters ?? '')],
    {
      cwd: repoRoot,
      encoding: 'utf8',
    },
  )
  return JSON.parse(json)
    .filter((project) => project.name.startsWith('@webx-ui/') && project.name !== '@webx-ui/php')
    .flatMap((project) => ['--filter', project.name])
}

const seconds = (ms) => {
  const total = Math.round(ms / 1000)
  return total >= 60
    ? `${Math.floor(total / 60)}m${String(total % 60).padStart(2, '0')}s`
    : `${total}s`
}

function main(argv) {
  const full = argv.includes('--full')
  const base = argv.find((arg) => !arg.startsWith('--'))?.trim() || null

  if (!full && !base) {
    console.error(
      'gate:affected needs a base — the commit the stage started from:\n' +
        '  BASE=$(git rev-parse HEAD)   at the start of the stage, before the first edit\n' +
        '  pnpm gate:affected $BASE     before the report\n' +
        'The release of the day runs everything: pnpm gate:affected --full',
    )
    return 2
  }
  if (full && base) {
    console.error('gate:affected: --full checks everything and takes no base.')
    return 2
  }

  const git = (...args) => execFileSync('git', args, { cwd: repoRoot, encoding: 'utf8' })
  if (base) {
    try {
      git('rev-parse', '--verify', '--quiet', `${base}^{commit}`)
    } catch {
      console.error(`gate:affected: ${base} is not a commit here.`)
      return 2
    }
  }

  const answer = execFileSync('node', ['scripts/affected.mjs', ...(base ? [base] : [])], {
    cwd: repoRoot,
    encoding: 'utf8',
  })
  const affected = parseAffected(answer)
  console.log(`affected.mjs ${base ?? '(no base: everything)'}\n${answer.trim()}\n`)

  if (git('status', '--porcelain').trim() !== '') {
    console.log(
      'note: uncommitted changes — affected.mjs compares commits, the steps run on the tree.\n',
    )
  }

  if (affected.php !== 'none') {
    const lock = spawnSync('composer', ['validate', '--no-check-all', '--no-check-publish'], {
      cwd: join(repoRoot, 'php'),
      encoding: 'utf8',
    })
    if (/lock file is not up to date/i.test(`${lock.stdout}${lock.stderr}`)) {
      console.log(
        'note: php/composer.lock is behind composer.json — CI installs fresh, so the tests here may\n' +
          '      run other versions. `composer update` in php/ brings vendor to what CI gets.\n',
      )
    }
  }

  const changed = base
    ? git('diff', '--name-only', '--no-renames', '--diff-filter=d', base, 'HEAD')
        .split('\n')
        .filter(Boolean)
    : []
  const steps = plan(affected, {
    base,
    changed,
    typecheckFilters: affected.node === 'none' ? [] : typecheckFilters(affected),
  })

  const results = []
  let failed = false
  const started = Date.now()
  for (const step of steps) {
    if (step.skipped || failed) {
      results.push({
        name: step.name,
        status: step.skipped ? `skipped: ${step.skipped}` : 'not run',
        ms: 0,
      })
      continue
    }
    const shown = [step.cmd, ...step.args].join(' ')
    console.log(
      `\n▶ ${step.name}\n  ${step.cwd === '.' ? '' : `(${step.cwd}) `}${shown.length > 300 ? `${shown.slice(0, 300)} …` : shown}`,
    )
    const at = Date.now()
    const run = spawnSync(step.cmd, step.args, {
      cwd: join(repoRoot, step.cwd),
      stdio: 'inherit',
      env: { ...process.env, ...step.env },
    })
    const ok = run.status === 0
    results.push({
      name: step.name,
      status: ok ? 'ok' : `FAILED (exit ${run.status ?? run.error?.code})`,
      ms: Date.now() - at,
    })
    failed = !ok
  }

  const width = Math.max(...results.map((result) => result.name.length))
  console.log(`\ngate:affected ${base ?? '--full'} — summary`)
  for (const result of results) {
    console.log(
      `  ${result.name.padEnd(width)}  ${result.ms ? seconds(result.ms).padStart(6) : '     -'}  ${result.status}`,
    )
  }
  console.log(
    `  ${'total'.padEnd(width)}  ${seconds(Date.now() - started).padStart(6)}  ${failed ? 'RED' : 'green'}`,
  )
  return failed ? 1 : 0
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href)
  process.exit(main(process.argv.slice(2)))
