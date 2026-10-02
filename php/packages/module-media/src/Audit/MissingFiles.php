<?php

declare(strict_types=1);

namespace WebxUi\Media\Audit;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Media\Models\MediaFile;

/**
 * A file the library lists and the disk does not have: copied a database without `storage/`,
 * cleaned a folder by hand, moved to another disk. Every page that shows it shows a hole.
 */
final class MissingFiles extends ModuleCheck
{
    protected const ID = 'media.missing_file';

    protected const GROUP = 'media';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-media';

    public function __construct(private readonly Filesystems $disks) {}

    public function run(AuditContext $context): iterable
    {
        foreach (MediaFile::query()->lazyById(500) as $file) {
            if (! $this->disks->disk($file->disk)->exists($file->path)) {
                yield $this->found('missing-file', ['file' => $file->file_name, 'disk' => $file->disk], key: (string) $file->id, table: [
                    'columns' => [Finding::column('title'), Finding::column('value'), Finding::column('edit', 'edit')],
                    'rows' => [['title' => $file->file_name, 'value' => $file->disk.':'.$file->path, 'edit' => '/media']],
                ]);
            }
        }
    }
}
