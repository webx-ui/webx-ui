<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

/**
 * The sections of the blog: flat, ordered by hand, several per article (§2.4, §2.6).
 */
final class RubricsModule extends BlogModule
{
    public function id(): string
    {
        return 'rubrics';
    }

    public function title(): string
    {
        return (string) __('webx-blog::module.rubrics');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 310;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['blog.taxonomy.manage'];
    }
}
