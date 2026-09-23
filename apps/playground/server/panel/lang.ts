import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

/**
 * The panel's dictionary, read out of the PHP packages.
 *
 * The packages carry their English defaults in TypeScript, so a panel without a server is in
 * English — but everything the *server* translates, screen labels first, only exists in
 * `php/packages/*\/lang`. Without them the editor of a page shows `webx-pages::screen.content`
 * where a tab label belongs, and the Russian panel, which is what the demo sites run, cannot be
 * looked at here at all.
 *
 * So the fixture server reads those files. They are flat lists of strings — no interpolation,
 * no PHP to evaluate — and a parser for exactly that shape is shorter than a copy of ten
 * languages would be, and never goes out of date.
 */

const NAMESPACES: Record<string, string> = {
  'module-admin': 'webx-admin',
  'module-auth': 'webx-auth',
  'module-blocks': 'webx-blocks',
  'module-blog': 'webx-blog',
  'module-inbox': 'webx-inbox',
  'module-media': 'webx-media',
  'module-menu': 'webx-menu',
  'module-pages': 'webx-pages',
  'module-seo': 'webx-seo',
  'module-services': 'webx-services',
  'module-settings': 'webx-settings',
}

type Messages = { [key: string]: string | Messages }

/**
 * Namespace → group → lines, which is the shape `GET /translations/<locale>` answers with.
 *
 * Read off disk on every request rather than kept. Vite watches what it imports, and a `.php`
 * file is not that — so a dictionary held in memory stays whatever it was when the server
 * started, and a line added to `lang/ru/article.php` shows up in the panel as its English
 * default with no sign that anything is stale. Ten flat files parsed inside a request that
 * already waits 220 ms on purpose is not a cost worth that.
 */
/**
 * What this site adds to a package's dictionary — `lang/vendor/webx-blocks/<locale>` on a
 * Laravel site, an object here.
 *
 * The blocks module ships three groups and says a site adds its own and translates it there
 * (`config/blocks.php`). This demo's types stand in a fourth, `marketing`, and without these
 * two lines the Russian panel labels the tab `Marketing` — the capitalised id, which is the
 * module being honest about a word nobody gave it, and looks exactly like a bug.
 */
const SITE: Record<string, Record<string, Record<string, Messages>>> = {
  ru: { 'webx-blocks': { groups: { marketing: 'Маркетинг' } } },
  uk: { 'webx-blocks': { groups: { marketing: 'Маркетинг' } } },
  en: { 'webx-blocks': { groups: { marketing: 'Marketing' } } },
}

export function dictionary(locale: string): Record<string, Record<string, Messages>> {
  const namespaces: Record<string, Record<string, Messages>> = {}

  for (const [pkg, namespace] of Object.entries(NAMESPACES)) {
    const dir = root(`php/packages/${pkg}/lang/${locale}`)

    if (!existsSync(dir)) {
      continue
    }

    const groups: Record<string, Messages> = {}

    for (const file of readdirSync(dir)) {
      if (!file.endsWith('.php')) {
        continue
      }

      groups[file.replace(/\.php$/, '')] = parse(readFileSync(`${dir}/${file}`, 'utf8'))
    }

    namespaces[namespace] = groups
  }

  for (const [namespace, groups] of Object.entries(SITE[locale] ?? {})) {
    for (const [group, lines] of Object.entries(groups)) {
      namespaces[namespace] = namespaces[namespace] ?? {}
      namespaces[namespace][group] = {
        ...((namespaces[namespace][group] as Messages | undefined) ?? {}),
        ...lines,
      }
    }
  }

  return namespaces
}

/** The languages the panel itself can be drawn in: whatever `module-admin` has files for. */
export function panelLocales(): string[] {
  const dir = root('php/packages/module-admin/lang')

  return existsSync(dir) ? readdirSync(dir) : ['en']
}

const root = (path: string): string =>
  fileURLToPath(new URL(`../../../../${path}`, import.meta.url))

/**
 * A `return [...]` of strings, as a nested object.
 *
 * Scans rather than matches line by line, because a line of Russian routinely wraps and a
 * value with a `'` in it is escaped as `\'` — both of which a line-wise regex gets wrong.
 */
function parse(source: string): Messages {
  const start = source.indexOf('return')

  if (start < 0) {
    return {}
  }

  const [value] = readArray(source, source.indexOf('[', start) + 1)

  return value
}

function readArray(source: string, from: number): [Messages, number] {
  const messages: Messages = {}
  let index = from
  let key: string | null = null

  while (index < source.length) {
    const char = source[index]

    if (char === ']') {
      return [messages, index + 1]
    }

    // A comment is skipped whole: an apostrophe in one ("a module's screen") would otherwise
    // open a string and shift every key after it onto the wrong value.
    if (char === '/' && source[index + 1] === '/') {
      const end = source.indexOf('\n', index)

      index = end < 0 ? source.length : end + 1

      continue
    }

    if (char === '/' && source[index + 1] === '*') {
      const end = source.indexOf('*/', index + 2)

      index = end < 0 ? source.length : end + 2

      continue
    }

    if (char === "'" || char === '"') {
      const [text, next] = readString(source, index)

      if (key === null) {
        key = text
      } else {
        messages[key] = text
        key = null
      }

      index = next

      continue
    }

    if (char === '[' && key !== null) {
      const [nested, next] = readArray(source, index + 1)

      messages[key] = nested
      key = null
      index = next

      continue
    }

    index += 1
  }

  return [messages, index]
}

function readString(source: string, from: number): [string, number] {
  const quote = source[from]
  let text = ''
  let index = from + 1

  while (index < source.length) {
    const char = source[index]

    if (char === '\\') {
      text += source[index + 1] ?? ''
      index += 2

      continue
    }

    if (char === quote) {
      return [text, index + 1]
    }

    text += char
    index += 1
  }

  return [text, index]
}
