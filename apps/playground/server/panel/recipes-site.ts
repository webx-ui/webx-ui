import type { BlockContent } from '../../../../packages/module-blocks/src/types'
import type { recipePage } from './recipes'

/**
 * The site's half of the recipes, as the playground draws it: the page of one recipe (§5.5) and
 * the "Recipes" block in both of its modes (§5.8).
 *
 * The page has no blocks — its structure is the module's view — so it is drawn here in code, in
 * the order of the parts of `recipe.blade.php`: gallery, title and lead, facts, ingredients,
 * method, nutrition, services, similar recipes. The block is a template like any other and goes
 * through `renderTemplate()`, the one path the constructor and the page preview share.
 *
 * Both stand in for the composer package's files until the two halves are on one branch: the
 * block's template in `resources/blocks/recipes.json` is written there, beside the Blade views,
 * and may lean on partials this playground's little Blade cannot include.
 */

const esc = (value: string): string =>
  value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

const NUTRITION_WORDS: Record<string, string> = {
  calories: 'Калорийность',
  protein: 'Белки',
  fat: 'Жиры',
  carbohydrates: 'Углеводы',
  fiber: 'Клетчатка',
}

type Page = ReturnType<typeof recipePage>

function cards(items: Page['similar']): string {
  return items
    .map(
      (item) => `<a class="r-card" href="${esc(item.url)}">
        <span class="r-card__cover">${item.cover ? `<img src="${esc(item.cover.url)}" alt="">` : ''}</span>
        <strong>${esc(item.title)}</strong>
        ${item.time ? `<small>${esc(item.time)}</small>` : ''}
      </a>`,
    )
    .join('')
}

/** One recipe's page, from its draft. Every part that has nothing to say is left out. */
export function drawRecipePage(page: Page): string {
  const [first, ...rest] = page.gallery

  const gallery = first
    ? `<div class="r-gallery">
        <img class="r-gallery__main" src="${esc(first.url)}" alt="${esc(first.alt)}">
        ${rest.length > 0 ? `<div class="r-gallery__strip">${rest.map((one) => `<img src="${esc(one.thumb)}" alt="${esc(one.alt)}">`).join('')}</div>` : ''}
      </div>`
    : ''

  const facts = [
    page.time ? `<li>⏱ ${esc(page.time)}</li>` : '',
    page.servings ? `<li>Порций: ${page.servings}</li>` : '',
    ...page.categoryLinks.map((one) => `<li><a href="${esc(one.url)}">${esc(one.title)}</a></li>`),
  ].join('')

  const chips = page.nutrients
    .map((one) => `<span class="r-chip">${esc(one.title)}</span>`)
    .join('')

  const nutrition =
    page.nutrition.length > 0
      ? `<section><h2>Пищевая ценность</h2><table class="r-nutrition">${page.nutrition
          .map(
            (one) =>
              `<tr><th>${NUTRITION_WORDS[one.key] ?? one.key}</th><td>${esc(one.value)}</td></tr>`,
          )
          .join('')}</table></section>`
      : ''

  const services =
    page.services.length > 0
      ? `<section><h2>Услуги</h2><div class="r-services">${page.services
          .map(
            (one) =>
              `<a class="r-service" href="${esc(one.url)}"><strong>${esc(one.title)}</strong><span>${esc(one.lead)}</span></a>`,
          )
          .join('')}</div></section>`
      : ''

  return `<article class="site-wrap r-page">
    ${gallery}
    <h1>${esc(page.title)}</h1>
    ${page.lead ? `<p class="r-lead">${esc(page.lead)}</p>` : ''}
    ${facts ? `<ul class="r-facts">${facts}</ul>` : ''}
    ${chips ? `<div class="r-chips">${chips}</div>` : ''}
    ${page.ingredients ? `<section><h2>Ингредиенты</h2>${page.ingredients}</section>` : ''}
    ${page.method ? `<section><h2>Приготовление</h2>${page.method}</section>` : ''}
    ${page.note ? `<aside class="r-note">${esc(page.note)}</aside>` : ''}
    ${nutrition}
    ${services}
    ${page.similar.length > 0 ? `<section><h2>Похожие рецепты</h2><div class="r-grid">${cards(page.similar)}</div></section>` : ''}
  </article>`
}

/* The page and the cards of the block share the card, so they share its rules too. */
const CARD_STYLES = `
  .r-grid { display: grid; grid-template-columns: repeat(var(--r-columns, 3), minmax(0, 1fr)); gap: 20px; }
  .r-card { display: flex; flex-direction: column; gap: 6px; color: inherit; text-decoration: none; }
  .r-card__cover { display: block; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden; background: #eef0f4; }
  .r-card__cover img { width: 100%; height: 100%; min-width: 0; object-fit: cover; display: block; }
  .r-card small { color: #6b7280; }
  .r-chip { display: inline-block; padding: 2px 10px; border-radius: 999px; background: #eef4ec; color: #2d6a3e; font-size: 14px; text-decoration: none; }
  .r-chip.is-active { background: #2d6a3e; color: #fff; }
  @media (max-width: 720px) { .r-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 460px) { .r-grid { grid-template-columns: minmax(0, 1fr); } }
`

export const RECIPE_PAGE_STYLES = `${CARD_STYLES}
  .r-page { padding-block: 32px; max-width: 860px; }
  .r-page section { margin-top: 32px; }
  .r-gallery__main { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 14px; display: block; }
  .r-gallery__strip { display: flex; gap: 8px; margin-top: 8px; overflow-x: auto; }
  .r-gallery__strip img { width: 120px; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 8px; flex: none; }
  .r-page h1 { margin-top: 24px; }
  .r-lead { font-size: 19px; color: #4b5263; }
  .r-facts { display: flex; flex-wrap: wrap; gap: 8px 20px; padding: 0; list-style: none; color: #4b5263; }
  .r-chips { display: flex; flex-wrap: wrap; gap: 6px; }
  .r-note { margin-top: 24px; padding: 16px 20px; border-left: 4px solid #2d6a3e; background: #f5f8f4; }
  .r-nutrition { border-collapse: collapse; min-width: 280px; }
  .r-nutrition th, .r-nutrition td { padding: 6px 12px 6px 0; border-bottom: 1px solid #e6e8ee; text-align: left; }
  .r-nutrition th { font-weight: 500; color: #4b5263; }
  .r-services { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
  .r-service { display: flex; flex-direction: column; gap: 4px; padding: 16px; border: 1px solid #e6e8ee; border-radius: 10px; color: inherit; text-decoration: none; }
  .r-service span { color: #6b7280; font-size: 14px; }
`

/*
 * The block. `mode` untouched is the showcase (a block keeps only what was touched —
 * `ResolvesMissing`), so every condition below reads "catalog" and falls through to the showcase.
 * The catalog pages by `$per_page` with its own loop arithmetic: this little Blade has no
 * paginator, and the page the preview draws is always the first.
 */
const BLOCK_TEMPLATE = `<section class="b-recipes site-wrap" style="--r-columns: {{ $columns ?: 3 }}">
    @if ($title)
        <h2 class="b-recipes__title">{{ $title }}</h2>
    @endif
    @if ($mode === 'catalog' && $recipes['groups'])
        <nav class="b-recipes__filter">
            <a class="r-chip is-active" href="?">Все</a>
            @foreach ($recipes['groups'] as $group)
                <a class="r-chip" href="?nutrient={{ $group['id'] }}">{{ $group['title'] }}</a>
            @endforeach
        </nav>
    @endif
    <div class="r-grid">
        @foreach ($recipes['items'] as $recipe)
            @if ($mode !== 'catalog' || $loop->iteration <= ($per_page ?: 24))
                <a class="r-card" href="{{ $recipe['url'] }}">
                    <span class="r-card__cover">
                        @if ($recipe['cover'])
                            <img src="{{ $recipe['cover']['url'] }}" alt="" loading="lazy">
                        @endif
                    </span>
                    <strong>{{ $recipe['title'] }}</strong>
                    @if ($recipe['time'])
                        <small>{{ $recipe['time'] }}</small>
                    @endif
                </a>
            @endif
        @endforeach
    </div>
    @if ($mode === 'catalog')
        <nav class="b-recipes__pages">
            @foreach ($recipes['items'] as $recipe)
                @if (intval($loop->index / ($per_page ?: 24)) * ($per_page ?: 24) === $loop->index)
                    <a class="b-recipes__page {{ $loop->first ? 'is-current' : '' }}" href="?page={{ intval($loop->index / ($per_page ?: 24)) + 1 }}">{{ intval($loop->index / ($per_page ?: 24)) + 1 }}</a>
                @endif
            @endforeach
        </nav>
    @else
        <p class="b-recipes__more"><a href="/recipes">{{ $all_label ?: 'Все рецепты' }} →</a></p>
    @endif
</section>`

const BLOCK_STYLES = `${CARD_STYLES}
  .b-recipes { padding-block: 32px; }
  .b-recipes__filter { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 20px; }
  .b-recipes__pages { display: flex; gap: 6px; margin-top: 24px; }
  .b-recipes__page { min-width: 36px; padding: 6px 10px; border: 1px solid #e6e8ee; border-radius: 8px; text-align: center; text-decoration: none; color: inherit; }
  .b-recipes__page.is-current { background: #1f2430; border-color: #1f2430; color: #fff; }
  .b-recipes__more { margin-top: 20px; }
`

export const RECIPES_BLOCK: BlockContent = {
  schema: [
    { id: 'title', type: 'wx-input', label: 'Heading', localized: true },
    { id: 'recipes', type: 'wx-collection', label: 'Recipes', props: { source: 'recipes' } },
    {
      id: 'mode',
      type: 'wx-segmented',
      label: 'Mode',
      props: {
        options: [
          { value: 'showcase', label: 'Showcase' },
          { value: 'catalog', label: 'Catalog' },
        ],
      },
    },
    {
      id: 'per_page',
      type: 'wx-input-number',
      label: 'Per page',
      help: 'Empty is 24.',
      props: { min: 1, max: 96 },
      visible: { when: 'mode', is: 'catalog' },
    },
    {
      id: 'all_label',
      type: 'wx-input',
      label: 'The link to every recipe',
      help: 'Empty is “All recipes”.',
      localized: true,
      visible: { when: 'mode', not: 'catalog' },
    },
    {
      id: 'columns',
      type: 'wx-input-number',
      label: 'Columns',
      help: 'Empty is 3.',
      props: { min: 1, max: 6 },
    },
  ],
  template: BLOCK_TEMPLATE,
  styles: BLOCK_STYLES,
  script: null,
  sample: { title: { ru: 'Рецепты', en: 'Recipes' }, recipes: { limit: 3 } },
}
