import { describe, expect, it } from 'vitest'
import { byFolder, counted, placeText } from './deleting'

/* The Russian lines as the server ships them, and a translator as plain as the panel's. */
const ru: Record<string, string> = {
  'dialogs.count-files.one': ':count файл',
  'dialogs.count-files.few': ':count файла',
  'dialogs.count-files.many': ':count файлов',
  'dialogs.count-files.other': ':count файла',
  'dialogs.count-folders.one': ':count папка',
  'dialogs.count-folders.few': ':count папки',
  'dialogs.count-folders.many': ':count папок',
  'dialogs.count-folders.other': ':count папки',
  'dialogs.and': 'и',
}

const t = (key: string, params: Record<string, string | number> = {}) =>
  Object.entries(params).reduce(
    (line, [name, value]) => line.replaceAll(`:${name}`, String(value)),
    ru[key] ?? key,
  )

describe('what a folder holds', () => {
  it('says each count in the form its number wants', () => {
    expect(counted(t, 'ru', 3, 1)).toBe('3 файла и 1 папка')
    expect(counted(t, 'ru', 5, 2)).toBe('5 файлов и 2 папки')
    expect(counted(t, 'ru', 21, 11)).toBe('21 файл и 11 папок')
  })

  it('leaves out what there is none of', () => {
    expect(counted(t, 'ru', 0, 2)).toBe('2 папки')
    expect(counted(t, 'ru', 1, 0)).toBe('1 файл')
  })
})

describe('where a file is used', () => {
  it('names the row by its title when it has one', () => {
    expect(placeText({ table: 'pages', column: 'blocks', id: 12, label: 'О нас' })).toBe(
      '«О нас» · pages #12',
    )
    expect(placeText({ table: 'cms_settings', column: 'value', id: null, label: null })).toBe(
      'cms_settings',
    )
  })
})

describe('undoing a move', () => {
  it('returns each file to the folder it came from', () => {
    const from = new Map([
      [1, 10],
      [2, 20],
      [3, 10],
    ])

    expect([...byFolder(from)]).toEqual([
      [10, [1, 3]],
      [20, [2]],
    ])
  })
})
