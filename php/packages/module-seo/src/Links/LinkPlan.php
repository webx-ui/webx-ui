<?php

declare(strict_types=1);

namespace WebxUi\Seo\Links;

use WebxUi\Seo\Targets\Binding;
use WebxUi\Seo\Targets\UrlTarget;

/**
 * A donor's links checked and resolved, before anything is written.
 *
 * Problems are per line and come in two levels: an `error` drops that line and keeps the rest, a
 * `warning` keeps it and says what was changed (an address replaced by its redirect target).
 */
final class LinkPlan
{
    /**
     * @param  list<array{target: UrlTarget, anchor: string, line: int}>  $items
     * @param  list<array{line: int, field: string, code: string, level: string, message: string}>  $problems
     */
    public function __construct(
        public readonly ?Binding $donor,
        public readonly array $items = [],
        public readonly array $problems = [],
    ) {}

    public function hasErrors(): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem['level'] === LinkWriter::ERROR) {
                return true;
            }
        }

        return false;
    }
}
