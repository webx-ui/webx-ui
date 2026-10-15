<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Exceptions;

use RuntimeException;

/**
 * An operation the rules of §3 do not allow, said in words the caller can act on.
 *
 * The key is kept next to the message so that a test, the panel and the MCP tools can tell the
 * refusals apart without parsing text in whatever language the site speaks.
 */
final class TaskRefused extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function because(string $reason, array $replace = []): self
    {
        return new self($reason, (string) __("webx-tasks::errors.{$reason}", $replace));
    }
}
