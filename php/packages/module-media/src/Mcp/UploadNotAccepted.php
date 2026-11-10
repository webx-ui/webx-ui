<?php

declare(strict_types=1);

namespace WebxUi\Media\Mcp;

use WebxUi\Mcp\Exceptions\ToolFailure;

/**
 * The library's rules refused a file: what they said, and which of them it was — for an answer
 * that is not a tool result (the upload link's endpoint), where a program reads a code.
 */
final class UploadNotAccepted extends ToolFailure
{
    public const TYPE = 'type_not_allowed';

    public const SIZE = 'too_large';

    public const OTHER = 'not_accepted';

    public function __construct(string $message, public readonly string $reason = self::OTHER)
    {
        parent::__construct($message);
    }
}
