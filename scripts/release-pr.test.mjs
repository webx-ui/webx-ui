// @vitest-environment node
import { describe, expect, it } from 'vitest'
import {
  RELEASE_APP,
  RELEASE_BRANCH,
  decide,
  isReleaseFile,
  pullRequestsOfGroup,
} from './release-pr.mjs'

const repository = 'webx-ui/webx-ui'
const versionPr = {
  headRef: RELEASE_BRANCH,
  headRepo: repository,
  repository,
  author: RELEASE_APP,
  actor: RELEASE_APP,
}

// The shape of a real version PR: one shared PHP version, npm bumps, consumed changesets.
const versionFiles = [
  '.changeset/some-change.md',
  'php/CHANGELOG.md',
  'php/composer.json',
  'php/package.json',
  'php/packages/module-blog/composer.json',
  'php/site/composer.json',
  'packages/core/CHANGELOG.md',
  'packages/core/package.json',
  'pnpm-lock.yaml',
]

describe('release-pr', () => {
  it('knows what version-packages writes', () => {
    expect(versionFiles.every(isReleaseFile)).toBe(true)
    expect(isReleaseFile('php/packages/module-blog/src/Article.php')).toBe(false)
    expect(isReleaseFile('php/packages/module-blog/composer.json.bak')).toBe(false)
    expect(isReleaseFile('.github/workflows/php-ci.yml')).toBe(false)
    expect(isReleaseFile('scripts/release-pr.mjs')).toBe(false)
  })

  it('lets the version PR through light', () => {
    expect(
      decide({ event: 'pull_request', files: versionFiles, pullRequest: versionPr }).light,
    ).toBe(true)
  })

  it('runs in full when the version PR carries code', () => {
    const files = [...versionFiles, 'php/packages/module-blog/src/Article.php']
    expect(decide({ event: 'pull_request', files, pullRequest: versionPr }).light).toBe(false)
  })

  it('does not trust the branch name alone', () => {
    const files = versionFiles
    for (const pullRequest of [
      { ...versionPr, author: 'somebody' },
      { ...versionPr, actor: 'somebody' },
      { ...versionPr, headRepo: 'somebody/webx-ui' },
      { ...versionPr, headRef: 'hot/2026-10-20' },
    ]) {
      expect(decide({ event: 'pull_request', files, pullRequest }).light).toBe(false)
    }
  })

  it('takes a queue group light only when it is the version PR alone', () => {
    const files = versionFiles
    expect(decide({ event: 'merge_group', files, group: [versionPr] }).light).toBe(true)
    expect(
      decide({ event: 'merge_group', files, group: [versionPr, { ...versionPr, author: 'x' }] })
        .light,
    ).toBe(false)
    expect(decide({ event: 'merge_group', files, group: null }).light).toBe(false)
    expect(decide({ event: 'merge_group', files, group: [] }).light).toBe(false)
  })

  it('reads the pull requests of a group from its squash subjects', () => {
    expect(pullRequestsOfGroup(['chore: version packages (#501)'])).toEqual([501])
    expect(
      pullRequestsOfGroup(['Hot branch (2) (#500)', 'chore: version packages (#501)']),
    ).toEqual([500, 501])
    expect(pullRequestsOfGroup(['chore: version packages (#501)', 'a commit of its own'])).toBe(
      null,
    )
  })

  it('never goes light outside a pull request or the queue, or without a diff', () => {
    expect(decide({ event: 'push', files: versionFiles }).light).toBe(false)
    expect(decide({ event: 'schedule', files: versionFiles }).light).toBe(false)
    expect(decide({ event: 'pull_request', files: [], pullRequest: versionPr }).light).toBe(false)
  })
})
