<?php

declare(strict_types=1);

namespace WebxUi\Media\Audit;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Media\Models\MediaFile;

/**
 * An image of the library heavier than a page can afford (`media_kb`, 1 MB): a photo straight
 * from the camera, put on a page as it is. The crawl finds it once it is on a page
 * (`images.heavy`); the library has it before that, drafts and unused files included.
 *
 * One finding with the heaviest twenty in its table: the list is a to-do, not twenty problems.
 */
final class HeavyImages extends ModuleCheck
{
    protected const ID = 'media.heavy';

    protected const GROUP = 'media';

    protected const SEVERITY = Severity::NOTICE;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-media';

    public function run(AuditContext $context): iterable
    {
        $limit = $context->threshold('media_kb', 1024) * 1024;
        $query = MediaFile::query()->where('mime', 'like', 'image/%')->where('mime', '!=', 'image/svg+xml')->where('size', '>', $limit);
        $count = (clone $query)->count();

        if ($count === 0) {
            return;
        }

        $files = $query->orderByDesc('size')->limit(20)->get();

        yield $this->found('heavy', ['count' => $count, 'kb' => (int) ($limit / 1024)], table: [
            'columns' => [Finding::column('title'), Finding::column('kb'), Finding::column('edit', 'edit')],
            'rows' => $files->map(static fn (MediaFile $file): array => [
                'title' => $file->file_name,
                'kb' => (int) round($file->size / 1024),
                'edit' => '/media',
            ])->values()->all(),
        ]);
    }
}
