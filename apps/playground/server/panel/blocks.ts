import type { BlockType } from '../../../../packages/module-blocks/src/types'

/**
 * The block types the playground's site is built from, and a renderer small enough to live in
 * a Vite plugin.
 *
 * Written to the conventions the constructor checks for (§15 of the module's spec): the root
 * carries `data-wx-block`, every class is prefixed `.b-<slug>`, widths are container queries,
 * and every variable a template reads is declared in its schema. A fixture that trips the
 * lints would leave a yellow warning under every screen being polished here, which is the one
 * thing a playground must not do.
 *
 * What draws them understands interpolation, `@if`, `@foreach` and `@blocks` and nothing else:
 * the point is a preview with the right shape and the right amount of text in it, not a second
 * implementation of Blade.
 */

const HERO_TEMPLATE = `<section class="b-hero" data-wx-block="hero">
    <div class="b-hero__inner">
        @if ($eyebrow)
            <p class="b-hero__eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="b-hero__title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="b-hero__subtitle">{{ $subtitle }}</p>
        @endif
        @if ($button_label)
            <a class="b-hero__button" href="{{ $button_url }}">{{ $button_label }}</a>
        @endif
    </div>
</section>
`

const HERO_STYLES = `.b-hero {
    container-type: inline-size;
    background: linear-gradient(140deg, #10224b, #1f4f9c);
    color: #fff;
}

.b-hero__inner {
    max-width: 60rem;
    margin: 0 auto;
    padding: 4rem 1.25rem;
    text-align: center;
}

.b-hero__eyebrow {
    margin: 0 0 0.75rem;
    font-size: 0.8125rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.72;
}

.b-hero__title {
    margin: 0 0 1rem;
    font-size: 2rem;
    line-height: 1.1;
}

.b-hero__subtitle {
    max-width: 34rem;
    margin: 0 auto 1.75rem;
    font-size: 1.0625rem;
    line-height: 1.6;
    opacity: 0.86;
}

.b-hero__button {
    display: inline-block;
    padding: 0.75rem 1.625rem;
    border-radius: 999px;
    background: #fff;
    color: #10224b;
    font-weight: 600;
    text-decoration: none;
}

@container (min-width: 48rem) {
    .b-hero__inner {
        padding: 6rem 1.25rem;
    }

    .b-hero__title {
        font-size: 3rem;
    }
}
`

const TEXT_TEMPLATE = `<section class="b-text" data-wx-block="text">
    <div class="b-text__inner">
        @if ($title)
            <h2 class="b-text__title">{{ $title }}</h2>
        @endif
        {!! $body !!}
    </div>
</section>
`

const TEXT_STYLES = `.b-text {
    container-type: inline-size;
}

.b-text__inner {
    max-width: 42rem;
    margin: 0 auto;
    padding: 3rem 1.25rem;
    font-size: 1.0625rem;
    line-height: 1.7;
    color: #1c2434;
}

.b-text__title {
    margin: 0 0 1.25rem;
    font-size: 1.75rem;
    line-height: 1.25;
}

.b-text__inner p {
    margin: 0 0 1rem;
}
`

const FEATURES_TEMPLATE = `<section class="b-features" data-wx-block="features">
    <div class="b-features__inner">
        <h2 class="b-features__title">{{ $title }}</h2>

        <div class="b-features__grid">
            @foreach ($items as $item)
                <article class="b-features__card">
                    <h3 class="b-features__name">{{ $item['title'] }}</h3>
                    <p class="b-features__text">{{ $item['text'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
`

const FEATURES_STYLES = `.b-features {
    container-type: inline-size;
    background: #f4f6fa;
}

.b-features__inner {
    max-width: 60rem;
    margin: 0 auto;
    padding: 3rem 1.25rem;
}

.b-features__title {
    margin: 0 0 2rem;
    font-size: 1.75rem;
    text-align: center;
    color: #1c2434;
}

.b-features__grid {
    display: grid;
    gap: 1.25rem;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
}

.b-features__card {
    padding: 1.5rem;
    border-radius: 0.875rem;
    background: #fff;
    box-shadow: 0 1px 2px rgb(16 34 75 / 8%);
}

.b-features__name {
    margin: 0 0 0.5rem;
    font-size: 1.0625rem;
    color: #10224b;
}

.b-features__text {
    margin: 0;
    font-size: 0.9375rem;
    line-height: 1.6;
    color: #55617a;
}
`

const COLUMNS_TEMPLATE = `<section class="b-columns b-columns--{{ $ratio }}" data-wx-block="columns">
    <div class="b-columns__inner">
        @blocks('children')
    </div>
</section>
`

const COLUMNS_STYLES = `.b-columns {
    container-type: inline-size;
}

.b-columns__inner {
    display: grid;
    gap: 1.5rem;
    max-width: 60rem;
    margin: 0 auto;
    padding: 1rem 1.25rem;
}

@container (min-width: 44rem) {
    .b-columns__inner {
        grid-template-columns: 1fr 1fr;
    }

    .b-columns--sidebar .b-columns__inner {
        grid-template-columns: 2fr 1fr;
    }
}
`

const CTA_TEMPLATE = `<section class="b-cta" data-wx-block="cta" style="--b-cta-bg: {{ $background }}">
    <div class="b-cta__inner">
        <div class="b-cta__words">
            <h2 class="b-cta__title">{{ $title }}</h2>
            <p class="b-cta__text">{{ $text }}</p>
        </div>
        <a class="b-cta__button" href="{{ $button_url }}">{{ $button_label }}</a>
    </div>
</section>
`

const CTA_STYLES = `.b-cta {
    container-type: inline-size;
    background: var(--b-cta-bg, #10224b);
    color: #fff;
}

.b-cta__inner {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    justify-content: space-between;
    max-width: 60rem;
    margin: 0 auto;
    padding: 2.5rem 1.25rem;
}

.b-cta__title {
    margin: 0;
    font-size: 1.5rem;
}

.b-cta__text {
    margin: 0.25rem 0 0;
    opacity: 0.8;
}

.b-cta__button {
    padding: 0.75rem 1.5rem;
    border-radius: 999px;
    background: #fff;
    color: #10224b;
    font-weight: 600;
    text-decoration: none;
}
`

const FAQ_TEMPLATE = `<section class="b-faq" data-wx-block="faq">
    <div class="b-faq__inner">
        <h2 class="b-faq__title">{{ $title }}</h2>

        @foreach ($items as $item)
            <details class="b-faq__item">
                <summary class="b-faq__question">{{ $item['question'] }}</summary>
                <p class="b-faq__answer">{{ $item['answer'] }}</p>
            </details>
        @endforeach
    </div>
</section>
`

const FAQ_STYLES = `.b-faq {
    container-type: inline-size;
}

.b-faq__inner {
    max-width: 42rem;
    margin: 0 auto;
    padding: 3rem 1.25rem;
}

.b-faq__title {
    margin: 0 0 1.5rem;
    font-size: 1.75rem;
    color: #1c2434;
}

.b-faq__item {
    padding: 1rem 0;
    border-bottom: 1px solid #e3e7ef;
}

.b-faq__question {
    font-size: 1.0625rem;
    font-weight: 600;
    color: #10224b;
    cursor: pointer;
}

.b-faq__answer {
    margin: 0.75rem 0 0;
    line-height: 1.6;
    color: #55617a;
}
`

const FORM_TEMPLATE = `<section class="b-form" data-wx-block="form">
    <div class="b-form__inner">
        <h2 class="b-form__title">{{ $title }}</h2>
        <p class="b-form__text">{{ $text }}</p>
        <x-webx-form :form="$form" />
    </div>
</section>
`

const FORM_STYLES = `.b-form {
    container-type: inline-size;
    background: #f4f6fa;
}

.b-form__inner {
    max-width: 35rem;
    margin: 0 auto;
    padding: 3rem 1.25rem;
    text-align: center;
}

.b-form__title {
    margin: 0 0 0.5rem;
    font-size: 1.625rem;
    color: #10224b;
}

.b-form__text {
    margin: 0 0 1.5rem;
    color: #55617a;
}
`

const MAP_TEMPLATE = `<section class="b-map" data-wx-block="map" data-address="{{ $address }}" data-zoom="{{ $zoom }}">
    <p class="b-map__address">{{ $address }}</p>
</section>
`

const MAP_STYLES = `.b-map {
    display: grid;
    place-items: center;
    height: 22rem;
    background: #dfe4ee;
}

.b-map__address {
    margin: 0;
    color: #55617a;
}
`

/** The types, in the order the section lists them — which is `group`, then `sort`. */
export const blockTypes: BlockType[] = [
  {
    id: 1,
    slug: 'hero',
    title: 'Обложка',
    description: 'Крупный заголовок, подпись и одна кнопка — первое, что видно на странице.',
    icon: 'layout-panel-top',
    group: 'layout',
    sort: 10,
    allow: null,
    allowed_in: ['root'],
    max_per_entity: 1,
    is_enabled: true,
    draft: version(4, '2026-09-18T09:12:00+00:00', 'Кнопка стала необязательной'),
    published: version(3, '2026-09-10T11:40:00+00:00', 'Первая версия обложки'),
    usage_count: 6,
    thumbnail: null,
    created_at: '2026-08-14T10:00:00+00:00',
    updated_at: '2026-09-18T09:12:00+00:00',
    content: {
      schema: [
        { id: 'eyebrow', type: 'wx-input', label: 'Надзаголовок', localized: true },
        { id: 'title', type: 'wx-input', label: 'Заголовок', localized: true },
        {
          id: 'subtitle',
          type: 'wx-textarea',
          label: 'Подпись',
          localized: true,
          props: { rows: 3 },
        },
        { id: 'button_label', type: 'wx-input', label: 'Кнопка', localized: true },
        {
          id: 'button_url',
          type: 'wx-input',
          label: 'Адрес кнопки',
          props: { placeholder: '/contacts' },
        },
      ],
      template: HERO_TEMPLATE,
      styles: HERO_STYLES,
      script: null,
      sample: {
        eyebrow: 'Студия разработки',
        title: 'Сайты, которые работают на бизнес',
        subtitle:
          'Проектируем, собираем и поддерживаем — от первого экрана до админки, которой пользуются каждый день.',
        button_label: 'Обсудить проект',
        button_url: '/contacts',
      },
    },
  },
  {
    id: 4,
    slug: 'columns',
    title: 'Колонки',
    description: 'Контейнер: внутрь кладутся любые блоки, и они встают рядом.',
    icon: 'columns-3',
    group: 'layout',
    sort: 20,
    allow: ['text', 'features', 'cta'],
    allowed_in: ['root'],
    max_per_entity: null,
    is_enabled: true,
    draft: null,
    published: version(1, '2026-09-05T12:00:00+00:00', null),
    usage_count: 3,
    thumbnail: null,
    created_at: '2026-09-05T11:30:00+00:00',
    updated_at: '2026-09-05T12:00:00+00:00',
    content: {
      schema: [
        {
          id: 'ratio',
          type: 'wx-select',
          label: 'Раскладка',
          props: {
            options: [
              { value: 'equal', label: 'Поровну' },
              { value: 'sidebar', label: 'Две трети и треть' },
            ],
          },
        },
        { id: 'children', type: 'wx-blocks', label: 'Содержимое колонок' },
      ],
      template: COLUMNS_TEMPLATE,
      styles: COLUMNS_STYLES,
      script: null,
      sample: { ratio: 'equal', children: [] },
    },
  },
  {
    id: 2,
    slug: 'text',
    title: 'Текст',
    description: 'Заголовок и абзацы. Самый частый блок на сайте.',
    icon: 'align-left',
    group: 'content',
    sort: 10,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft: null,
    published: version(2, '2026-09-02T08:30:00+00:00', null),
    usage_count: 18,
    thumbnail: null,
    created_at: '2026-08-14T10:05:00+00:00',
    updated_at: '2026-09-02T08:30:00+00:00',
    content: {
      schema: [
        { id: 'title', type: 'wx-input', label: 'Заголовок', localized: true },
        { id: 'body', type: 'wx-rich-text', label: 'Текст', localized: true },
      ],
      template: TEXT_TEMPLATE,
      styles: TEXT_STYLES,
      script: null,
      sample: {
        title: 'Как мы работаем',
        body: '<p>Начинаем с разговора о задаче, а не с макета. Через неделю у вас на руках прототип, который можно показать команде и клиенту.</p><p>Дальше — сборка, тестирование на настоящих устройствах и передача в работу вместе с админкой.</p>',
      },
    },
  },
  {
    id: 3,
    slug: 'features',
    title: 'Преимущества',
    description: 'Сетка карточек: заголовок и короткий текст в каждой.',
    icon: 'grid-2x2',
    group: 'content',
    sort: 20,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft: null,
    published: version(1, '2026-08-28T14:20:00+00:00', null),
    usage_count: 9,
    thumbnail: null,
    created_at: '2026-08-20T09:00:00+00:00',
    updated_at: '2026-08-28T14:20:00+00:00',
    content: {
      schema: [
        { id: 'title', type: 'wx-input', label: 'Заголовок', localized: true },
        {
          id: 'items',
          type: 'wx-repeater',
          label: 'Карточки',
          props: { min: 2, max: 6 },
          children: [
            { id: 'title', type: 'wx-input', label: 'Название', localized: true },
            { id: 'text', type: 'wx-textarea', label: 'Описание', localized: true },
          ],
        },
      ],
      template: FEATURES_TEMPLATE,
      styles: FEATURES_STYLES,
      script: null,
      sample: {
        title: 'Почему с нами удобно',
        items: [
          {
            title: 'Свои разработчики',
            text: 'Никаких подрядчиков на подряде: команда работает с вами от начала до сдачи.',
          },
          {
            title: 'Админка без обучения',
            text: 'Редактор страниц собирает блоки сам, без вёрстки и без правок в коде.',
          },
          {
            title: 'Поддержка по договору',
            text: 'Реагируем за четыре часа в рабочее время и держим сайт обновлённым.',
          },
        ],
      },
    },
  },
  {
    id: 6,
    slug: 'faq',
    title: 'Вопросы и ответы',
    description: 'Список раскрывающихся вопросов.',
    icon: 'circle-help',
    group: 'content',
    sort: 30,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft: null,
    published: version(1, '2026-09-08T09:45:00+00:00', null),
    usage_count: 2,
    thumbnail: null,
    created_at: '2026-09-08T09:00:00+00:00',
    updated_at: '2026-09-08T09:45:00+00:00',
    content: {
      schema: [
        { id: 'title', type: 'wx-input', label: 'Заголовок', localized: true },
        {
          id: 'items',
          type: 'wx-repeater',
          label: 'Вопросы',
          children: [
            { id: 'question', type: 'wx-input', label: 'Вопрос', localized: true },
            { id: 'answer', type: 'wx-textarea', label: 'Ответ', localized: true },
          ],
        },
      ],
      template: FAQ_TEMPLATE,
      styles: FAQ_STYLES,
      script: null,
      sample: {
        title: 'Частые вопросы',
        items: [
          {
            question: 'Сколько занимает разработка?',
            answer: 'Сайт-визитка — три недели, магазин — от двух месяцев.',
          },
          {
            question: 'Можно ли редактировать самому?',
            answer: 'Да, страницы собираются блоками прямо в админке.',
          },
        ],
      },
    },
  },
  {
    id: 8,
    slug: 'map',
    title: 'Карта',
    description: 'Ещё не готов: черновик без опубликованной версии.',
    icon: 'map-pin',
    group: 'content',
    sort: 40,
    allow: null,
    allowed_in: null,
    max_per_entity: 1,
    is_enabled: false,
    draft: version(1, '2026-09-19T18:30:00+00:00', 'Набросок'),
    published: null,
    usage_count: 0,
    thumbnail: null,
    created_at: '2026-09-19T18:20:00+00:00',
    updated_at: '2026-09-19T18:30:00+00:00',
    content: {
      schema: [
        { id: 'address', type: 'wx-input', label: 'Адрес', localized: true },
        { id: 'zoom', type: 'wx-input-number', label: 'Масштаб' },
      ],
      template: MAP_TEMPLATE,
      styles: MAP_STYLES,
      script:
        "export default function (node) {\n    node.querySelector('.b-map__address').textContent = node.dataset.address\n}\n",
      sample: { address: 'Киев, улица Крещатик, 22', zoom: 15 },
    },
  },
  {
    id: 5,
    slug: 'cta',
    title: 'Призыв к действию',
    description: 'Полоса во всю ширину с кнопкой.',
    icon: 'megaphone',
    group: 'marketing',
    sort: 10,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft: version(3, '2026-09-19T16:05:00+00:00', 'Цвет фона вынесен в поле'),
    published: version(2, '2026-09-11T10:15:00+00:00', null),
    usage_count: 4,
    thumbnail: null,
    created_at: '2026-08-30T15:00:00+00:00',
    updated_at: '2026-09-19T16:05:00+00:00',
    content: {
      schema: [
        { id: 'title', type: 'wx-input', label: 'Заголовок', localized: true },
        { id: 'text', type: 'wx-input', label: 'Подпись', localized: true },
        { id: 'button_label', type: 'wx-input', label: 'Кнопка', localized: true },
        { id: 'button_url', type: 'wx-input', label: 'Адрес кнопки' },
        { id: 'background', type: 'wx-color-picker', label: 'Фон' },
      ],
      template: CTA_TEMPLATE,
      styles: CTA_STYLES,
      script: null,
      sample: {
        title: 'Расскажите о проекте',
        text: 'Ответим в течение дня и предложим план работ.',
        button_label: 'Написать нам',
        button_url: '/contacts',
        background: '#10224b',
      },
    },
  },
  {
    id: 7,
    slug: 'form',
    title: 'Форма',
    description: 'Форма из раздела «Заявки», встроенная в страницу.',
    icon: 'inbox',
    group: 'marketing',
    sort: 20,
    allow: null,
    allowed_in: null,
    max_per_entity: 2,
    is_enabled: true,
    draft: null,
    published: version(1, '2026-09-17T13:10:00+00:00', null),
    usage_count: 2,
    thumbnail: null,
    created_at: '2026-09-17T12:40:00+00:00',
    updated_at: '2026-09-17T13:10:00+00:00',
    content: {
      schema: [
        { id: 'title', type: 'wx-input', label: 'Заголовок', localized: true },
        { id: 'text', type: 'wx-input', label: 'Подпись', localized: true },
        {
          id: 'form',
          type: 'wx-select',
          label: 'Форма',
          props: {
            options: [
              { value: 'feedback', label: 'Обратная связь' },
              { value: 'callback', label: 'Заказ звонка' },
            ],
          },
        },
      ],
      template: FORM_TEMPLATE,
      styles: FORM_STYLES,
      script: null,
      sample: {
        title: 'Оставьте заявку',
        text: 'Перезвоним в рабочее время.',
        form: 'feedback',
      },
    },
  },
]

function version(
  number: number,
  createdAt: string,
  comment: string | null,
): NonNullable<BlockType['draft']> {
  return {
    number,
    source: 'panel',
    comment,
    author_id: 1,
    author: 'Анна Ковальчук',
    created_at: createdAt,
  }
}

/** The groups the section offers, in the order the manifest reports them. */
export const blockGroups = ['layout', 'content', 'marketing']

/**
 * A block drawn: the template with its values in it.
 *
 * Deliberately naive — see the note at the top of the file. Unknown directives are left alone
 * rather than stripped, which is what makes a broken template look broken in the preview.
 */
export function renderTemplate(
  template: string,
  values: Record<string, unknown>,
  children: Record<string, string> = {},
): string {
  let html = template

  html = html.replace(
    /@foreach\s*\(\$([\w-]+) as \$(\w+)\)([\s\S]*?)@endforeach/g,
    (_match, key: string, alias: string, body: string) => {
      const items = values[key]

      if (!Array.isArray(items)) {
        return ''
      }

      return items.map((item) => interpolate(body, item as Record<string, unknown>, alias)).join('')
    },
  )

  html = html.replace(
    /@if\s*\(([^)]+)\)([\s\S]*?)@endif/g,
    (_match, condition: string, body: string) => {
      const names = [...condition.matchAll(/\$([\w-]+)/g)].map((match) => match[1])

      return names.every((name) => !isEmpty(values[name])) ? body : ''
    },
  )

  /* `@blocks('children')` prints the blocks held in one field — the drawn ones arrive here. */
  html = html.replace(/@blocks\s*\('([\w-]+)'\)/g, (_match, key: string) => children[key] ?? '')

  html = html.replace(/\{!! \$([\w-]+) !!\}/g, (_match, key: string) => text(values[key]))
  html = html.replace(/\{\{ \$([\w-]+) \}\}/g, (_match, key: string) => escape(text(values[key])))

  return html
}

/** The body of a `@foreach`, where the loop variable is a name rather than a field. */
function interpolate(body: string, item: Record<string, unknown>, alias: string): string {
  const pattern = new RegExp(`\\{\\{ \\$${alias}\\['([\\w-]+)'\\] \\}\\}`, 'g')

  return body.replace(pattern, (_match, key: string) => escape(text(item?.[key])))
}

/**
 * What a value reads as on the site.
 *
 * A localized value is a map of languages, and the site draws one of them — `ru` here, since
 * that is what the fixtures are written in and the preview is a page of the site rather than
 * of the panel.
 */
function text(value: unknown): string {
  if (value === null || value === undefined) {
    return ''
  }

  if (typeof value === 'object' && !Array.isArray(value)) {
    const map = value as Record<string, unknown>

    return text(map.ru ?? map.en ?? Object.values(map)[0])
  }

  return String(value)
}

function isEmpty(value: unknown): boolean {
  return text(value).trim() === ''
}

function escape(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}
