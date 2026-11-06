<?php

declare(strict_types=1);

namespace WebxUi\Templates;

use Mustache\Template as MustacheTemplate;

/**
 * A template read and checked once ({@see Template::compile()}): a feed renders it for every
 * product, and reading it again each time is what a hundred thousand products would notice.
 *
 * @phpstan-import-type Values from Template
 */
final readonly class Compiled
{
    public function __construct(private MustacheTemplate $template) {}

    /**
     * @param  Values  $values
     */
    public function render(array $values): string
    {
        return $this->template->render(Template::nest($values));
    }
}
