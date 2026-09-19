<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

/**
 * Where articles are written and published.
 *
 * Two permissions, the panel's usual pair, named after the blog rather than after this section:
 * `blog.articles.view` opens the list, `blog.articles.manage` writes (§14). Rubrics and tags
 * share `blog.taxonomy.manage` between them, because somebody who may rename a rubric may
 * rename a tag — they are the same job.
 */
final class ArticlesModule extends BlogModule
{
    public function id(): string
    {
        return 'articles';
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.articles');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 300;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['blog.articles.view', 'blog.articles.manage'];
    }
}
