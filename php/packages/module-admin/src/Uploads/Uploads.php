<?php

declare(strict_types=1);

namespace WebxUi\Admin\Uploads;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Files that arrive a piece at a time (§4 of the video spec) — the panel's, not a module's: the
 * catalogue's videos are the first to need it, the library and anything else with large files
 * the next.
 *
 * Its own small protocol rather than tus: four requests, simpler to write and to check here
 * than a server library is to carry. A session is a row and a `.part` file; each piece is
 * appended at the offset the server holds, so there is no assembly at the end — a finished file
 * is already whole, and handing it over is one move, not gigabytes glued together inside a
 * request with a time limit.
 */
final class Uploads
{
    /** The smallest piece the client will fall back to; the server never advises less. */
    public const MIN_CHUNK = 262144;

    public function __construct(
        private readonly Config $config,
        private readonly UploadPurposes $purposes,
        private readonly FreeSpace $space,
        private readonly string $directory,
    ) {}

    public function directory(): string
    {
        return $this->directory;
    }

    public function path(string $id): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$id.'.part';
    }

    public function ttlHours(): int
    {
        return max(1, (int) $this->config->get('webx-admin.uploads.ttl_hours', 24));
    }

    /** The piece this server can take, which is what the client starts with. */
    public function chunkSize(): int
    {
        $preferred = (int) round((float) $this->config->get('webx-admin.uploads.chunk_mb', 8) * 1048576);

        return self::advise($preferred, ini_get('upload_max_filesize'), ini_get('post_max_size'));
    }

    /**
     * The smaller of the configured piece and 90 % of what PHP lets a request carry. The ten per
     * cent is the headers and whatever the web server counts that PHP does not; what nginx allows
     * the server cannot know at all, and the client halves its piece on a 413 for that.
     */
    public static function advise(int $preferred, string|false $uploadMax, string|false $postMax): int
    {
        $limits = array_filter(
            [self::bytes($uploadMax), self::bytes($postMax)],
            static fn (int $limit): bool => $limit > 0,
        );

        $chunk = $limits === [] ? $preferred : min($preferred, (int) floor(min($limits) * 0.9));

        return max(self::MIN_CHUNK, $chunk);
    }

    /**
     * A session for this file: a new one, or the one the same administrator already started for
     * the same file and purpose, with however much of it arrived.
     *
     * @return array{Upload, bool} the session and whether it was created now
     */
    public function start(
        mixed $admin,
        string $purpose,
        string $name,
        int $size,
        string $type,
        string $fingerprint,
    ): array {
        $rules = $this->purposes->find($purpose) ?? throw UploadRefused::unknownPurpose();

        if (! $rules->allows($admin)) {
            throw UploadRefused::forbidden();
        }

        if (! $rules->accepts($type)) {
            throw UploadRefused::wrongType();
        }

        if ($rules->maxBytes !== null && $size > $rules->maxBytes) {
            throw UploadRefused::tooLarge($rules->maxBytes);
        }

        $adminId = self::adminId($admin) ?? throw UploadRefused::forbidden();
        $hash = sha1($fingerprint);

        $existing = Upload::query()
            ->where('admin_id', $adminId)
            ->where('purpose', $purpose)
            ->where('fingerprint', $hash)
            ->where('size', $size)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if ($existing instanceof Upload) {
            $this->reconcile($existing);
            $this->room($size - $existing->offset);

            $existing->forceFill(['expires_at' => $this->expiry()])->save();

            return [$existing, false];
        }

        $this->room($size);

        $upload = Upload::query()->create([
            'admin_id' => $adminId,
            'purpose' => $purpose,
            'name' => mb_substr($name, 0, 255),
            'size' => $size,
            'type' => mb_substr($type, 0, 128),
            'fingerprint' => $hash,
            'offset' => 0,
            'expires_at' => $this->expiry(),
        ]);

        if (file_put_contents($this->path($upload->id), '') === false) {
            $upload->delete();

            throw new RuntimeException("The directory [{$this->directory}] does not take files.");
        }

        return [$upload, true];
    }

    /**
     * The session of this administrator by its id. Somebody else's, an expired one and one that
     * never was are the same 404: an id is not something to confirm the existence of.
     */
    public function find(mixed $admin, string $id): Upload
    {
        $upload = Upload::query()->find($id);

        if (! $upload instanceof Upload || $upload->expired() || $upload->admin_id !== self::adminId($admin)) {
            throw UploadRefused::missing();
        }

        return $upload;
    }

    /**
     * Append a piece at `$offset`, which has to be where the file ends now.
     *
     * The file is locked while it is written, so two tabs sending the same upload cannot
     * interleave; whichever comes second finds the offset moved and gets a 409 with the new one.
     * What arrives is written as it arrives — a piece cut short by a dropped connection is still
     * the right bytes in the right place, and the next piece starts after them.
     *
     * @param  resource  $body
     */
    public function append(Upload $upload, int $offset, $body, ?int $length = null): Upload
    {
        $path = $this->path($upload->id);
        $file = fopen($path, 'c+b');

        if ($file === false) {
            throw UploadRefused::missing();
        }

        try {
            flock($file, LOCK_EX);

            $upload->refresh();
            $this->reconcile($upload);

            if ($upload->expired()) {
                throw UploadRefused::missing();
            }

            if ($offset !== $upload->offset) {
                throw UploadRefused::offset($upload->offset);
            }

            $room = $upload->size - $offset;

            if ($length !== null && $length > $room) {
                throw UploadRefused::overflow();
            }

            // Whatever a crash left past the offset is not part of the file.
            ftruncate($file, $offset);
            fseek($file, $offset);

            $written = $room > 0 ? (int) stream_copy_to_stream($body, $file, $room) : 0;

            if (! feof($body) && fread($body, 1) !== '') {
                ftruncate($file, $offset);

                throw UploadRefused::overflow();
            }

            fflush($file);

            $upload->forceFill([
                'offset' => $offset + $written,
                'expires_at' => $this->expiry(),
            ])->save();

            return $upload;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public function cancel(Upload $upload): void
    {
        $upload->delete();
        $this->forgetFile($upload->id);
    }

    /**
     * Hand a finished file to the module it was for. The session goes now; the file is the
     * consumer's to move or discard ({@see ClaimedUpload}).
     *
     * `$admin`, when given, has to be whoever uploaded it: a consumer that takes the id from a
     * request passes the request's administrator, so that an id seen in somebody else's browser
     * does not attach somebody else's file.
     */
    public function claim(string $id, string $purpose, mixed $admin = null): ClaimedUpload
    {
        $upload = Upload::query()->find($id);

        if (! $upload instanceof Upload || $upload->expired()) {
            throw UploadRefused::missing();
        }

        if ($admin !== null && $upload->admin_id !== self::adminId($admin)) {
            throw UploadRefused::missing();
        }

        if ($upload->purpose !== $purpose) {
            throw UploadRefused::wrongPurpose();
        }

        $path = $this->path($upload->id);

        if (! $upload->finished() || ! is_file($path) || filesize($path) !== $upload->size) {
            throw UploadRefused::unfinished();
        }

        // Whoever deletes the row owns the file; a second claim of the same id finds nothing.
        if (Upload::query()->whereKey($upload->id)->delete() === 0) {
            throw UploadRefused::missing();
        }

        return new ClaimedUpload($upload->id, $path, $upload->name, $upload->size, $upload->type);
    }

    /**
     * Remove the sessions nobody has sent a piece to within the TTL, their files with them, and
     * any piece file that has outlived its row — a claim whose consumer never moved it, or a row
     * deleted by hand.
     *
     * @return int how many files went
     */
    public function prune(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $removed = 0;

        Upload::query()
            ->where('expires_at', '<=', $now)
            ->chunkById(200, function ($uploads) use (&$removed): void {
                foreach ($uploads as $upload) {
                    /** @var Upload $upload */
                    $upload->delete();
                    $removed += $this->forgetFile($upload->id) ? 1 : 0;
                }
            });

        $before = $now->copy()->subHours($this->ttlHours())->getTimestamp();

        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*.part') ?: [] as $file) {
            $id = basename($file, '.part');

            if (filemtime($file) < $before && ! Upload::query()->whereKey($id)->exists()) {
                $removed += @unlink($file) ? 1 : 0;
            }
        }

        return $removed;
    }

    /** The room this many bytes need, refused now rather than at 90 %. */
    private function room(int $bytes): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("The directory [{$this->directory}] could not be created.");
        }

        $free = $this->space->bytes($this->directory);

        if ($free !== null && $bytes > $free) {
            throw UploadRefused::noSpace($free);
        }
    }

    /**
     * The file is the truth about how much arrived. A row ahead of its file — a disk restored
     * from a backup, a file deleted by hand — would have the client skip bytes nobody has.
     */
    private function reconcile(Upload $upload): void
    {
        $path = $this->path($upload->id);
        clearstatcache(true, $path);
        $length = is_file($path) ? (int) filesize($path) : 0;

        if ($length < $upload->offset) {
            $upload->forceFill(['offset' => $length])->save();
        }
    }

    private function forgetFile(string $id): bool
    {
        $path = $this->path($id);

        return is_file($path) && @unlink($path);
    }

    private function expiry(): Carbon
    {
        return Carbon::now()->addHours($this->ttlHours());
    }

    private static function adminId(mixed $admin): ?int
    {
        if (! is_object($admin) || ! method_exists($admin, 'getAuthIdentifier')) {
            return null;
        }

        $id = $admin->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    /** `8M`, `2G`, `512K`, `0` (no limit) → bytes. */
    private static function bytes(string|false $value): int
    {
        if ($value === false) {
            return 0;
        }

        $value = trim($value);

        if ($value === '' || ! is_numeric(rtrim($value, 'kKmMgG'))) {
            return 0;
        }

        $number = (float) rtrim($value, 'kKmMgG');

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
