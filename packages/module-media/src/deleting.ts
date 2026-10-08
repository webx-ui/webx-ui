import { pluralForm, type Translate } from '@webx-ui/module-admin'
import type { UsagePlace } from './types'

/**
 * What a folder holds, said the way its language says it: «3 файла и 1 папка», «1 file and
 * 2 folders». Each count picks its own form — a line with `файл(ов)` in it reads as a form to
 * fill in, not as a question somebody is asking.
 */
export function counted(t: Translate, locale: string, files: number, folders: number): string {
  return [
    files > 0 ? t(`dialogs.count-files.${pluralForm(files, locale)}`, { count: files }) : null,
    folders > 0
      ? t(`dialogs.count-folders.${pluralForm(folders, locale)}`, { count: folders })
      : null,
  ]
    .filter((part): part is string => part !== null)
    .join(` ${t('dialogs.and')} `)
}

/**
 * A row that uses a file, the way an editor knows it: «Страница: About us». The table is said
 * only when nothing better is known about the row.
 */
export function placeText(place: UsagePlace): string {
  const row = place.id === null ? place.table : `${place.table} #${place.id}`

  if (place.kind) {
    const name = place.label ?? (place.id === null ? null : `#${place.id}`)

    return name ? `${place.kind}: ${name}` : place.kind
  }

  return place.label ? `«${place.label}» · ${row}` : row
}

/**
 * The folders files came from, for putting them back: a search lists files of many folders,
 * and undoing a move returns each to its own.
 */
export function byFolder(from: Map<number, number>): Map<number, number[]> {
  const folders = new Map<number, number[]>()

  for (const [id, directory] of from) {
    folders.set(directory, [...(folders.get(directory) ?? []), id])
  }

  return folders
}
