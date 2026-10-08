<?php

declare(strict_types=1);

namespace WebxUi\Media\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Media\Images\OrphanThumbnails;

/**
 * Previews of files the library no longer has: space on the disk nobody will ever ask for.
 * Closed by {@see PruneThumbnailsFix}, or `webx:media:prune-thumbs`.
 */
final class OrphanThumbnailsCheck extends ModuleCheck
{
    public const ID = 'media.orphan_thumbs';

    protected const GROUP = 'media';

    protected const SEVERITY = Severity::NOTICE;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-media';

    public function __construct(private readonly OrphanThumbnails $orphans) {}

    public function run(AuditContext $context): iterable
    {
        $found = $this->orphans->find();

        if ($found === []) {
            return;
        }

        yield $this->found('orphan-thumbs', ['count' => count($found)], key: 'thumbs', table: [
            'columns' => [Finding::column('value')],
            'rows' => array_map(
                static fn (array $orphan): array => ['value' => $orphan['disk'].':'.$orphan['path']],
                array_slice($found, 0, 20),
            ),
        ]);
    }
}
