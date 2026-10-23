<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

/**
 * A patch that cannot be applied as written (COMPOSITIONS §3.6): the operation is skipped and
 * this is said instead — in the log when compiling, by `webx:theme:check`, and as a failure in a
 * package's or a theme's own test. A note is a warning about something that did apply.
 */
final readonly class Finding
{
    public function __construct(
        public string $file,
        public string $operation,
        public string $message,
        public bool $note = false,
    ) {}

    public function __toString(): string
    {
        return "{$this->file}: {$this->operation} — {$this->message}";
    }

    /** @return array{file: string, operation: string, message: string, note: bool} */
    public function toArray(): array
    {
        return ['file' => $this->file, 'operation' => $this->operation, 'message' => $this->message, 'note' => $this->note];
    }

    /** @param array{file: string, operation: string, message: string, note?: bool} $finding */
    public static function fromArray(array $finding): self
    {
        return new self($finding['file'], $finding['operation'], $finding['message'], $finding['note'] ?? false);
    }
}
