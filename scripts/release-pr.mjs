// Decides whether PHP CI may take the light path: the change is the version PR alone, and it
// touches nothing but what `pnpm version-packages` writes. That PR bumps versions, changelogs
// and the constraints between our packages on top of a main the merge queue already proved
// green, and because it rewrites every composer.json, scripts/affected.mjs would otherwise send
// it through the whole suite — twice, on the PR and again in the queue. php-ci.yml reads the
// answer; the light path validates the manifests and that the versions agree, nothing more.
//
//   node scripts/release-pr.mjs <base-sha>
//
// Reads the event from the environment php-ci.yml sets (EVENT, REPOSITORY, and for a pull
// request HEAD_REF, HEAD_REPO, PR_AUTHOR, ACTOR) and prints `light=true|false` plus `reason=…`,
// appending both to $GITHUB_OUTPUT when it is set. Anything it cannot prove answers `false`:
// the full run is never wrong, only slower.

import { execFileSync } from 'node:child_process'
import { appendFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

// The branch changesets/action pushes the version PR to, and the organisation's App that
// release.yml pushes it with. The branch name alone proves nothing — anybody with write access
// can push a branch called that — but a `[bot]` login cannot be taken by a person.
export const RELEASE_BRANCH = 'changeset-release/main'
export const RELEASE_APP = 'webx-ui-split[bot]'

// What `pnpm version-packages` and changesets write: versions and constraints in the
// manifests, the changelogs, the consumed changesets and the lock files. A version PR that
// changes anything else was edited by hand, and that is code to test.
export const isReleaseFile = (file) =>
  file.startsWith('.changeset/') ||
  /(^|\/)(composer\.json|package\.json|CHANGELOG\.md|composer\.lock|pnpm-lock\.yaml)$/.test(file)

/**
 * The pull request: the release branch of this repository, opened by the App, and the run
 * started by the App too — a person pushing on top of the App's branch gets the full run.
 */
export function isReleasePullRequest({ headRef, headRepo, repository, author, actor }) {
  return (
    headRef === RELEASE_BRANCH &&
    headRepo === repository &&
    author === RELEASE_APP &&
    actor === RELEASE_APP
  )
}

/**
 * The merge queue: the group's commits are one squash per pull request, each subject ending
 * in `(#N)`. Every commit has to name a pull request and every one of those has to be the
 * version PR; a group with any other pull request in it — or a commit we cannot place — runs
 * in full, because the neighbour's code has not been tested against this base.
 */
export function pullRequestsOfGroup(subjects) {
  const numbers = subjects.map((subject) => subject.match(/\(#(\d+)\)$/)?.[1])
  return numbers.every(Boolean) ? numbers.map(Number) : null
}

/** The whole decision, from what the event says and the files the change touches. */
export function decide({ event, files, pullRequest, group }) {
  if (files.length === 0) return { light: false, reason: 'no diff against the base' }
  const foreign = files.filter((file) => !isReleaseFile(file))
  if (foreign.length > 0) {
    return { light: false, reason: `changes more than versions: ${foreign.slice(0, 5).join(', ')}` }
  }

  if (event === 'pull_request') {
    return isReleasePullRequest(pullRequest)
      ? { light: true, reason: 'the version PR, versions only' }
      : { light: false, reason: 'not the version PR opened and pushed by the App' }
  }

  if (event === 'merge_group') {
    if (!group || group.length === 0) {
      return { light: false, reason: 'cannot tell which pull requests are in the group' }
    }
    return group.every(isReleasePullRequest)
      ? { light: true, reason: 'a group of the version PR alone, versions only' }
      : { light: false, reason: 'the group holds a pull request other than the version PR' }
  }

  return { light: false, reason: `a ${event} run is always full` }
}

function main() {
  const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
  const git = (...args) => execFileSync('git', args, { cwd: repoRoot, encoding: 'utf8' })
  const lines = (text) => text.split('\n').filter(Boolean)
  const base = process.argv[2]?.trim()
  const env = process.env
  const repository = env.REPOSITORY ?? ''

  const input = { event: env.EVENT ?? '', files: [] }
  if (base) {
    input.files = lines(git('diff', '--name-only', '--no-renames', base, 'HEAD'))
  }

  if (input.event === 'pull_request') {
    input.pullRequest = {
      headRef: env.HEAD_REF,
      headRepo: env.HEAD_REPO,
      repository,
      author: env.PR_AUTHOR,
      actor: env.ACTOR,
    }
  }

  if (input.event === 'merge_group' && base) {
    // A history that does not reach the base would hide the group's first commits.
    let reaches = true
    try {
      git('merge-base', '--is-ancestor', base, 'HEAD')
    } catch {
      reaches = false
    }
    const numbers = reaches
      ? pullRequestsOfGroup(lines(git('log', '--format=%s', `${base}..HEAD`)))
      : null
    // The queue enqueues a pull request, not its pushes, so the App has to have opened it and
    // its branch has to be the release branch; who pressed «merge when ready» does not matter.
    // An API that does not answer leaves the group unknown, and an unknown group runs in full.
    try {
      input.group = numbers?.map((number) => {
        const pr = JSON.parse(
          execFileSync('gh', ['api', `repos/${repository}/pulls/${number}`], { encoding: 'utf8' }),
        )
        return {
          headRef: pr.head.ref,
          headRepo: pr.head.repo?.full_name,
          repository,
          author: pr.user.login,
          actor: pr.user.login,
        }
      })
    } catch {
      input.group = null
    }
  }

  const { light, reason } = decide(input)
  const output = [`light=${light}`, `reason=${reason}`]
  console.log(output.join('\n'))
  if (env.GITHUB_OUTPUT) appendFileSync(env.GITHUB_OUTPUT, `${output.join('\n')}\n`)
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) main()
