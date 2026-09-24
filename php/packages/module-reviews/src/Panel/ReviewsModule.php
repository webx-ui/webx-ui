<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

/**
 * Where reviews are written, ordered and published (§4.6).
 *
 * The panel's usual pair: `reviews.view` opens the list, `reviews.manage` writes — including the
 * order, which is what a reader of a block of reviews sees first.
 */
final class ReviewsModule extends ReviewsGroup
{
    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'reviews';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-reviews::module.reviews');
    }

    public function icon(): string
    {
        return 'list';
    }

    public function order(): int
    {
        return 600;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['reviews.view', 'reviews.manage'];
    }
}
