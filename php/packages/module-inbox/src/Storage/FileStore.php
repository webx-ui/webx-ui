<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Storage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionFile;

/**
 * Where a visitor's attachments go (§8).
 *
 * Its own disk, private by default, and never the media library: those are files editors chose
 * and these are files strangers sent, and mixing them means the next person browsing the
 * library is browsing somebody's passport scan. It also means this module does not depend on
 * `module-media` at all.
 *
 * The key is `inbox/{form}/{submission}/{uuid}.{ext}` — a uuid rather than the visitor's own
 * file name, so that nothing about a file name can become a path, and so that two people
 * sending `scan.pdf` are two files.
 */
final class FileStore
{
    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly Config $config,
    ) {}

    public function store(UploadedFile $upload, Submission $submission, Field $field): SubmissionFile
    {
        $path = $this->key($submission, $upload);

        $this->disk()->putFileAs(dirname($path), $upload, basename($path));

        $original = (string) $upload->getClientOriginalName();

        return $submission->files()->create([
            'field_id' => $field->getKey(),
            'disk' => $this->diskName(),
            'path' => $path,
            'name' => Str::limit($original, 250, ''),
            'size' => (int) $upload->getSize(),
            'mime' => $upload->getClientMimeType(),
            'created_at' => Carbon::now(),
        ]);
    }

    public function disk(?string $name = null): Filesystem
    {
        return $this->filesystems->disk($name ?? $this->diskName());
    }

    public function diskName(): string
    {
        return (string) $this->config->get('webx-inbox.disk', 'local');
    }

    /**
     * The extensions a field takes: its own list, or the site's.
     *
     * A white list of extensions rather than a black list of the dangerous ones, for the
     * reason the media library keeps one — the list of what must not be uploaded always
     * forgets the executable.
     *
     * @return list<string>
     */
    public function extensions(Field $field): array
    {
        $own = $field->option('extensions');

        $extensions = is_array($own) && $own !== []
            ? $own
            : (array) $this->config->get('webx-inbox.upload.extensions', []);

        return array_values(array_map(
            static fn ($extension): string => strtolower(trim((string) $extension, ". \t\n\r\0\x0B")),
            array_filter($extensions, 'is_scalar'),
        ));
    }

    /** The biggest file this field takes, in kilobytes — the unit Laravel's validation speaks. */
    public function maxSize(Field $field): int
    {
        $own = $field->option('max_size');
        $default = (int) $this->config->get('webx-inbox.upload.max_size', 10240);

        if (! is_numeric($own) || (int) $own <= 0) {
            return $default;
        }

        // A field cannot ask for more than the site allows: the limit in the configuration is
        // the one the server was sized for, and a form is edited by whoever has `inbox.manage`.
        return min((int) $own, $default);
    }

    public function maxFiles(): int
    {
        return (int) $this->config->get('webx-inbox.upload.max_files', 10);
    }

    private function key(Submission $submission, UploadedFile $upload): string
    {
        $prefix = trim((string) $this->config->get('webx-inbox.prefix', 'inbox'), '/');
        $extension = $this->extension($upload);

        return "{$prefix}/{$submission->form_id}/{$submission->getKey()}/".Str::uuid()->toString().'.'.$extension;
    }

    private function extension(UploadedFile $upload): string
    {
        $extension = strtolower($upload->getClientOriginalExtension());

        if ($extension === '') {
            $extension = strtolower((string) $upload->guessExtension());
        }

        return preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
    }
}
