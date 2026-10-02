// Fails when a name that must stay out of the public repository shows up in the tree, in the
// staged changes or in commit messages. The names themselves are the leak, so they are not
// here: the pattern (a JavaScript regular expression, matched case-insensitively) comes from
// the WEBX_FORBIDDEN_NAMES environment variable — a repository secret in CI, a local file for
// a hook. Without it the check says so and passes, which is what a fork gets.
//
// The report names the place and never the match: CI logs of a public repository are public.
//
//   node scripts/check-names.mjs                  every tracked file
//   node scripts/check-names.mjs --staged         what is about to be committed (pre-commit)
//   node scripts/check-names.mjs --message <file> a commit message (commit-msg)
//   node scripts/check-names.mjs --commits <a..b> the messages of a range of commits

import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

const source = process.env.WEBX_FORBIDDEN_NAMES?.trim()
if (!source) {
  console.log('WEBX_FORBIDDEN_NAMES is not set — the names check is skipped.')
  process.exit(0)
}
const pattern = new RegExp(source, 'iu')

// The lockfile's integrity hashes are base64 and spell anything sooner or later.
const skipped = new Set(['pnpm-lock.yaml', 'php/composer.lock'])

const git = (...args) => execFileSync('git', args, { encoding: 'utf8', maxBuffer: 1 << 28 })

const found = []
const scan = (where, text) => {
  text.split('\n').forEach((line, i) => {
    if (pattern.test(line)) found.push(`${where}:${i + 1}`)
  })
}
const isBinary = (buffer) => buffer.subarray(0, 8000).includes(0)

const [mode, arg] = process.argv.slice(2)

if (mode === '--message') {
  scan('commit message', readFileSync(arg, 'utf8'))
} else if (mode === '--commits') {
  for (const sha of git('rev-list', arg).split('\n').filter(Boolean)) {
    scan(`commit ${sha.slice(0, 10)}`, git('log', '-1', '--format=%B', sha))
  }
} else {
  const staged = mode === '--staged'
  const files = (
    staged
      ? git('diff', '--cached', '--name-only', '--diff-filter=ACMR', '-z')
      : git('ls-files', '-z')
  )
    .split('\0')
    .filter((file) => file && !skipped.has(file))
  for (const file of files) {
    if (pattern.test(file)) found.push(`${file} (the path)`)
    const buffer = staged
      ? execFileSync('git', ['show', `:${file}`], { maxBuffer: 1 << 28 })
      : (() => {
          try {
            return readFileSync(file)
          } catch {
            return null // a deleted file still in the index, a broken symlink
          }
        })()
    if (buffer && !isBinary(buffer)) scan(file, buffer.toString('utf8'))
  }
}

if (found.length > 0) {
  console.error(
    `A name that must not be public was found in ${found.length} place(s):\n` +
      found.map((place) => `  ${place}`).join('\n') +
      '\nReplace it with a neutral description (see docs/pitfalls/release-and-ci.md).',
  )
  process.exit(1)
}
console.log('No forbidden names.')
