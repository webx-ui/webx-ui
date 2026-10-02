<?php

declare(strict_types=1);

namespace WebxUi\Audit\Content;

/**
 * A text-bearing field of a record, in one locale.
 *
 * Structured values — a block tree, a repeater — arrive as JSON with unescaped slashes, so the
 * finder reads one kind of string and `replace()` gets back the same JSON with the host swapped.
 * `published` is false for a field that only lives in a draft, even on a published record.
 */
final readonly class ContentField
{
    public function __construct(
        public string $name,
        public string $value,
        public ?string $locale = null,
        public ?bool $published = null,
    ) {}

    /** Structured values as the finder reads them, and as `replace()` expects them back. */
    public static function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
