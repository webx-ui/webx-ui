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
 * Both stand in for the composer package's files, and stay stand-ins: the block the module offers
 * (`resources/blocks/recipes.json`) is one `@include` of the catalog partial the index and the
 * category page share, and this playground's little Blade has neither `@include` nor a paginator.
 * So the block below inlines that partial's markup — `.wx-recipes` and its parts, as the composer
 * package prints them — inside the block's own root, which is what the constructor's lints want.
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

/* The cards of the page (similar recipes). The block has its own rules, under its own root. */
const CARD_STYLES = `
  .r-grid { display: grid; grid-template-columns: repeat(var(--r-columns, 3), minmax(0, 1fr)); gap: 20px; }
  .r-card { display: flex; flex-direction: column; gap: 6px; color: inherit; text-decoration: none; }
  .r-card__cover { display: block; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden; background: #eef0f4; }
  .r-card__cover img { width: 100%; height: 100%; min-width: 0; object-fit: cover; display: block; }
  .r-card small { color: #6b7280; }
  .r-chip { display: inline-block; padding: 2px 10px; border-radius: 999px; background: #eef4ec; color: #2d6a3e; font-size: 14px; text-decoration: none; }
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
 *
 * Inside the root is the markup of `partials/catalog.blade.php` as RC3 wrote it: `.wx-recipes`
 * with its filter, grid, pages and the showcase's link to every recipe.
 */
const BLOCK_TEMPLATE = `<section class="b-recipes" data-wx-block="recipes">
    @if ($title)
        <h2 class="b-recipes__title">{{ $title }}</h2>
    @endif
    <div class="wx-recipes" style="--wx-recipes-columns: {{ $columns ?: 3 }}">
        @if ($mode === 'catalog' && $recipes['groups'])
            <ul class="wx-recipes__filter">
                <li><a class="wx-recipes__chip" href="?" aria-current="true">Все</a></li>
                @foreach ($recipes['groups'] as $group)
                    <li><a class="wx-recipes__chip" href="?nutrient={{ $group['id'] }}">{{ $group['title'] }}</a></li>
                @endforeach
            </ul>
        @endif
        <ul class="wx-recipes__grid">
            @foreach ($recipes['items'] as $recipe)
                @if ($mode !== 'catalog' || $loop->iteration <= ($per_page ?: 24))
                    <li class="wx-recipes__card">
                        <a class="wx-recipes__link" href="{{ $recipe['url'] }}">
                            @if ($recipe['cover'])
                                <img class="wx-recipes__cover" src="{{ $recipe['cover']['url'] }}" alt="" loading="lazy">
                            @endif
                            <span class="wx-recipes__name">{{ $recipe['title'] }}</span>
                        </a>
                        @if ($recipe['time'])
                            <span class="wx-recipes__time">{{ $recipe['time'] }}</span>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
        @if ($mode === 'catalog')
            <nav>
                <ul class="wx-recipes__pages">
                    @foreach ($recipes['items'] as $recipe)
                        @if (intval($loop->index / ($per_page ?: 24)) * ($per_page ?: 24) === $loop->index)
                            <li><a href="?page={{ intval($loop->index / ($per_page ?: 24)) + 1 }}" {{ $loop->first ? 'aria-current=page' : '' }}>{{ intval($loop->index / ($per_page ?: 24)) + 1 }}</a></li>
                        @endif
                    @endforeach
                </ul>
            </nav>
        @else
            <p><a class="wx-recipes__more" href="/recipes">Все рецепты →</a></p>
        @endif
    </div>
</section>`

const BLOCK_STYLES = `.b-recipes {
    container-type: inline-size;
    max-width: 1160px;
    margin: 0 auto;
    padding: 32px 24px;
}

.b-recipes .wx-recipes__filter,
.b-recipes .wx-recipes__grid,
.b-recipes .wx-recipes__pages {
    margin: 0;
    padding: 0;
    list-style: none;
}

.b-recipes .wx-recipes__filter {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 20px;
}

.b-recipes .wx-recipes__chip {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    background: #eef4ec;
    color: #2d6a3e;
    font-size: 14px;
    text-decoration: none;
}

.b-recipes .wx-recipes__chip[aria-current] {
    background: #2d6a3e;
    color: #fff;
}

.b-recipes .wx-recipes__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
}

.b-recipes .wx-recipes__link {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: inherit;
    text-decoration: none;
    font-weight: 600;
}

.b-recipes .wx-recipes__cover {
    display: block;
    width: 100%;
    min-width: 0;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: 10px;
    background: #eef0f4;
}

.b-recipes .wx-recipes__time {
    color: #6b7280;
    font-size: 13px;
}

.b-recipes .wx-recipes__pages {
    display: flex;
    gap: 6px;
    margin-top: 24px;
}

.b-recipes .wx-recipes__pages a {
    display: inline-block;
    min-width: 36px;
    padding: 6px 10px;
    border: 1px solid #e6e8ee;
    border-radius: 8px;
    text-align: center;
    text-decoration: none;
    color: inherit;
}

.b-recipes .wx-recipes__pages a[aria-current] {
    background: #1f2430;
    border-color: #1f2430;
    color: #fff;
}

@container (min-width: 30rem) {
    .b-recipes .wx-recipes__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@container (min-width: 46rem) {
    .b-recipes .wx-recipes__grid {
        grid-template-columns: repeat(var(--wx-recipes-columns, 3), minmax(0, 1fr));
    }
}
`

/* The ids, the modes and the sample of the block the composer package offers. */
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
  sample: {
    title: 'Recipes',
    recipes: { categories: [], limit: 3, filter: false, markup: null, related: null },
    mode: 'showcase',
    columns: 3,
  },
}
