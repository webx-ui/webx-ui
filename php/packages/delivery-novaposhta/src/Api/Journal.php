<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Api;

/**
 * The journal of the client's calls (§10.3): one JSON line per call — time, model, method, page,
 * outcome, milliseconds. Never the key, never the properties sent or the data received, so it can
 * be read and handed over as it is: it is how one checks that a site called nothing but reading
 * methods on a key that belongs to somebody else.
 *
 * Outcomes: `ok`, `error` (the carrier said no), `key` (the key was refused), `no_answer`,
 * `bad_response`, `refused` (stopped by the client before a request: read only or unknown).
 */
final class Journal
{
    public function __construct(private readonly ?string $path) {}

    public function record(string $model, string $method, string $outcome, ?int $page = null, ?int $ms = null): void
    {
        if ($this->path === null || $this->path === '') {
            return;
        }

        $line = json_encode(array_filter([
            'at' => gmdate('Y-m-d\TH:i:s\Z'),
            'model' => $model,
            'method' => $method,
            'page' => $page,
            'outcome' => $outcome,
            'ms' => $ms,
        ], static fn (mixed $value): bool => $value !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        // A journal that cannot be written must not break a sync or a waybill.
        @file_put_contents($this->path, $line."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Every line of the journal, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        if ($this->path === null || ! is_file($this->path)) {
            return [];
        }

        $entries = [];

        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);

            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function path(): ?string
    {
        return $this->path;
    }
}
