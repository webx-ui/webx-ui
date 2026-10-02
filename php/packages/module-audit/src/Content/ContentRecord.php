<?php

declare(strict_types=1);

namespace WebxUi\Audit\Content;

/**
 * One record of a content source: what the panel calls it, whether the site shows it, and where
 * it is edited. `subject` is the source's own handle on it — usually the model — handed back to
 * `fields()` and `replace()` untouched.
 */
final readonly class ContentRecord
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $published,
        public ?string $editUrl = null,
        public mixed $subject = null,
    ) {}
}
