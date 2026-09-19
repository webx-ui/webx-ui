import type { AdminModule } from '@webx-ui/module-admin'
import ArticleAddress from './ArticleAddress.vue'
import ArticleAuthor from './ArticleAuthor.vue'
import ArticleEditorPage from './ArticleEditorPage.vue'
import ArticleHistory from './ArticleHistory.vue'
import ArticleRelated from './ArticleRelated.vue'
import ArticleRubrics from './ArticleRubrics.vue'
import ArticleTags from './ArticleTags.vue'
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
        {
          path: `${path}/articles/:id(\\d+)`,
          name: 'webx.blog.articles.edit',
          component: ArticleEditorPage,
          props: { base: path },
        },
      ],
      /*
       * The parts of `blog.article-form` that only this module can draw.
       *
       * The editor is a described screen so that a module can add a tab to it with a patch, and
       * the price of that is that everything on it has to be a node type. Five of these are
       * fields — the value is ids, and the control over them is a list that can be dragged or a
       * box that makes a tag — and two only draw. Each reads the article from the editor above
       * it rather than from the description, because a screen is a description and not a
       * binding.
       */
      types: {
        'wx-article-address': { component: ArticleAddress, kind: 'display' },
        'wx-article-author': { component: ArticleAuthor, kind: 'field' },
        'wx-article-rubrics': { component: ArticleRubrics, kind: 'field' },
        'wx-article-tags': { component: ArticleTags, kind: 'field' },
        'wx-article-related': { component: ArticleRelated, kind: 'field' },
        'wx-article-history': { component: ArticleHistory, kind: 'display' },
      },
    },
  ]
}
