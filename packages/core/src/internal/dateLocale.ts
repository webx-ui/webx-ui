import type { RootProps } from '@vuepic/vue-datepicker'

/** What `@vuepic/vue-datepicker` wants for its language: a date-fns locale object. */
export type DateFnsLocale = NonNullable<RootProps['locale']>

type Width = 'narrow' | 'short' | 'abbreviated' | 'wide'
type Context = 'formatting' | 'standalone'

const cache = new Map<string, DateFnsLocale>()

/**
 * Builds a date-fns locale out of `Intl`.
 *
 * The picker bundles exactly one language — `en-US` — and takes any other as a date-fns
 * locale object, so a panel in Russian gets an English calendar unless one is handed in.
 * Reaching for `date-fns/locale` would mean a runtime dependency on date-fns and a chunk
 * per language; the browser already carries every one of them, so the names come from
 * `Intl.DateTimeFormat` and are shaped here into what date-fns reads.
 *
 * Only what the picker asks for is filled in honestly: month, weekday and day-period
 * names, the era, and enough of `match` to parse a typed-in date back. Quarter names and
 * ordinal suffixes have no `Intl` source and fall back to digits — the picker reaches for
 * them only in a quarter picker and in a day cell's `aria-label`, where a bare number
 * reads correctly in most languages anyway.
 */
export function dateFnsLocale(tag: string): DateFnsLocale {
  const cached = cache.get(tag)
  if (cached) return cached

  const built = build(supported(tag) ? tag : 'en-US')
  cache.set(tag, built)

  return built
}

/** `Intl` throws a `RangeError` on a malformed tag rather than falling back. */
function supported(tag: string): boolean {
  try {
    new Intl.DateTimeFormat(tag)

    return true
  } catch {
    return false
  }
}

function build(tag: string): DateFnsLocale {
  const months = memo((key: string) => {
    const [width, context] = key.split(':') as [Width, Context]

    return monthNames(tag, width, context)
  })
  const days = memo((width: string) => dayNames(tag, width as Width))
  const periods = memo(() => dayPeriodNames(tag))
  const eras = memo((width: string) => eraNames(tag, width as Width))
  const quarters = memo((width: string) => quarterNames(width as Width))

  const localize: DateFnsLocale['localize'] = {
    ordinalNumber: (value) => ordinalNumber(tag, Number(value)),
    era: (value, options) => eras(options?.width ?? 'wide')[Number(value)] ?? '',
    quarter: (value, options) => quarters(options?.width ?? 'wide')[Number(value) - 1] ?? '',
    month: (value, options) =>
      months(`${options?.width ?? 'wide'}:${options?.context ?? 'formatting'}`)[Number(value)] ??
      '',
    day: (value, options) => days(options?.width ?? 'wide')[Number(value)] ?? '',
    // `Intl` offers one form of "am"; the widths date-fns asks for do not exist to give.
    dayPeriod: (value) => periods('')[String(value)] ?? '',
  }

  const match: DateFnsLocale['match'] = {
    ordinalNumber: (str) => {
      const found = /^(\d+)(?:st|nd|rd|th|[^\s\d]{0,3})?/i.exec(str)
      if (!found) return null

      return { value: Number(found[1]), rest: str.slice(found[0].length) }
    },
    era: nameMatcher(() => widths.flatMap((width) => indexed<Matched<'era'>>(eras(width)))),
    quarter: nameMatcher(() =>
      widths.flatMap((width) =>
        quarters(width).map((name, index) => ({ name, value: (index + 1) as Matched<'quarter'> })),
      ),
    ),
    month: nameMatcher(() =>
      widths.flatMap((width) =>
        contexts.flatMap((context) => indexed<Matched<'month'>>(months(`${width}:${context}`))),
      ),
    ),
    day: nameMatcher(() => widths.flatMap((width) => indexed<Matched<'day'>>(days(width)))),
    dayPeriod: nameMatcher(() =>
      Object.entries(periods('')).map(([value, name]) => ({
        name,
        value: value as Matched<'dayPeriod'>,
      })),
    ),
  }

  return {
    code: tag,
    localize,
    match,
    formatLong: formatLong(tag),
    /*
     * The picker never reaches for relative or distance wording — it draws a calendar,
     * not "3 days ago". The shape is part of the type, so it is filled in rather than
     * left out.
     */
    formatDistance: () => '',
    formatRelative: () => '',
    options: { weekStartsOn: weekStartsOn(tag), firstWeekContainsDate: 1 },
  } as DateFnsLocale
}

const widths: Width[] = ['narrow', 'short', 'abbreviated', 'wide']
const contexts: Context[] = ['formatting', 'standalone']

function memo<T>(make: (key: string) => T): (key: string) => T {
  const store = new Map<string, T>()

  return (key) => {
    if (!store.has(key)) store.set(key, make(key))

    return store.get(key) as T
  }
}

/** What date-fns expects a given matcher to hand back — `Month`, `Day` and friends. */
type Matched<K extends keyof DateFnsLocale['match']> = NonNullable<
  ReturnType<DateFnsLocale['match'][K]>
>['value']

function indexed<T>(names: string[]): { name: string; value: T }[] {
  return names.map((name, value) => ({ name, value: value as T }))
}

/**
 * Longest name first, so "сентябрь" is not cut short by the abbreviated "сент" that
 * starts the same way.
 */
function nameMatcher<T>(entries: () => { name: string; value: T }[]) {
  return (str: string) => {
    const sorted = [...entries()].sort((a, b) => b.name.length - a.name.length)
    const lower = str.toLowerCase()

    for (const { name, value } of sorted) {
      if (name && lower.startsWith(name.toLowerCase())) {
        return { value, rest: str.slice(name.length) }
      }
    }

    return null
  }
}

/** A year with no daylight-saving surprises, read in UTC so the day never slips. */
function at(month: number, day = 15, hour = 12): Date {
  return new Date(Date.UTC(2021, month, day, hour))
}

function partOf(format: Intl.DateTimeFormat, date: Date, type: Intl.DateTimeFormatPartTypes) {
  return format.formatToParts(date).find((part) => part.type === type)?.value ?? ''
}

/**
 * Standalone and formatting month names differ in half of Europe: Russian heads the
 * calendar with "сентябрь" but writes "15 сентября" in a sentence. `Intl` draws the same
 * line — a month on its own comes out standalone, a month beside a day comes out in the
 * sentence form — so the two are read from two formatters.
 */
function monthNames(tag: string, width: Width, context: Context): string[] {
  const month = width === 'wide' ? 'long' : width === 'narrow' ? 'narrow' : 'short'
  const format = new Intl.DateTimeFormat(tag, {
    month,
    ...(context === 'formatting' ? { day: 'numeric' as const } : {}),
    timeZone: 'UTC',
  })

  return Array.from({ length: 12 }, (_, index) => partOf(format, at(index), 'month'))
}

function dayNames(tag: string, width: Width): string[] {
  const weekday = width === 'wide' ? 'long' : width === 'narrow' ? 'narrow' : 'short'
  const format = new Intl.DateTimeFormat(tag, { weekday, timeZone: 'UTC' })
  // 1 August 2021 was a Sunday, which is index 0 in date-fns.
  const names = Array.from({ length: 7 }, (_, index) => format.format(at(7, 1 + index)))

  /*
   * date-fns keeps a two-letter weekday ("Su", "вс") that `Intl` does not offer, and the
   * picker's own header format asks for exactly that one. Cutting the abbreviated name
   * down lands on it in every language checked — "Sun" to "Su", "dim." to "di", "вс" as
   * it already is.
   */
  return width === 'short'
    ? names.map((name) => [...name.replace(/\.$/, '')].slice(0, 2).join(''))
    : names
}

function dayPeriodNames(tag: string): Record<string, string> {
  const format = new Intl.DateTimeFormat(tag, { hour: 'numeric', hour12: true, timeZone: 'UTC' })
  const am = partOf(format, at(0, 1, 9), 'dayPeriod') || 'AM'
  const pm = partOf(format, at(0, 1, 21), 'dayPeriod') || 'PM'

  return { am, pm, midnight: am, noon: pm, morning: am, afternoon: pm, evening: pm, night: pm }
}

function eraNames(tag: string, width: Width): string[] {
  const era = width === 'wide' ? 'long' : width === 'narrow' ? 'narrow' : 'short'
  const format = new Intl.DateTimeFormat(tag, { era, year: 'numeric', timeZone: 'UTC' })
  const bc = at(0, 1)
  bc.setUTCFullYear(-500)

  return [partOf(format, bc, 'era'), partOf(format, at(0, 1), 'era')]
}

/**
 * `Intl` has no quarter names, and no calendar in this kit shows one — the picker only
 * asks in a quarter picker, which none of the presets is.
 */
function quarterNames(width: Width): string[] {
  return width === 'narrow' ? ['1', '2', '3', '4'] : ['Q1', 'Q2', 'Q3', 'Q4']
}

/**
 * Only English spells its ordinals with a suffix; elsewhere the digits alone are what a
 * reader expects, which is also what date-fns' own non-English locales mostly return.
 */
function ordinalNumber(tag: string, value: number): string {
  if (!tag.toLowerCase().startsWith('en')) return String(value)

  const rest = value % 100
  if (rest >= 11 && rest <= 13) return `${value}th`

  return `${value}${['th', 'st', 'nd', 'rd'][value % 10] ?? 'th'}`
}

/**
 * `P` and `p` tokens, which only a caller's own `format` can reach. `Intl` will not hand
 * out a pattern, so the order of the parts is read off a formatted date and the rest is
 * the conventional shape for that order.
 */
function formatLong(tag: string): DateFnsLocale['formatLong'] {
  const parts = new Intl.DateTimeFormat(tag, { timeZone: 'UTC' })
    .formatToParts(at(0, 1))
    .filter((part) => part.type === 'day' || part.type === 'month' || part.type === 'year')
    .map((part) => part.type[0])
    .join('')

  const date =
    parts === 'mdy'
      ? { full: 'EEEE, MMMM do, y', long: 'MMMM do, y', medium: 'MMM d, y', short: 'MM/dd/yyyy' }
      : parts === 'ymd'
        ? { full: 'y MMMM d, EEEE', long: 'y MMMM d', medium: 'y MMM d', short: 'yyyy-MM-dd' }
        : { full: 'EEEE, d MMMM y', long: 'd MMMM y', medium: 'd MMM y', short: 'dd.MM.yyyy' }

  const hour12 = new Intl.DateTimeFormat(tag, { hour: 'numeric', timeZone: 'UTC' })
    .formatToParts(at(0, 1, 21))
    .some((part) => part.type === 'dayPeriod')

  const time = hour12
    ? { full: 'h:mm:ss a zzzz', long: 'h:mm:ss a z', medium: 'h:mm:ss a', short: 'h:mm a' }
    : { full: 'HH:mm:ss zzzz', long: 'HH:mm:ss z', medium: 'HH:mm:ss', short: 'HH:mm' }

  const dateTime = {
    full: '{{date}} {{time}}',
    long: '{{date}} {{time}}',
    medium: '{{date}}, {{time}}',
    short: '{{date}}, {{time}}',
  }

  const pick = (formats: Record<string, string>) => (options: { width?: string }) =>
    formats[options?.width ?? 'full'] ?? formats.full

  return { date: pick(date), time: pick(time), dateTime: pick(dateTime) }
}

/** `getWeekInfo` counts Monday as 1 and Sunday as 7; date-fns counts Sunday as 0. */
function weekStartsOn(tag: string): 0 | 1 | 2 | 3 | 4 | 5 | 6 {
  type WithWeekInfo = Intl.Locale & { getWeekInfo?: () => { firstDay: number } }

  try {
    const first = (new Intl.Locale(tag) as WithWeekInfo).getWeekInfo?.().firstDay

    if (first) return (first % 7) as 0 | 1 | 2 | 3 | 4 | 5 | 6
  } catch {
    // Not every engine carries week data; Monday is this kit's default anyway.
  }

  return 1
}
