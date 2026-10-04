import type { IncomingMessage, ServerResponse } from 'node:http'
import { json, localeOf, multipart, normalise, parseCsv, raw } from './seo-links'

/**
 * The rules of `module-seo` and the FAQ of a page (§18.5 of WEBX_UI_MODULE_SEO.md) for the
 * playground: the list with its FAQ column and filter, the form with its questions, and the FAQ
 * import with its preview and the export.
 *
 * What is real: only an exact rule keeps questions (a 422 on the kind otherwise), a question
 * needs its answer, the import groups by address in the order of the file, finds the exact rule
 * or makes one with empty meta, and refuses another host and a repeated question. What is not:
 * a rule is never bound to an entity, the address is not followed through redirects, and XLSX is
 * refused both ways.
 */

type On = (
  method: string,
  pattern: string,
  handler: (context: {
    params: string[]
    query: URLSearchParams
    body: Record<string, unknown>
    locale: string
  }) => unknown,
) => void
type Fail = (status: number, message: string, errors?: Record<string, string[]>) => Error
type Line = (locale: string, namespace: string, path: string) => string
type Words = Record<string, string>

interface Question {
  id: number
  question: Words
  answer: Words
}

interface Rule {
  id: number
  match_type: 'exact' | 'mask' | 'regex'
  pattern: string
  priority: number
  is_active: boolean
  fields: Record<string, unknown>
  faq: Question[]
  created_at: string
  updated_at: string
}

interface Problem {
  line: number
  field: string
  code: string
  level: 'error' | 'warning'
  message: string
}

const TRANSLATED = ['title', 'h1', 'description', 'keywords', 'og_title', 'og_description']
const PLAIN = ['og_image', 'canonical', 'robots', 'json_ld']

let nextRule = 1
let nextQuestion = 1

const stamp = () => new Date().toISOString()
const rules: Rule[] = []

function seed(
  matchType: Rule['match_type'],
  pattern: string,
  title: string | null,
  faq: [string, string][] = [],
): void {
  rules.push({
    id: nextRule++,
    match_type: matchType,
    pattern,
    priority: 0,
    is_active: true,
    fields: title === null ? {} : { title: { ru: title } },
    faq: faq.map(([question, answer]) => ({
      id: nextQuestion++,
      question: { ru: question },
      answer: { ru: answer },
    })),
    created_at: stamp(),
    updated_at: stamp(),
  })
}

seed('exact', '/services', 'Услуги и цены', [
  ['Сколько стоит выезд мастера?', '<p>Выезд в пределах города — <strong>бесплатно</strong>.</p>'],
  ['Даёте ли вы гарантию?', '<p>Да, год на все работы.</p>'],
  ['Можно оплатить картой?', '<p>Картой, наличными или по счёту.</p>'],
])
seed('exact', '/about', 'О компании')
seed('exact', '/faq', null, [['Как с вами связаться?', '<p>По телефону или в чате.</p>']])
seed('mask', '/blog/*', 'Блог: {title}')

/** A question nobody tells apart from another by case or spacing. */
const keyOf = (text: string) => text.trim().replace(/\s+/g, ' ').toLowerCase()

/** Plain text is escaped into paragraphs, the way the server keeps an answer. */
function html(answer: string): string {
  const text = answer.trim()

  if (text === '' || /<[a-z][a-z0-9]*[\s>/]/i.test(text)) return text

  const escape = (value: string) =>
    value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')

  return text
    .split(/\r?\n\s*\r?\n/)
    .map((paragraph) => `<p>${escape(paragraph.trim()).replace(/\r?\n/g, '<br>')}</p>`)
    .join('')
}

function kept(value: unknown): Words {
  const words: Words = {}

  if (value === null || typeof value !== 'object') return words

  for (const [code, text] of Object.entries(value as Record<string, unknown>)) {
    if (typeof text === 'string' && text.trim() !== '') words[code] = text.trim()
  }

  return words
}

function describe(rule: Rule, withFaq: boolean) {
  const fields: Record<string, unknown> = {}

  for (const key of TRANSLATED) fields[key] = rule.fields[key] ?? {}
  for (const key of PLAIN) fields[key] = rule.fields[key] ?? null

  return {
    id: rule.id,
    match_type: rule.match_type,
    pattern: rule.pattern,
    entity_type: null,
    entity_id: null,
    current_pattern: rule.pattern,
    redirected_from: null,
    priority: rule.priority,
    is_active: rule.is_active,
    created_at: rule.created_at,
    updated_at: rule.updated_at,
    ...fields,
    faq_count: rule.faq.length,
    ...(withFaq ? { faq: rule.faq } : {}),
  }
}

function say(line: Line, locale: string, code: string): string {
  return line(locale, 'webx-seo', `faq.${code}`)
}

export function registerSeoFaq(on: On, fail: Fail, line: Line): void {
  const find = (id: string): Rule => {
    const rule = rules.find((candidate) => candidate.id === Number(id))

    if (!rule) throw fail(404, 'Not found.')

    return rule
  }

  on('GET', '/seo/urls', ({ query }) => {
    const term = (query.get('q') ?? '').trim().toLowerCase()
    const kind = query.get('match_type')
    const withFaq = ['1', 'true'].includes(query.get('has_faq') ?? '')
    const perPage = Number(query.get('per_page') ?? 25)
    const page = Math.max(1, Number(query.get('page') ?? 1))
    const order = { exact: 0, mask: 1, regex: 2 }

    const found = rules
      .filter((rule) => term === '' || rule.pattern.toLowerCase().includes(term))
      .filter((rule) => kind === null || rule.match_type === kind)
      .filter((rule) => !withFaq || rule.faq.length > 0)
      .sort((a, b) => order[a.match_type] - order[b.match_type] || b.priority - a.priority)

    const rows = found.slice((page - 1) * perPage, page * perPage)
    const from = rows.length > 0 ? (page - 1) * perPage + 1 : null

    return {
      data: rows.map((rule) => describe(rule, false)),
      meta: {
        current_page: page,
        last_page: Math.max(1, Math.ceil(found.length / perPage)),
        per_page: perPage,
        total: found.length,
        from,
        to: from === null ? null : from + rows.length - 1,
      },
    }
  })

  on('GET', '/seo/urls/(\\d+)', ({ params }) => ({ data: describe(find(params[0]!), true) }))

  const write = (rule: Rule | null, body: Record<string, unknown>, locale: string) => {
    const matchType = String(body.match_type ?? 'exact') as Rule['match_type']
    const pattern = String(body.pattern ?? '').trim()
    const errors: Record<string, string[]> = {}

    if (pattern === '') errors.pattern = ['The address is required.']

    let faq: Question[] | null = null

    if (Array.isArray(body.faq)) {
      faq = []

      body.faq.forEach((item: unknown, index) => {
        const row = (item ?? {}) as Record<string, unknown>
        const question = kept(row.question)
        const answer = kept(row.answer)
        const hasQuestion = Object.keys(question).length > 0
        const hasAnswer = Object.keys(answer).length > 0

        if (hasAnswer && !hasQuestion) {
          errors[`faq.${index}.question`] = [say(line, locale, 'question-missing')]
        } else if (hasQuestion && !hasAnswer) {
          errors[`faq.${index}.answer`] = [say(line, locale, 'answer-missing')]
        } else if (hasQuestion) {
          faq!.push({
            id: nextQuestion++,
            question,
            answer: Object.fromEntries(
              Object.entries(answer).map(([code, text]) => [code, html(text)]),
            ),
          })
        }
      })
    }

    const keeps = faq !== null ? faq.length > 0 : (rule?.faq.length ?? 0) > 0

    if (matchType !== 'exact' && keeps) errors.match_type = [say(line, locale, 'has-faq')]

    if (Object.keys(errors).length > 0) throw fail(422, 'The given data was invalid.', errors)

    const fields: Record<string, unknown> = {}

    for (const key of TRANSLATED) fields[key] = kept(body[key])
    for (const key of PLAIN) fields[key] = body[key] ?? null

    const saved: Rule = rule ?? {
      id: nextRule++,
      match_type: matchType,
      pattern,
      priority: 0,
      is_active: true,
      fields: {},
      faq: [],
      created_at: stamp(),
      updated_at: stamp(),
    }

    Object.assign(saved, {
      match_type: matchType,
      pattern: matchType === 'regex' ? pattern : (normalise(pattern) ?? pattern),
      priority: Number(body.priority ?? 0),
      is_active: body.is_active !== false,
      fields,
      faq: faq ?? saved.faq,
      updated_at: stamp(),
    })

    if (rule === null) rules.push(saved)

    return { data: describe(saved, true) }
  }

  on('POST', '/seo/urls', ({ body, locale }) => write(null, body, locale))
  on('PUT', '/seo/urls/(\\d+)', ({ params, body, locale }) => write(find(params[0]!), body, locale))
  on('DELETE', '/seo/urls/(\\d+)', ({ params }) => {
    rules.splice(rules.indexOf(find(params[0]!)), 1)

    return null
  })
}

const HEADERS: Record<string, 'address' | 'question' | 'answer'> = {
  address: 'address',
  url: 'address',
  page: 'address',
  адрес: 'address',
  страница: 'address',
  question: 'question',
  вопрос: 'question',
  answer: 'answer',
  ответ: 'answer',
}

function runImport(line: Line, locale: string, text: string, mode: string, dryRun: boolean) {
  const append = mode === 'append'
  const lines = parseCsv(text.replace(/^\uFEFF/, ''))
  const problems: Problem[] = []
  const problem = (row: number, field: string, code: string) =>
    problems.push({ line: row, field, code, level: 'error', message: say(line, locale, code) })

  let map: Record<string, number> | null = null
  const groups = new Map<string, { items: { question: string; answer: string; line: number }[] }>()

  lines.forEach((cells, index) => {
    const number = index + 1

    if (cells.every((cell) => cell.trim() === '')) return

    if (map === null) {
      const found: Record<string, number> = {}

      cells.forEach((cell, at) => {
        const column = HEADERS[cell.trim().toLowerCase()]

        if (column !== undefined && found[column] === undefined) found[column] = at
      })

      if (['address', 'question', 'answer'].every((column) => found[column] !== undefined)) {
        map = found

        return
      }

      map = { address: 0, question: 1, answer: 2 }
    }

    const cell = (column: string) => (cells[map![column]!] ?? '').trim()
    const address = cell('address')
    const question = cell('question')
    const answer = cell('answer')

    if (address === '' || question === '' || answer === '') {
      problem(number, address === '' ? 'address' : question === '' ? 'question' : 'answer', 'empty')

      return
    }

    const path = normalise(address)

    if (path === null) {
      problem(number, 'address', 'foreign-host')

      return
    }

    if (!groups.has(path)) groups.set(path, { items: [] })

    groups.get(path)!.items.push({ question, answer, line: number })
  })

  const counts = { addresses: 0, questions: 0, created: 0, replaced: 0, appended: 0 }
  const pages: { address: string; questions: number; action: string }[] = []

  for (const [address, group] of groups) {
    const rule =
      rules.find((one) => one.is_active && one.match_type === 'exact' && one.pattern === address) ??
      null
    const language = localeOf(address)
    const seen = new Set(
      append && rule ? rule.faq.map((item) => keyOf(item.question[language] ?? '')) : [],
    )
    const items: Question[] = []

    for (const item of group.items) {
      if (seen.has(keyOf(item.question))) {
        problem(item.line, 'question', 'duplicate')

        continue
      }

      seen.add(keyOf(item.question))
      items.push({
        id: nextQuestion++,
        question: { [language]: item.question },
        answer: { [language]: html(item.answer) },
      })
    }

    if (items.length === 0) continue

    const action = rule === null ? 'create' : append ? 'append' : 'replace'

    counts.addresses++
    counts.questions += items.length
    counts[action === 'create' ? 'created' : action === 'append' ? 'appended' : 'replaced']++
    pages.push({ address, questions: items.length, action })

    if (dryRun) continue

    const target = rule ?? {
      id: nextRule++,
      match_type: 'exact' as const,
      pattern: address,
      priority: 0,
      is_active: true,
      fields: {},
      faq: [],
      created_at: stamp(),
      updated_at: stamp(),
    }

    target.faq = append ? [...target.faq, ...items] : items
    target.updated_at = stamp()

    if (rule === null) rules.push(target)
  }

  problems.sort((a, b) => a.line - b.line)

  return {
    ok: true,
    applied: !dryRun,
    mode: append ? 'append' : 'replace',
    ...counts,
    errors: problems.length,
    problems,
    pages,
  }
}

/** The FAQ's import and export: a file in, a file out. */
export function handleSeoFaqFiles(
  request: IncomingMessage,
  response: ServerResponse,
  url: URL,
  line: Line,
): boolean {
  const method = (request.method ?? 'GET').toUpperCase()

  if (url.pathname === '/api/cms/seo/faq/export' && method === 'GET') {
    if (url.searchParams.get('format') === 'xlsx') {
      return json(response, 422, {
        message: 'The playground writes CSV only; XLSX is checked on a real site.',
      })
    }

    const quote = (cell: string) =>
      /[",\r\n]/.test(cell) ? `"${cell.replaceAll('"', '""')}"` : cell
    const lines = ['address,question,answer']

    for (const rule of rules.filter((one) => one.match_type === 'exact')) {
      const language = localeOf(rule.pattern)

      for (const item of rule.faq) {
        const question = item.question[language]
        const answer = item.answer[language]

        if (question && answer) lines.push([rule.pattern, question, answer].map(quote).join(','))
      }
    }

    response.statusCode = 200
    response.setHeader('Content-Type', 'text/csv; charset=UTF-8')
    response.setHeader('Content-Disposition', 'attachment; filename="faq.csv"')
    response.end(`\uFEFF${lines.join('\r\n')}\r\n`)

    return true
  }

  if (url.pathname === '/api/cms/seo/faq/import' && method === 'POST') {
    void (async () => {
      const header = request.headers['x-webx-locale']
      const locale = typeof header === 'string' && header !== '' ? header : 'en'
      const boundary = /boundary=(?:"([^"]+)"|([^;]+))/.exec(request.headers['content-type'] ?? '')

      if (boundary === null) {
        json(response, 422, {
          message: 'Expected a multipart body.',
          errors: { file: ['Expected a file.'] },
        })

        return
      }

      const parts = multipart(await raw(request), boundary[1] ?? boundary[2]!)
      const file = parts.get('file')

      if (!file || !/\.(csv|txt)$/i.test(file.filename ?? '')) {
        json(response, 422, {
          message: 'The given data was invalid.',
          errors: { file: [say(line, locale, 'unreadable')] },
        })

        return
      }

      const mode = parts.get('mode')?.data.toString('utf8') ?? 'replace'
      const dry = parts.get('dry_run')?.data.toString('utf8') ?? '1'

      json(response, 200, {
        data: runImport(
          line,
          locale,
          file.data.toString('utf8'),
          mode,
          !['0', 'false'].includes(dry),
        ),
      })
    })()

    return true
  }

  return false
}
