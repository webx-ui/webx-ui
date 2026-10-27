import { describe, expect, it } from 'vitest'
import {
  configuredDirectories,
  counts,
  fileSeconds,
  jobs,
  parseArgs,
  workerCount,
} from './phpunit-parallel.mjs'

const file = (path, seconds) => ({ path, seconds })

describe('phpunit-parallel', () => {
  it('reads the test directories of phpunit.xml.dist, not those of <source>', () => {
    const xml = `<testsuite name="x">
            <directory>packages/a/tests</directory>
            <directory>packages/b/tests</directory>
        </testsuite>
    <source><include><directory>packages/a/src</directory></include></source>`
    expect(configuredDirectories(xml)).toEqual(['packages/a/tests', 'packages/b/tests'])
  })

  it('keeps a package within its share as one job by its directory, longest first', () => {
    const run = jobs(
      {
        'packages/small/tests': [file('packages/small/tests/ATest.php', 2)],
        'packages/big/tests': [file('packages/big/tests/ATest.php', 4)],
        'packages/mid/tests': [
          file('packages/mid/tests/ATest.php', 1.5),
          file('packages/mid/tests/BTest.php', 1.5),
        ],
      },
      1,
    )
    expect(run.map((job) => job.args)).toEqual([
      ['packages/big/tests'],
      ['packages/mid/tests'],
      ['packages/small/tests'],
    ])
  })

  it('splits a long package into slices of its files, every file in exactly one', () => {
    const big = [
      file('packages/big/tests/OptimizeTest.php', 30),
      file('packages/big/tests/ATest.php', 10),
      file('packages/big/tests/BTest.php', 10),
      file('packages/big/tests/sub/CTest.php', 10),
    ]
    const run = jobs(
      {
        'packages/big/tests': big,
        'packages/small/tests': [file('packages/small/tests/ATest.php', 4)],
      },
      2,
    )
    const slices = run.filter((job) => job.name.startsWith('packages/big/tests ('))
    expect(slices.length).toBeGreaterThan(1)
    expect(slices.flatMap((job) => job.args).sort()).toEqual(big.map((f) => f.path).sort())
    // The longest file is dealt first, to a slice of its own.
    expect(slices[0].args).toEqual(['packages/big/tests/OptimizeTest.php'])
  })

  it('keeps every directory asked for, with or without --workers', () => {
    expect(parseArgs(['packages/a/tests', 'packages/b/tests'])).toEqual({
      given: ['packages/a/tests', 'packages/b/tests'],
      override: 0,
    })
    expect(parseArgs(['packages/a/tests', '--workers', '3', 'packages/b/tests'], '8')).toEqual({
      given: ['packages/a/tests', 'packages/b/tests'],
      override: 3,
    })
    expect(parseArgs([], '6')).toEqual({ given: [], override: 6 })
  })

  it('takes the cores but two, as far as memory holds, never more than the jobs', () => {
    const GB = 1024 ** 3
    expect(workerCount({ jobs: 40, cores: 12, free: 16 * GB })).toBe(10)
    expect(workerCount({ jobs: 40, cores: 12, free: 3 * GB })).toBe(4)
    expect(workerCount({ jobs: 3, cores: 12, free: 16 * GB })).toBe(3)
    expect(workerCount({ jobs: 40, cores: 2, free: 16 * GB })).toBe(1)
    expect(workerCount({ jobs: 40, cores: 12, free: 16 * GB, override: 4 })).toBe(4)
  })

  it('reads both shapes of the phpunit summary, colours and all', () => {
    expect(counts('\x1b[30;42mOK (55 tests, 524 assertions)\x1b[0m')).toEqual({
      tests: 55,
      assertions: 524,
      skipped: 0,
    })
    expect(
      counts('\x1b[30;42mTests: 5418\x1b[0m\x1b[30;42m, Assertions: 38883\x1b[0m, Skipped: 29.'),
    ).toEqual({ tests: 5418, assertions: 38883, skipped: 29 })
    expect(counts('PHP Fatal error')).toBeNull()
  })

  it('times the files of a junit log by their path under php/', () => {
    const junit = `<testsuite name="CLI Arguments" tests="3" time="0.37">
    <testsuite name="A\\ATest" file="${process.cwd()}/php/packages/a/tests/ATest.php" tests="1" assertions="18" errors="0" failures="0" skipped="0" time="0.287807">`
    expect(fileSeconds(junit)).toEqual({ 'packages/a/tests/ATest.php': 0.287807 })
  })
})
