<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

/**
 * `manifest.json`, the first file of every archive: what is inside and where it came from.
 *
 * The version is the archive's, not the package's. A patch (1.0.x) only adds fields a reader
 * may ignore, so a stand reads an archive from a newer patch; a minor or major one changes what
 * the files mean, and a stand that does not know it refuses rather than guesses. Hosting tools
 * read this file too, so its fields are a contract: add, never rename.
 */
final class Manifest
{
    public const FORMAT = 'webx-snapshot';

    public const VERSION = '1.0.0';

    public const NAME = 'manifest.json';

    public const DATABASE = 'database.jsonl';

    public const MEDIA = 'media/';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public readonly array $data) {}

    public static function fromJson(string $json, string $path): self
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw SnapshotFailed::notASnapshot($path);
        }

        $format = (string) ($data['format'] ?? '');
        $version = (string) ($data['version'] ?? '');

        if ($format !== self::FORMAT || ! self::readable($version)) {
            throw SnapshotFailed::unknownFormat($format === '' ? '?' : $format, $version === '' ? '?' : $version, self::VERSION);
        }

        return new self($data);
    }

    /**
     * Same major and minor, any patch.
     */
    public static function readable(string $version): bool
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $theirs) !== 1) {
            return false;
        }

        [, $major, $minor] = explode('.', '.'.self::VERSION);

        return (int) $theirs[1] === (int) $major && (int) $theirs[2] === (int) $minor;
    }

    public function toJson(): string
    {
        return (string) json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function site(): string
    {
        return (string) ($this->data['site'] ?? '');
    }

    public function env(): string
    {
        return (string) ($this->data['env'] ?? '');
    }

    public function url(): string
    {
        return (string) ($this->data['url'] ?? '');
    }

    public function createdAt(): string
    {
        return (string) ($this->data['created_at'] ?? '');
    }

    /**
     * @return list<string>
     */
    public function migrations(): array
    {
        return array_values(array_map(strval(...), (array) ($this->data['migrations'] ?? [])));
    }

    /**
     * @return array<string, array{group: string|null, rows: int, columns: list<string>}>
     */
    public function tables(): array
    {
        /** @var array<string, array{group: string|null, rows: int, columns: list<string>}> */
        return (array) ($this->data['tables'] ?? []);
    }

    public function hasMedia(): bool
    {
        return is_array($this->data['media'] ?? null);
    }

    /**
     * @return array<string, string> Path relative to the disk root => sha256.
     */
    public function mediaFiles(): array
    {
        /** @var array<string, string> */
        return $this->hasMedia() ? (array) ($this->data['media']['files'] ?? []) : [];
    }

    public function mediaBytes(): int
    {
        return $this->hasMedia() ? (int) ($this->data['media']['bytes'] ?? 0) : 0;
    }
}
