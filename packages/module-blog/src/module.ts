import type { AdminModule } from '@webx-ui/module-admin'
import ArticlesPage from './ArticlesPage.vue'

export interface BlogOptions {
  /** Where the blog lives inside the panel. The three sections sit under it. */
  path?: string
}

/**
 * The blog as sections of the panel: articles, and — once they are written — rubrics and tags.
 *
 * Three modules rather than one, because the navigation is one entry per module and the blog
 * wants three of them; the server puts all three in the `blog` group, which is what draws them
 * under one heading. Each is returned separately so a panel can leave one out.
 *
 * A section whose server half is not installed never appears — the entry is built from the
 * manifest — and so does one whose front end is missing. That is what makes it safe to return
 * the sections that do not have their screens yet: they are declared on the server and silently
 * skipped here until the screen arrives.
 */
export function blog(options: BlogOptions = {}): AdminModule[] {
  const path = options.path ?? '/blog'

  return [
    {
      id: 'articles',
      path: `${path}/articles`,
      routes: [
        // The section's own path travels as a prop, so a panel that mounted the blog somewhere
        // else still links between its screens correctly.
        {
          path: `${path}/articles`,
          name: 'webx.blog.articles',
          component: ArticlesPage,
          props: { base: path },
        },
      ],
    },
  ]
}
