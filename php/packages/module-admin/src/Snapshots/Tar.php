<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

use Generator;

/**
 * A gzipped tar, written and read as a stream.
 *
 * Not `PharData`: it builds the archive in place and compresses it into a second file, so a site
 * with two gigabytes of photographs needs four free, and on some hostings `phar` is not there at
 * all. What is written here is plain ustar with GNU long names — `tar xzf` on any machine opens
 * it, which matters for a file somebody carries by hand and may want to look into.
 */
final class Tar
{
    private const BLOCK = 512;

    /** @var resource */
    private $gz;

    private function __construct(private readonly string $path, string $mode)
    {
        $gz = @gzopen($path, $mode);

        if ($gz === false) {
            throw SnapshotFailed::cannotOpen($path);
        }

        $this->gz = $gz;
    }

    public static function create(string $path): self
    {
        return new self($path, 'wb6');
    }

    public function addString(string $name, string $contents): void
    {
        $this->header($name, strlen($contents), '0');
        $this->write($contents);
        $this->pad(strlen($contents));
    }

    public function addFile(string $name, string $source): void
    {
        $size = (int) filesize($source);
        $handle = fopen($source, 'rb');

        if ($handle === false) {
            throw SnapshotFailed::cannotRead($source);
        }

        $this->header($name, $size, '0', (int) filemtime($source));

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 1048576);

                if ($chunk === false) {
                    throw SnapshotFailed::cannotRead($source);
                }

                $this->write($chunk);
            }
        } finally {
            fclose($handle);
        }

        $this->pad($size);
    }

    public function close(): void
    {
        $this->write(str_repeat("\0", self::BLOCK * 2));
        gzclose($this->gz);
    }

    /**
     * Every entry in order, as `name => entry`. An entry not read by the consumer is skipped when
     * the next one is asked for, so stopping after the manifest reads one block, not the photos.
     *
     * @return Generator<string, TarEntry>
     */
    public static function read(string $path): Generator
    {
        $gz = @gzopen($path, 'rb');

        if ($gz === false) {
            throw SnapshotFailed::cannotOpen($path);
        }

        try {
            $longName = null;

            while (true) {
                $header = self::exactly($gz, self::BLOCK, $path, allowEnd: true);

                if ($header === '' || trim($header, "\0") === '') {
                    return;
                }

                $size = (int) octdec(trim(substr($header, 124, 12), " \0"));
                $type = $header[156];
                $name = rtrim(substr($header, 0, 100), "\0");

                if (substr($header, 257, 5) === 'ustar') {
                    $prefix = rtrim(substr($header, 345, 155), "\0");
                    $name = $prefix === '' ? $name : $prefix.'/'.$name;
                }

                if ($type === 'L') {
                    $longName = rtrim(self::exactly($gz, $size, $path), "\0");
                    self::skip($gz, self::padding($size), $path);

                    continue;
                }

                if ($type === 'x') {
                    // A pax header, from an archive repacked by GNU or BSD tar: its `path` is the
                    // only record that matters here.
                    $pax = self::exactly($gz, $size, $path);
                    self::skip($gz, self::padding($size), $path);

                    if (preg_match('/^\d+ path=(.*)$/m', $pax, $match) === 1) {
                        $longName = $match[1];
                    }

                    continue;
                }

                if ($longName !== null) {
                    $name = $longName;
                    $longName = null;
                }

                $entry = new TarEntry($name, $size, $type === '5' || str_ends_with($name, '/'), $gz, $path);

                yield $name => $entry;

                self::skip($gz, $entry->remaining() + self::padding($size), $path);
            }
        } finally {
            gzclose($gz);
        }
    }

    /**
     * @param  resource  $gz
     */
    public static function exactly($gz, int $length, string $path, bool $allowEnd = false): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = gzread($gz, min(1048576, $length - strlen($data)));

            if ($chunk === false || $chunk === '') {
                if ($allowEnd && $data === '') {
                    return '';
                }

                throw SnapshotFailed::truncated($path);
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @param  resource  $gz
     */
    private static function skip($gz, int $length, string $path): void
    {
        while ($length > 0) {
            $chunk = gzread($gz, min(1048576, $length));

            if ($chunk === false || $chunk === '') {
                throw SnapshotFailed::truncated($path);
            }

            $length -= strlen($chunk);
        }
    }

    private static function padding(int $size): int
    {
        return (self::BLOCK - $size % self::BLOCK) % self::BLOCK;
    }

    private function header(string $name, int $size, string $type, ?int $mtime = null): void
    {
        if (strlen($name) > 100) {
            $this->header('././@LongLink', strlen($name) + 1, 'L');
            $this->write($name."\0");
            $this->pad(strlen($name) + 1);
            $name = substr($name, 0, 100);
        }

        $header = str_pad($name, 100, "\0")
            .sprintf('%07o', 0644)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%011o', $size)."\0"
            .sprintf('%011o', $mtime ?? time())."\0"
            .'        '
            .$type
            .str_repeat("\0", 100)
            ."ustar\0".'00'
            .str_repeat("\0", 32 * 2 + 8 * 2 + 155 + 12);

        $sum = array_sum(array_map(ord(...), str_split($header)));
        $header = substr_replace($header, sprintf('%06o', $sum)."\0 ", 148, 8);

        $this->write($header);
    }

    private function pad(int $size): void
    {
        $padding = self::padding($size);

        if ($padding > 0) {
            $this->write(str_repeat("\0", $padding));
        }
    }

    private function write(string $data): void
    {
        if ($data !== '' && gzwrite($this->gz, $data) === false) {
            throw SnapshotFailed::cannotWrite($this->path);
        }
    }
}
