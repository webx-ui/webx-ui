<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Blocks\Exceptions\BlocksException;

/**
 * An import asked to replace what it may not (BLOCK_SCHEMAS §4): every type at once, or a slug
 * the documents do not carry — a typo must not pass in silence. Refused before anything is written.
 */
final class ReplaceRefused extends BlocksException
{
    public const ALL = 'all';

    public const UNKNOWN = 'unknown';

    /**
     * @param  self::ALL|self::UNKNOWN  $reason
     */
    public function __construct(public readonly string $reason, public readonly string $slug = '')
    {
        parent::__construct($reason === self::ALL
            ? 'Name each type to replace: there is no replacing every type at once.'
            : "{$slug} is not among the types read, so there is nothing to replace it with.");
    }

    /**
     * @param  list<string>  $replace
     * @param  list<string>  $slugs  The slugs the documents carry.
     *
     * @throws self
     */
    public static function check(array $replace, array $slugs): void
    {
        foreach ($replace as $slug) {
            if (in_array(strtolower(trim($slug)), ['', '*', 'all'], true)) {
                throw new self(self::ALL);
            }
        }

        foreach ($replace as $slug) {
            if (! in_array($slug, $slugs, true)) {
                throw new self(self::UNKNOWN, $slug);
            }
        }
    }
}
