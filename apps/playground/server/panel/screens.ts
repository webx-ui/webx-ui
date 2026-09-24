import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { applyPatch } from '../../../../packages/schema/src/patch'
import type { Patch, ScreenNode } from '../../../../packages/schema/src/types'

/**
 * The screens the fake server hands out, built the way the real one builds them: the module's
 * own JSON with the patches other modules put over it. The words are markers (`trans::…`)
 * and stay that way: the panel resolves them against the dictionary, exactly as it does
 * against a real server.
 *
 * Read off disk rather than copied here, because a copy is a second `pages.form` to keep in
 * step — and the whole point of the playground is to polish the screen the panel actually
 * ships.
 */

/** Each screen: whose description it is, and who writes over it. */
const SCREENS: Record<string, { base: string; patches: string[] }> = {
  'pages.form': {
    base: 'php/packages/module-pages/resources/screens/form.json',
    patches: ['php/packages/module-seo/resources/screens/pages.form.json'],
  },
  'blog.article-form': {
    base: 'php/packages/module-blog/resources/screens/article-form.json',
    // The SEO tab of the article editor is a patch and not a card in the module's own file:
    // the blog leaves a placeholder there, and `module-seo` replaces it when it is installed.
    patches: ['php/packages/module-seo/resources/screens/blog.article-form.json'],
  },
  'blog.category-form': {
    base: 'php/packages/module-blog/resources/screens/category-form.json',
    // The second patch is the project's, as a site would write it: one field of its own in the
    // card the screen keeps for that (`project-fields`). Saved into `extra` by the server.
    patches: [
      'php/packages/module-seo/resources/screens/blog.category-form.json',
      'apps/playground/server/panel/project/blog.category-form.json',
    ],
  },
  'faq.form': { base: 'php/packages/module-faq/resources/screens/form.json', patches: [] },
  'faq.category-form': {
    base: 'php/packages/module-faq/resources/screens/category-form.json',
    patches: [],
  },
  'reviews.form': { base: 'php/packages/module-reviews/resources/screens/form.json', patches: [] },
  'reviews.category-form': {
    base: 'php/packages/module-reviews/resources/screens/category-form.json',
    patches: [],
  },
  'services.form': {
    base: 'php/packages/module-services/resources/screens/form.json',
    // A project's price beside the SEO card, as a site would patch it on: into `extra`.
    patches: [
      'php/packages/module-seo/resources/screens/services.form.json',
      'apps/playground/server/panel/project/services.form.json',
    ],
  },
  'services.category-form': {
    base: 'php/packages/module-services/resources/screens/category-form.json',
    patches: ['php/packages/module-seo/resources/screens/services.category-form.json'],
  },
  'recipes.form': {
    base: 'php/packages/module-recipes/resources/screens/form.json',
    // The author's note is the project's field, as omnivitality has one: into `extra`.
    patches: [
      'php/packages/module-seo/resources/screens/recipes.form.json',
      'apps/playground/server/panel/project/recipes.form.json',
    ],
  },
  'recipes.category-form': {
    base: 'php/packages/module-recipes/resources/screens/category-form.json',
    patches: ['php/packages/module-seo/resources/screens/recipes.category-form.json'],
  },
  'recipes.nutrient-form': {
    base: 'php/packages/module-recipes/resources/screens/nutrient-form.json',
    patches: [],
  },
}

const root = (path: string): string =>
  fileURLToPath(new URL(`../../../../${path}`, import.meta.url))

export function screen(name: string): ScreenNode[] | null {
  const described = SCREENS[name]

  if (described === undefined) {
    return null
  }

  let tree = json<{ root: ScreenNode[] }>(described.base).root

  for (const patch of described.patches) {
    tree = applyPatch(tree, json<Patch>(patch)).root
  }

  return tree
}

/** The names the manifest reports — a screen the panel asks for and does not get is an error. */
export const screenNames = Object.keys(SCREENS)

function json<T>(path: string): T {
  return JSON.parse(readFileSync(root(path), 'utf8')) as T
}
