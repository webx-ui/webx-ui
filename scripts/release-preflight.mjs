// What a release needs from a person, checked while there is still time to do it. Trusted
// Publishing cannot create an npm name, php-split cannot create a mirror and a Packagist webhook
// only updates what was submitted; each of those used to show up as a failure at the end of the
// release. ci.yml runs this on the two pull requests of a release:
//
//   node scripts/release-preflight.mjs             the version PR: npm, mirrors and Packagist
//   node scripts/release-preflight.mjs --mirrors   the day's PR, hot/* or release/*: mirrors alone
//
// The day's PR stops at the mirrors because that is all its merge needs: the push to main fills
// them, Packagist can only take a mirror that has been filled, and the first npm version has to
// come from the version PR's branch, where the final ranges are.
//
// npm covers every public package, not only those whose version grows: `changeset publish`
// publishes whatever the registry does not have, bumped or not.
//
// Whether a trusted publisher is configured cannot be read without a login. What is public is
// provenance: a version published from CI carries an attestation. No such version proves nothing
// is wrong or right — a correct new package looks the same until its first CI release — so that
// is a warning and never fails the check. It is a warning only for a package going out now, one
// whose checkout version npm does not have yet: nothing can go wrong for the others in this
// release, so they get a line of information.
//
// The commands for a first publish need a directory for a temporary worktree. Whose machine it is
// cannot be known from CI, so the text says <worktree>; --worktree=<path> writes the path in.
//
// Reads only, with no write access anywhere; GH_TOKEN or GITHUB_TOKEN only lifts GitHub's rate
// limit. Exits 1 when something is missing or could not be checked.
//
// Run by hand with a token: anonymous GitHub allows 60 requests an hour and one run spends about
// one per mirror, so a second run in the hour comes back unchecked:
//
//   GH_TOKEN=$(gh auth token) node scripts/release-preflight.mjs

import { appendFileSync, existsSync, readFileSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const OWNER = 'webx-ui'
const WORKTREE_PLACEHOLDER = '<worktree>'

export const npmUrl = (name) => `https://registry.npmjs.org/${name.replace('/', '%2f')}`
export const mirrorUrl = (repository) => `https://api.github.com/repos/${OWNER}/${repository}`
export const packagistUrl = (repository) =>
  `https://repo.packagist.org/p2/${OWNER}/${repository}.json`

/** What the checkout publishes: the same listing `changeset publish` and php-split.yml read. */
export function readPackages(root) {
  const read = (file) => JSON.parse(readFileSync(join(root, file), 'utf8'))
  const has = (file) => existsSync(join(root, file))

  const npm = readdirSync(join(root, 'packages'))
    .map((name) => `packages/${name}`)
    .filter((dir) => has(`${dir}/package.json`))
    .map((dir) => ({ dir, ...read(`${dir}/package.json`) }))
    .filter((manifest) => !manifest.private)
    .map(({ dir, name, version }) => ({ dir, name, version }))

  // `php/site` is the skeleton `composer create-project` reads: mirrored like a package.
  const composer = [
    ...readdirSync(join(root, 'php/packages')).map((name) => `php/packages/${name}`),
    'php/site',
  ]
    .filter((dir) => has(`${dir}/composer.json`))
    .map((dir) => read(`${dir}/composer.json`).name.replace(`${OWNER}/`, ''))

  return { npm, composer }
}

/**
 * One address, three answers. Only a 404 means missing: a registry that is down must not read as
 * "publish it by hand", so anything else is retried and then reported as not checked.
 */
async function probe(url, { fetcher, headers = {}, attempts = 3, pause = 1000 }) {
  let failure = {}

  for (let attempt = 1; attempt <= attempts; attempt++) {
    try {
      const response = await fetcher(url, { headers, signal: AbortSignal.timeout(15000) })
      if (response.ok) return { state: 'found', response }
      if (response.status === 404) return { state: 'missing' }
      failure = { note: `HTTP ${response.status}`, status: response.status }
    } catch (error) {
      failure = { note: error.message }
    }
    if (attempt < attempts) await new Promise((resolve) => setTimeout(resolve, pause * attempt))
  }

  return { state: 'unknown', ...failure }
}

async function eachLimited(items, limit, work) {
  let next = 0
  const worker = async () => {
    while (next < items.length) await work(items[next++])
  }
  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, worker))
}

const publishedFromCi = (document) =>
  Object.values(document?.versions ?? {}).some((version) => version.dist?.attestations)

/** Asks the three services; `mirrorsOnly` is the day's PR. */
export async function check(
  { npm, composer },
  { mirrorsOnly = false, fetcher = fetch, token, pause } = {},
) {
  const result = {
    mirrorsOnly,
    npm: [],
    mirrors: [],
    packagist: [],
    unproven: [],
    unprovenElsewhere: [],
    unknown: [],
    checked: { npm: 0, mirrors: 0, packagist: 0 },
    unchecked: { npm: 0, mirrors: 0, packagist: 0 },
    rateLimited: false,
  }

  const count = (kind, label, answer, onMissing) => {
    result.checked[kind]++
    if (answer.state === 'missing') onMissing()
    if (answer.state === 'unknown') {
      result.unchecked[kind]++
      result.unknown.push(`${label}: ${answer.note}`)
    }
  }

  const askNpm = async (item) => {
    const answer = await probe(npmUrl(item.name), {
      fetcher,
      pause,
      headers: { accept: 'application/vnd.npm.install-v1+json' },
    })
    count('npm', `npm ${item.name}`, answer, () => result.npm.push(item))
    if (answer.state !== 'found') return
    const document = await answer.response.json().catch(() => null)
    if (document && !publishedFromCi(document)) {
      const inThisRelease = !document.versions?.[item.version]
      ;(inThisRelease ? result.unproven : result.unprovenElsewhere).push(item.name)
    }
  }

  const askMirror = async (repository) => {
    const headers = {
      accept: 'application/vnd.github+json',
      ...(token ? { authorization: `Bearer ${token}` } : {}),
    }
    const answer = await probe(mirrorUrl(repository), { fetcher, pause, headers })
    count('mirrors', `зеркало ${repository}`, answer, () => result.mirrors.push(repository))
    // With a token already sent, the advice to send one would be wrong.
    if (!token && [403, 429].includes(answer.status)) result.rateLimited = true
  }

  const askPackagist = async (repository) => {
    const answer = await probe(packagistUrl(repository), { fetcher, pause })
    count('packagist', `Packagist ${repository}`, answer, () => result.packagist.push(repository))
  }

  await Promise.all([
    eachLimited(composer, 8, askMirror),
    ...(mirrorsOnly ? [] : [eachLimited(npm, 8, askNpm), eachLimited(composer, 8, askPackagist)]),
  ])

  const lists = [
    result.mirrors,
    result.packagist,
    result.unproven,
    result.unprovenElsewhere,
    result.unknown,
  ]
  lists.forEach((list) => list.sort())
  result.npm.sort((a, b) => a.name.localeCompare(b.name))

  return result
}

export const isGreen = (result) =>
  result.npm.length + result.mirrors.length + result.packagist.length + result.unknown.length === 0

const tarball = ({ name, version }) => `${name.replace('@', '').replace('/', '-')}-${version}.tgz`

function npmSteps(packages, worktree) {
  const filters = packages.map(({ name }) => `--filter "${name}..."`).join(' ')
  const lines = [
    `npm: нужна первая ручная публикация — ${packages.map(({ name }) => name).join(', ')}`,
    '  Этого имени нет в реестре, а Trusted Publishing новое имя не создаёт: после мержа',
    '  «Version or publish» ответит 404. Публикует человек, до мержа этого PR:',
    ...(worktree === WORKTREE_PLACEHOLDER
      ? [
          '',
          `  ${WORKTREE_PLACEHOLDER} в командах — путь к новому каталогу вне монорепы, не симлинк. Подставить свой`,
          '  или запустить локально: node scripts/release-preflight.mjs --worktree=<путь>',
        ]
      : []),
    '',
    `  1. cd ~/work/webx-ui && git fetch origin && git worktree add ${worktree} origin/changeset-release/main`,
    `  2. cd ${worktree} && pnpm install --frozen-lockfile && pnpm ${filters} build`,
  ]

  packages.forEach((item, index) => {
    const file = tarball(item)
    const step = index + 3
    lines.push(
      `  ${step}. ${item.name}@${item.version}:`,
      `     cd ${worktree}/${item.dir} && pnpm pack && tar -xzf ${file}`,
      `     jq '.version, .dependencies, .peerDependencies' package/package.json && ls package/dist && rm -rf package`,
      `     Ждём: версия ${item.version}, диапазоны без workspace:, в dist есть сборка.`,
      `     npm publish ${file} --access public   (с 2FA, из настоящего терминала)`,
    )
  })

  const next = packages.length + 3
  lines.push(
    `  ${next}. Для каждого пакета: npmjs.com → пакет → Settings → Trusted Publishing → GitHub Actions:`,
    '     webx-ui / webx-ui / release.yml, environment пустой, отметить Allow npm publish.',
    `  ${next + 1}. npm logout && cd ~/work/webx-ui && git worktree remove ${worktree}`,
  )

  return lines
}

function mirrorSteps(repositories, mirrorsOnly) {
  return [
    `Зеркала: нет на GitHub — ${repositories.join(', ')}`,
    '  php-split зеркалит только существующие репозитории. Создать без README и лицензии:',
    '',
    ...repositories.map((repository) => `  gh repo create ${OWNER}/${repository} --public`),
    '',
    mirrorsOnly
      ? '  Первый коммит положит сплит после мержа этого PR.'
      : '  Наполнить их до Submit на Packagist: gh workflow run php-split.yml --ref main',
  ]
}

function packagistSteps(repositories) {
  return [
    `Packagist: не отправлены — ${repositories.join(', ')}`,
    '  Вебхук обновляет только отправленные пакеты: тег придёт в зеркало, а версии не будет.',
    '  Для каждого: https://packagist.org/packages/submit → URL → Check → Submit (зеркало должно быть наполнено):',
    '',
    ...repositories.map((repository) => `  https://github.com/${OWNER}/${repository}`),
  ]
}

/** The text a person reads in the red check: what is wrong and the commands that fix it. */
export function report(result, { runId, worktree = WORKTREE_PLACEHOLDER } = {}) {
  const { checked, unchecked, mirrorsOnly } = result
  const tally = (kind, label) => {
    const found = checked[kind] - result[kind].length - unchecked[kind]
    const rest = unchecked[kind] ? `, не проверено ${unchecked[kind]}` : ''
    return `${label} ${found}/${checked[kind]}${rest}`
  }
  const counts = mirrorsOnly
    ? [tally('mirrors', 'зеркала')]
    : [tally('npm', 'npm'), tally('mirrors', 'зеркала'), tally('packagist', 'Packagist')]
  const verdict = isGreen(result) ? 'всё на месте' : 'релиз не готов'

  const sections = [[`Preflight релиза (${counts.join(', ')}): ${verdict}.`]]

  if (result.npm.length) sections.push(npmSteps(result.npm, worktree))
  if (result.mirrors.length) sections.push(mirrorSteps(result.mirrors, mirrorsOnly))
  if (result.packagist.length) sections.push(packagistSteps(result.packagist))

  if (result.unknown.length) {
    sections.push([
      'Не удалось проверить — это сбой сервиса, а не отсутствие пакета:',
      ...result.unknown.map((line) => `  ${line}`),
      ...(result.rateLimited
        ? [
            '',
            '  GitHub ограничил запросы без токена: 60 в час, а один прогон — около 50. Запускать так:',
            '  GH_TOKEN=$(gh auth token) node scripts/release-preflight.mjs',
          ]
        : []),
    ])
  }

  if (result.unproven.length) {
    sections.push([
      `Предупреждение: идут в этот релиз, но ни одна версия не вышла из CI — ${result.unproven.join(', ')}`,
      '  Значит, доверенный издатель ещё не подтверждён публикацией. Отсюда настройка не видна:',
      '  посмотреть Settings → Trusted Publishing на npmjs.com. Без издателя будет 404 у одного пакета.',
    ])
  }

  if (result.unprovenElsewhere.length) {
    sections.push([
      `Остальные, не подтверждённые публикацией из CI (в этот релиз не идут): ${result.unprovenElsewhere.join(', ')}`,
    ])
  }

  if (!isGreen(result)) {
    sections.push([
      runId
        ? `Когда сделано, перезапустить чек: gh run rerun ${runId} --failed`
        : 'Когда сделано, запустить проверку ещё раз.',
    ])
  }

  return sections.map((lines) => lines.join('\n')).join('\n\n')
}

/** One line per kind for the annotations on the pull request; the commands stay in the log. */
export function annotations(result) {
  const names = (list) => list.join(', ')
  return [
    result.npm.length &&
      `::error::Нужна первая ручная публикация в npm: ${names(result.npm.map(({ name }) => name))}`,
    result.mirrors.length && `::error::Нет зеркала на GitHub: ${names(result.mirrors)}`,
    result.packagist.length && `::error::Нет на Packagist: ${names(result.packagist)}`,
    result.unknown.length && `::error::Не удалось проверить: ${names(result.unknown)}`,
    result.unproven.length &&
      `::warning::Идут в этот релиз, издатель не подтверждён публикацией из CI: ${names(result.unproven)}`,
  ].filter(Boolean)
}

/** `--worktree=<path>`; an empty value is no path at all. */
export const worktreeArgument = (argv) =>
  argv.find((item) => item.startsWith('--worktree='))?.slice('--worktree='.length) || undefined

async function main() {
  const { env, argv } = process
  const root = join(dirname(fileURLToPath(import.meta.url)), '..')
  const result = await check(readPackages(root), {
    mirrorsOnly: argv.includes('--mirrors'),
    token: env.GH_TOKEN || env.GITHUB_TOKEN,
  })
  const text = report(result, { runId: env.GITHUB_RUN_ID, worktree: worktreeArgument(argv) })

  console.log(text)
  if (env.GITHUB_ACTIONS) annotations(result).forEach((line) => console.log(line))
  if (env.GITHUB_STEP_SUMMARY) {
    appendFileSync(env.GITHUB_STEP_SUMMARY, `\`\`\`text\n${text}\n\`\`\`\n`)
  }
  if (!isGreen(result)) process.exitCode = 1
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) await main()
