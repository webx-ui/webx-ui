import type { ScreenNode } from '../../../../packages/schema/src/types'
import { dictionary } from './lang'
import { screen } from './screens'

/**
 * Help as `GET /api/cms/help/{key}` answers it (WEBX_UI_PANEL_HELP.md §3.2): the texts are a
 * fixture, and the labels, hints and tabs come from the same tree the form gets. `HelpBuilder` is
 * the real thing.
 *
 * Three keys, one for each thing the panel has to draw: a screen with no tabs, a screen with tabs
 * and a field nobody described (the SEO tab), and a page with no tree. Texts are in `en` and `ru`
 * only, so a panel in any other language gets the English text and the line that says so.
 */

type Text = {
  title: string
  about: string
  howto?: { title: string; body: string }[]
  fields?: Record<string, string>
}

type HelpField = {
  id: string
  label: string | null
  hint: string | null
  required: boolean
  body: string | null
}

type HelpGroup = { id: string; label: string | null; fields: HelpField[] }

const TEXTS: Record<string, Record<string, Text>> = {
  'catalog-stock.index': {
    en: {
      title: 'Stock statuses',
      about:
        'A stock status tells a buyer whether an item can be had right now. Here you keep the list of statuses; an item gets its status on its own card.',
    },
    ru: {
      title: 'Статусы наличия',
      about:
        'Статус наличия говорит покупателю, можно ли получить товар сейчас. Здесь вы ведёте список статусов, а сам товар получает статус в своей карточке.',
    },
  },
  'catalog.stock-status-form': {
    en: {
      title: 'Stock status',
      about:
        'Here you set what the status is called, how it looks and whether an item in it can be bought. An item gets its status on its own card.',
      howto: [
        {
          title: 'How to make a status an item cannot be bought in',
          body: '1. Press «New status» in the list.\n2. Fill in the title.\n3. Switch purchase off.\n4. Save, and set the status on the items that need it.',
        },
      ],
      fields: {
        title: 'A buyer sees this word on the item and in the catalogue filter, so keep it short.',
        code: 'Links to the filter that are already out in adverts and bookmarks stop choosing this status if the code changes.',
      },
    },
    ru: {
      title: 'Статус наличия',
      about:
        'Здесь вы задаёте, как статус называется, как выглядит и можно ли купить товар в этом статусе. Сам товар получает статус в своей карточке.',
      howto: [
        {
          title: 'Как сделать статус, при котором товар нельзя купить',
          body: '1. В списке статусов нажмите «Новый статус».\n2. Заполните название.\n3. Выключите покупку.\n4. Сохраните статус и поставьте его нужным товарам.',
        },
      ],
      fields: {
        title:
          'Это слово покупатель видит у товара и в фильтре каталога, поэтому лучше писать коротко.',
        code: 'Если код поменять, ссылки на фильтр, которые уже разошлись по рекламе и закладкам, перестанут выбирать этот статус.',
      },
    },
  },
  'blog.category-form': {
    en: {
      title: 'Rubric',
      about: 'A rubric gathers articles on one subject and gives them a page of their own.',
      fields: {
        title: 'A buyer sees this name in the menu of the blog and at the top of the rubric.',
        'is-visible': 'Use it to prepare a rubric before anybody can see it.',
      },
    },
    ru: {
      title: 'Рубрика',
      about: 'Рубрика собирает статьи на одну тему и даёт им отдельную страницу.',
      fields: {
        title: 'Это название покупатель видит в меню блога и в начале рубрики.',
        'is-visible': 'Удобно, чтобы подготовить рубрику, пока её никто не видит.',
      },
    },
  },
}

/** Only the keys, as in the manifest of a real server: a screen without help shows no «?». */
export const helpKeys = Object.keys(TEXTS).sort()

export function help(key: string, locale: string) {
  const texts = TEXTS[key]

  if (texts === undefined) {
    return null
  }

  const language = texts[locale] === undefined ? 'en' : locale
  const text = texts[language]
  const groups = new Map<string, HelpGroup>()

  collect(screen(key) ?? [], text, '_screen', null, groups, words(locale))

  return {
    key,
    title: text.title,
    locale: language,
    about: text.about,
    howto: text.howto ?? [],
    groups: [...groups.values()],
    guide: null,
  }
}

/** The walk of `HelpBuilder::collect()`: it goes inside a repeater, and a tab starts a group. */
function collect(
  nodes: ScreenNode[],
  text: Text,
  group: string,
  label: string | null,
  groups: Map<string, HelpGroup>,
  say: (value: unknown) => string | null,
): void {
  for (const node of nodes) {
    if (node.visible === false) {
      continue
    }

    if (node.type === 'wx-tab') {
      collect(node.children ?? [], text, node.id, say(node.label), groups, say)
      continue
    }

    if (typeof node.name === 'string' && node.name !== '') {
      if (!groups.has(group)) {
        groups.set(group, { id: group, label, fields: [] })
      }

      groups.get(group)?.fields.push({
        id: node.id,
        label: say(node.label),
        hint: say(node.help),
        required: node.required === true,
        body: text.fields?.[node.id] ?? null,
      })
    }

    collect(node.children ?? [], text, group, label, groups, say)
  }
}

/** `trans::<namespace>::<group>.<key>` in words, the English ones if the language has none. */
function words(locale: string): (value: unknown) => string | null {
  const dictionaries = [dictionary(locale), dictionary('en')]

  return (value) => {
    if (typeof value !== 'string' || value === '') {
      return null
    }

    if (!value.startsWith('trans::')) {
      return value
    }

    const [namespace, path] = value.slice('trans::'.length).split('::')
    const [group, ...keys] = path.split('.')

    for (const lines of dictionaries) {
      let found: unknown = lines[namespace]?.[group]

      for (const key of keys) {
        found = (found as Record<string, unknown> | undefined)?.[key]
      }

      if (typeof found === 'string') {
        return found
      }
    }

    return value
  }
}
