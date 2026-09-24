<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

use WebxUi\Reviews\Models\ReviewCategory;

/**
 * The categories of reviews: flat, ordered by hand, several per review — the panel's shared
 * category screens with this module's words (`ReviewCategory::categoryKind()`), and no address.
 *
 * Its own permission, for the reason every module's categories have one: the buttons of every
 * filter of reviews on the site are a different job from writing a review down.
 *
 * The id is `review-categories`, so the tools an agent gets for them are `review_categories_*`.
 */
final class CategoriesModule extends ReviewsGroup
{
    public function id(): string
    {
        return 'review-categories';
    }

    public function title(): string
    {
        return (string) __('webx-reviews::module.categories');
    }

    public function icon(): string
    {
        return 'folder';
    }

    public function order(): int
    {
        return 610;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [ReviewCategory::MANAGE];
    }
}
