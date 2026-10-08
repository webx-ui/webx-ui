<?php

declare(strict_types=1);

namespace WebxUi\Media\Audit;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Media\Images\OrphanThumbnails;

/**
 * `media.prune-thumbs`: delete the preview folders of files that are gone — what
 * `webx:media:prune-thumbs` does, found the same way ({@see OrphanThumbnails}).
 */
final readonly class PruneThumbnailsFix implements AuditFix
{
    public const ID = 'media.prune-thumbs';

    public function __construct(private OrphanThumbnails $orphans) {}

    public function textNamespace(): string
    {
        return 'webx-media';
    }

    public function id(): string
    {
        return self::ID;
    }

    public function fixes(): array
    {
        return [OrphanThumbnailsCheck::ID];
    }

    public function available(Finding $finding): bool
    {
        return $this->orphans->find() !== [];
    }

    public function preview(Finding $finding): FixPreview
    {
        return new FixPreview(array_map(
            static fn (array $orphan): array => ['label' => $orphan['disk'].':'.$orphan['path'], 'before' => null, 'after' => null],
            $this->orphans->find(),
        ));
    }

    public function apply(Finding $finding): void
    {
        $this->orphans->sweep();
    }
}
