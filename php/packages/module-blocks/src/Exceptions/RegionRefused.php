<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Exceptions;

/**
 * A write to a region that did not happen, and why — in a shape both doors can say: the panel
 * answers with the status and the lines under the blocks field, an agent reads the sentence.
 */
final class RegionRefused extends BlocksException
{
    /**
     * @param  list<string>  $errors  Lines for the blocks field; empty when the message is all.
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $errors = [],
        public readonly ?string $revision = null,
    ) {
        parent::__construct($message);
    }

    /** Somebody changed the region since it was read (409, like a page's revision). */
    public static function conflict(string $revision): self
    {
        return new self((string) __('webx-blocks::regions.conflict'), 409, [], $revision);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function invalid(string $message, array $errors): self
    {
        return new self($message, 422, $errors);
    }

    public static function status(string $message, int $status): self
    {
        return new self($message, $status);
    }

    /** One sentence with every line in it, for an agent. */
    public function sentence(): string
    {
        return $this->errors === [] ? $this->getMessage() : $this->getMessage().' '.implode(' ', $this->errors);
    }
}
