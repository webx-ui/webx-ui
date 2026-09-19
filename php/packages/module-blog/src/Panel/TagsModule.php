<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

/**
 * Tags: entered from the article's own form by the hundred, and raked through here — renamed,
 * merged, opened to the index or kept out of it (§2.8, §12).
 *
 * The permission is the one the rubrics use. Somebody who may rename a rubric may rename a tag:
 * it is the same job, and two permissions would be two places to forget.
 */
final class TagsModule extends BlogModule
{
    public function id(): string
    {
        return 'tags';
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.tags');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function order(): int
    {
        return 320;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['blog.taxonomy.manage'];
    }
}
