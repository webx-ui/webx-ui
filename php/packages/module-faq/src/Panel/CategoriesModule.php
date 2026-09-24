<?php

declare(strict_types=1);

namespace WebxUi\Faq\Panel;

use WebxUi\Faq\Models\FaqCategory;

/**
 * The categories of the FAQ: flat, ordered by hand, several per question — the panel's shared
 * category screens with this module's words (`FaqCategory::categoryKind()`), and no address.
 *
 * Its own permission, for the reason every module's categories have one: the buttons of every
 * FAQ filter on the site are a different job from answering a question.
 */
final class CategoriesModule extends FaqGroup
{
    public function id(): string
    {
        return 'faq-categories';
    }

    public function title(): string
    {
        return (string) __('webx-faq::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 510;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [FaqCategory::MANAGE];
    }
}
