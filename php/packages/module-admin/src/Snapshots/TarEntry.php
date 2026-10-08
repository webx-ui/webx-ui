<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

/**
 * One file inside an archive being read. Valid only until the next entry is asked for.
 */
final class TarEntry
{
    private int $read = 0;

    /**
     * @param  resource  $gz
     */
    public function __construct(
        public readonly string $name,
        public readonly int $size,
        public readonly bool $directory,
        private $gz,
        private readonly string $archive,
    ) {}

    public function contents(): string
    {
        $data = Tar::exactly($this->gz, $this->size - $this->read, $this->archive);
        $this->read = $this->size;

        return $data;
    }

    public function copyTo(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw SnapshotFailed::cannotWrite($directory);
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw SnapshotFailed::cannotWrite($path);
        }

        try {
            while ($this->read < $this->size) {
                $chunk = Tar::exactly($this->gz, min(1048576, $this->size - $this->read), $this->archive);
                $this->read += strlen($chunk);

                if (fwrite($handle, $chunk) === false) {
                    throw SnapshotFailed::cannotWrite($path);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function remaining(): int
    {
        return $this->size - $this->read;
    }
}
