<?php

declare(strict_types=1);

namespace WebxUi\Faq\Panel;

/**
 * Where questions are written, ordered and published (§4.5).
 *
 * The panel's usual pair: `faq.view` opens the list, `faq.manage` writes — including the order,
 * which is what a reader of the FAQ sees first.
 */
final class QuestionsModule extends FaqGroup
{
    /** The id in the panel — and the name `webx:setup` and `webx:blocks:offered --module` know it by. */
    public const ID = 'faq';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-faq::module.questions');
    }

    public function icon(): string
    {
        return 'list';
    }

    public function order(): int
    {
        return 500;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['faq.view', 'faq.manage'];
    }
}
