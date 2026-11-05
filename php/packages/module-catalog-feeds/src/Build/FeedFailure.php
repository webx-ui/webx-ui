<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use RuntimeException;

/**
 * A build that cannot come out right, whoever tries again: the journal says why and the file served
 * stays. What may pass by itself, a database gone for a moment, is any other exception, and the
 * queue repeats it.
 */
final class FeedFailure extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $replace
     */
    private function __construct(string $message, public readonly string $key, public readonly array $replace)
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function of(string $key, array $replace = []): self
    {
        return new self((string) __('webx-catalog-feeds::errors.'.$key, $replace), $key, $replace);
    }
}
