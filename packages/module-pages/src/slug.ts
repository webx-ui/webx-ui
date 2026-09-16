/**
 * Cyrillic and the other alphabets the panel is translated into, as the Latin a URL can hold.
 *
 * The server transliterates too, and the same way — `Str::slug` is what actually decides the
 * address. This is the field following the title as it is typed, so that what the editor sees
 * before saving is what they get; where the two ever disagree, the server wins and the address
 * that comes back is the address.
 */
const TRANSLITERATION: Record<string, string> = {
  а: 'a',
  б: 'b',
  в: 'v',
  г: 'g',
  ґ: 'g',
  д: 'd',
  е: 'e',
  ё: 'e',
  є: 'ie',
  ж: 'zh',
  з: 'z',
  и: 'i',
  і: 'i',
  ї: 'i',
  й: 'i',
  к: 'k',
  л: 'l',
  м: 'm',
  н: 'n',
  о: 'o',
  п: 'p',
  р: 'r',
  с: 's',
  т: 't',
  у: 'u',
  ф: 'f',
  х: 'h',
  ц: 'ts',
  ч: 'ch',
  ш: 'sh',
  щ: 'shch',
  ъ: '',
  ы: 'y',
  ь: '',
  э: 'e',
  ю: 'iu',
  я: 'ia',
  ä: 'a',
  ö: 'o',
  ü: 'u',
  ß: 'ss',
  å: 'a',
  ø: 'o',
  æ: 'ae',
  ç: 'c',
  ğ: 'g',
  ı: 'i',
  ş: 's',
  ą: 'a',
  ć: 'c',
  ę: 'e',
  ł: 'l',
  ń: 'n',
  ó: 'o',
  ś: 's',
  ź: 'z',
  ż: 'z',
}

/** A title as the last part of an address: lower case, Latin, words joined by hyphens. */
export function slugify(title: string): string {
  return (
    title
      .toLowerCase()
      .split('')
      .map((character) => TRANSLITERATION[character] ?? character)
      .join('')
      // Accents that survived the map — `é`, `à` — come apart into a letter and a mark, and the
      // mark is what has to go; `é` must not become `-`.
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
  )
}
