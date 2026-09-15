<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Exceptions;

use Throwable;
use WebxUi\Blocks\BlockType;

/**
 * The version failed its render on the sample values, so it stays a draft.
 *
 * Carries the template's line so the panel can put the error under the editor at the right
 * place: the compiled file keeps the template's line numbers, which is the one thing Blade
 * promises about its output. Not `$line` — that is the exception's own, and it points here.
 */
final class BlockNotPublishable extends BlocksException
{
    public function __construct(
        public readonly string $slug,
        public readonly int $version,
        public readonly string $reason,
        public readonly ?int $templateLine,
        ?Throwable $previous = null,
    ) {
        $where = $templateLine === null ? '' : " on line {$templateLine}";

        parent::__construct("Block '{$slug}' v{$version} cannot be published: {$reason}{$where}.", 0, $previous);
    }

    public static function because(BlockType $type, Throwable $failure, ?int $templateLine): self
    {
        return new self($type->slug, $type->version, $failure->getMessage(), $templateLine, $failure);
    }
}
