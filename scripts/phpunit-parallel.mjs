// PHPUnit over the Composer packages in parallel, a few processes at a time, the longest job
// first. The same tests as `vendor/bin/phpunit <dirs>` under the same phpunit.xml.dist — what
// changes is only that they no longer queue behind one another.
//
//   node scripts/phpunit-parallel.mjs [--workers N] [packages/<name>/tests …]
//
// No directories means every one phpunit.xml.dist lists. A package is one job, its directory
// handed to phpunit as is; a package longer than its share of the run (module-media,
// module-catalog: over two minutes each) is split into a few jobs by its test files, balanced
// by the seconds each file took last time (.cache/gate/phpunit-seconds.json, from phpunit's
// junit log). Workers default to the cores but two, as far as free memory holds them
// (GATE_PHP_WORKERS overrides): on devbox's 12 threads 10 workers ran the suite in 2:11, 8 in
// 2:30, 6 in 2:50, one process in 8:11 — the threads are mostly small cores, past 10 little is left.
//
// Two phpunit processes in one checkout break each other through the Testbench skeleton
// (vendor/orchestra/testbench-core/laravel): compiled views, storage, bootstrap caches, files
// tests publish into it. Each worker gets a copy of its own under the system temp directory,
// handed over by APP_BASE_PATH, and a cache directory of its own; both go when the run ends.
// Paths tests make with sys_get_temp_dir() are random already.

import { spawn } from 'node:child_process'
import {
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'node:fs'
import { availableParallelism, freemem, tmpdir } from 'node:os'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const php = join(repoRoot, 'php')
const skeleton = join(php, 'vendor/orchestra/testbench-core/laravel')
const timings = join(repoRoot, '.cache/gate/phpunit-seconds.json')

// The whole suite in one process peaks under 0.4 GB; a package alone takes less. Twice that
// leaves room for whatever else the machine runs.
const GB = 1024 ** 3
const perWorker = 0.75 * GB

/** The test directories phpunit.xml.dist lists, in its order. */
export function configuredDirectories(xml) {
  return [...xml.matchAll(/<directory>([^<]*\/tests)<\/directory>/g)].map((match) => match[1])
}

/** The directories asked for and the workers forced, by --workers N or the environment. */
export function parseArgs(argv, fromEnv) {
  const flag = argv.indexOf('--workers')
  const value = flag >= 0 ? flag + 1 : -1
  return {
    given: argv.filter((arg, index) => !arg.startsWith('--') && index !== value),
    override: Number(flag >= 0 ? argv[value] : fromEnv) || 0,
  }
}

/** How many workers the machine holds: cores and free memory, never more than there is work. */
export function workerCount({ jobs, cores, free, override }) {
  if (override) return Math.max(1, Math.min(jobs, override))
  return Math.max(1, Math.min(jobs, Math.max(1, cores - 2), Math.floor(free / perWorker)))
}

/**
 * The jobs of a run, longest first. `files` maps a directory to its test files with their
 * expected seconds. A directory within its share stays one job by its own path; a longer one
 * becomes as many jobs as it has shares, its files dealt longest first to the lightest job.
 */
export function jobs(files, workers) {
  const weight = (list) => list.reduce((sum, file) => sum + file.seconds, 0)
  const total = Object.values(files).reduce((sum, list) => sum + weight(list), 0)
  // Twice as many shares as workers: the last jobs to start are short, and the run ends level.
  // Never under 20 s: a process boots its phpunit and Testbench for a second or two.
  const share = Math.max(total / (workers * 2), 20)
  const out = []
  for (const [directory, list] of Object.entries(files)) {
    const seconds = weight(list)
    const parts = Math.min(list.length, Math.ceil(seconds / share))
    if (parts <= 1) {
      out.push({ name: directory, args: [directory], seconds })
      continue
    }
    const chunks = Array.from({ length: parts }, () => ({ files: [], seconds: 0 }))
    for (const file of [...list].sort((a, b) => b.seconds - a.seconds)) {
      const lightest = chunks.reduce((min, chunk) => (chunk.seconds < min.seconds ? chunk : min))
      lightest.files.push(file.path)
      lightest.seconds += file.seconds
    }
    chunks.forEach((chunk, index) =>
      out.push({
        name: `${directory} (${index + 1}/${parts})`,
        args: chunk.files.sort(),
        seconds: chunk.seconds,
      }),
    )
  }
  return out.sort((a, b) => b.seconds - a.seconds)
}

/** Tests, assertions and skipped of one phpunit summary, whichever of its two shapes it took. */
export function counts(text) {
  // phpunit.xml.dist asks for colours, and phpunit paints its summary even into a pipe.
  // eslint-disable-next-line no-control-regex -- the escape of a colour is what is stripped
  const output = text.replace(/\x1b\[[0-9;]*m/g, '')
  const ok = /OK \((\d+) tests?, (\d+) assertions?\)/.exec(output)
  if (ok) return { tests: Number(ok[1]), assertions: Number(ok[2]), skipped: 0 }
  const line = /^Tests: (\d+), Assertions: (\d+)(.*)$/m.exec(output)
  if (!line) return null
  const skipped = /Skipped: (\d+)/.exec(line[3])
  return {
    tests: Number(line[1]),
    assertions: Number(line[2]),
    skipped: skipped ? Number(skipped[1]) : 0,
  }
}

/** Seconds per test file of a junit log, by the file's path relative to php/. */
export function fileSeconds(junit) {
  return Object.fromEntries(
    [...junit.matchAll(/<testsuite name="[^"]*" file="([^"]+)"[^>]* time="([\d.]+)"/g)].map(
      (match) => [relative(php, match[1]), Number(match[2])],
    ),
  )
}

function readJson(file) {
  try {
    return JSON.parse(readFileSync(file, 'utf8'))
  } catch {
    return {}
  }
}

// What phpunit would find in a directory: files ending in Test.php, at any depth.
function testFiles(directory) {
  return readdirSync(join(php, directory), { recursive: true })
    .filter((file) => String(file).endsWith('Test.php'))
    .map((file) => join(directory, String(file)))
}

// A copy of the skeleton without what runs leave in it: the log (hundreds of MB), compiled views,
// and the traces docs/pitfalls/packages-and-demo-sites.md warns of — a .env an interrupted run
// of themes left, services.php or testbench.yaml from a `vendor/bin/testbench` call. Testbench
// writes the bootstrap cache again on the first boot.
const leftovers = /^(\.env|bootstrap\/cache\/.+|storage\/logs\/.+|storage\/framework\/views\/.+)$/

function prepare(workDir) {
  const root = join(workDir, 'laravel')
  cpSync(skeleton, root, {
    recursive: true,
    verbatimSymlinks: true,
    filter: (source) => !leftovers.test(relative(skeleton, source).split('\\').join('/')),
  })
  for (const sub of ['bootstrap/cache', 'storage/logs', 'storage/framework/views'])
    mkdirSync(join(root, sub), { recursive: true })
  return root
}

function run(job, worker) {
  return new Promise((resolve) => {
    const at = Date.now()
    const junit = join(worker.dir, 'junit.xml')
    rmSync(junit, { force: true })
    const child = spawn(
      'php',
      [
        '-d',
        'memory_limit=-1',
        'vendor/bin/phpunit',
        '--cache-directory',
        join(worker.dir, 'phpunit-cache'),
        '--log-junit',
        junit,
        ...job.args,
      ],
      { cwd: php, env: { ...process.env, APP_BASE_PATH: worker.base } },
    )
    let output = ''
    child.stdout.on('data', (chunk) => (output += chunk))
    child.stderr.on('data', (chunk) => (output += chunk))
    child.on('close', (code) => {
      let files = {}
      try {
        files = fileSeconds(readFileSync(junit, 'utf8'))
      } catch {
        // No log when phpunit died before writing it: the next run splits by size again.
      }
      resolve({ job, code, output, files, seconds: (Date.now() - at) / 1000 })
    })
  })
}

async function main(argv) {
  const { given, override } = parseArgs(argv, process.env.GATE_PHP_WORKERS)
  const directories = given.length
    ? given
    : configuredDirectories(readFileSync(join(php, 'phpunit.xml.dist'), 'utf8'))
  const missing = directories.filter((directory) => !existsSync(join(php, directory)))
  if (missing.length) {
    console.error(`phpunit-parallel: no such directory under php/: ${missing.join(', ')}`)
    return 2
  }

  // A file never timed counts by its size: about a second per 10 KB of test is the suite's mean.
  const seconds = readJson(timings)
  const files = Object.fromEntries(
    directories.map((directory) => [
      directory,
      testFiles(directory).map((path) => ({
        path,
        seconds: seconds[path] ?? statSync(join(php, path)).size / 10_000,
      })),
    ]),
  )
  const count = workerCount({
    jobs: directories.length,
    cores: availableParallelism(),
    free: freemem(),
    override,
  })
  const queue = jobs(files, count)
  console.log(
    `phpunit-parallel: ${directories.length} directories, ${queue.length} jobs, ${count} workers`,
  )

  const scratch = join(tmpdir(), `webx-phpunit-${process.pid}`)
  const cleanup = () => rmSync(scratch, { recursive: true, force: true })
  process.on('SIGINT', () => (cleanup(), process.exit(130)))
  const workers = Array.from({ length: count }, (_, index) => {
    const dir = join(scratch, `w${index + 1}`)
    return { dir, base: prepare(dir) }
  })

  const results = []
  const next = async (worker) => {
    while (queue.length) {
      const result = await run(queue.shift(), worker)
      const found = counts(result.output)
      const ok = result.code === 0
      console.log(
        `  ${ok ? 'ok    ' : 'FAILED'} ${result.job.name}  ${found ? `${found.tests} tests` : ''}  ${result.seconds.toFixed(0)}s`,
      )
      if (!ok) console.log(result.output.replace(/^/gm, '    │ '))
      results.push({ ...result, found })
    }
  }
  try {
    await Promise.all(workers.map(next))
  } finally {
    cleanup()
  }

  // Keep what this run learnt for the split of the next one; a failed run times as well.
  mkdirSync(dirname(timings), { recursive: true })
  writeFileSync(
    timings,
    `${JSON.stringify({ ...seconds, ...Object.assign({}, ...results.map((r) => r.files)) }, null, 2)}\n`,
  )

  const failed = results.filter((result) => result.code !== 0)
  const total = results.reduce(
    (sum, { found }) => ({
      tests: sum.tests + (found?.tests ?? 0),
      assertions: sum.assertions + (found?.assertions ?? 0),
      skipped: sum.skipped + (found?.skipped ?? 0),
    }),
    { tests: 0, assertions: 0, skipped: 0 },
  )
  console.log(
    `phpunit-parallel: ${total.tests} tests, ${total.assertions} assertions, ${total.skipped} skipped` +
      (failed.length ? ` — FAILED in ${failed.map((r) => r.job.name).join(', ')}` : ' — OK'),
  )
  return failed.length ? 1 : 0
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href)
  main(process.argv.slice(2)).then((code) => process.exit(code))
