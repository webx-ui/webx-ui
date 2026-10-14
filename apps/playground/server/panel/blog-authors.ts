import type { ScreenModel } from '../../../../packages/schema/src/types'
import type { AuthorRow } from '../../../../packages/module-blog/src/authors'
import { PREFIX, articles, authors as accounts } from './blog'

/**
 * The profiles of the blog's authors (§19 of the blog spec), on a panel whose site allows author
 * pages: two of the three accounts have one, and one of those two asked for a page.
 *
 * Kept as the values of `blog.author-form`, the way the server keeps them in columns, so what the
 * form opens is what was last saved.
 */
export interface AuthorProfile {
  id: number
  user_id: number
  values: ScreenModel
}

export const profiles: AuthorProfile[] = [
  {
    id: 1,
    user_id: 1,
    values: {
      title: { ru: 'Анна Ковальчук', en: 'Anna Kovalchuk' },
      slug: { ru: 'anna', en: 'anna' },
      role: { ru: 'Главный редактор', en: 'Editor-in-chief' },
      bio: { ru: '<p>Пишет о том, как устроены сайты изнутри.</p>' },
      photo: null,
      is_public: true,
    },
  },
  {
    id: 2,
    user_id: 2,
    values: {
      title: { ru: 'Дмитрий Левченко' },
      slug: { ru: 'dmitry' },
      role: {},
      bio: {},
      photo: null,
      is_public: false,
    },
  },
]

function text(value: unknown, locale: string): string {
  if (typeof value === 'string') return value
  if (value === null || typeof value !== 'object') return ''

  const map = value as Record<string, string>

  return map[locale] || Object.values(map).find((line) => line) || ''
}

function written(userId: number): number {
  return articles.filter((article) => !article.deleted_at && article.values.author_id === userId)
    .length
}

export function authorRow(profile: AuthorProfile, locale: string): AuthorRow {
  const account = accounts.find((one) => one.id === profile.user_id)
  const slug = text(profile.values.slug, locale)

  return {
    id: profile.id,
    name: text(profile.values.title, locale) || `#${profile.id}`,
    user: account ? { id: account.id, name: account.title } : null,
    is_public: profile.values.is_public === true,
    open: profile.values.is_public === true,
    url: slug === '' ? null : `https://webx-demo.test/${PREFIX}/author/${slug}`,
    photo: null,
    articles_count: written(profile.user_id),
  }
}

/** The accounts with no profile — who a new one can be made for, the ones who wrote first. */
export function freeAccounts(): Array<{ id: number; name: string; articles_count: number }> {
  return accounts
    .filter((account) => !profiles.some((profile) => profile.user_id === account.id))
    .map((account) => ({
      id: account.id,
      name: account.title,
      articles_count: written(account.id),
    }))
    .sort((one, two) => two.articles_count - one.articles_count)
}

/** What the server refuses, under the field it belongs to; nothing when it would be saved. */
export function refusal(values: ScreenModel, userId?: number | null): Record<string, string[]> {
  const errors: Record<string, string[]> = {}

  if (userId !== undefined) {
    if (userId === null || !accounts.some((one) => one.id === userId)) {
      errors.user_id = ['Выберите учётную запись, для которой этот профиль.']
    } else if (profiles.some((profile) => profile.user_id === userId)) {
      errors.user_id = ['У этой учётной записи уже есть профиль.']
    }
  }

  const title = values.title as Record<string, string> | undefined

  if (!(title?.ru ?? '').trim()) {
    errors['title.ru'] = ['Напишите имя на основном языке.']
  }

  return errors
}

/** The profile of an account, for the dropdown of the article editor. */
export function profileOf(userId: number): number | null {
  return profiles.find((profile) => profile.user_id === userId)?.id ?? null
}
