<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks;

/**
 * What a check found.
 *
 * `details` is data for the expansion, not text (§4): a `summary` as a dictionary key with its
 * parameters, and optionally a `table` of `columns` (key, label key, cell type) and `rows`. The
 * panel draws every check's expansion with one component from that shape, and the words are
 * translated for whoever reads them rather than for whoever ran the audit.
 *
 * `runId` is set on a finding read back from the database — what a fix needs to reach the
 * run's snapshot; a check that yields findings leaves it out.
 *
 * `key` tells two findings of one check at one address apart — the field of a record, the asset
 * of a page — and goes into the fingerprint, so the same problem is recognised in the next run.
 */
final readonly class Finding
{
    /**
     * @param  array{summary?: array{key: string, params?: array<string, scalar|null>}, table?: array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}}  $details
     */
    public function __construct(
        public string $check,
        public string $severity,
        public ?string $url = null,
        public array $details = [],
        public string $key = '',
        public ?int $pageId = null,
        public ?int $runId = null,
    ) {}

    public function fingerprint(): string
    {
        return sha1($this->check."\n".($this->url ?? '')."\n".$this->key);
    }

    /**
     * A summary line, by its key in `webx-audit::details`.
     *
     * @param  array<string, scalar|null>  $params
     * @return array{key: string, params: array<string, scalar|null>}
     */
    public static function summary(string $key, array $params = []): array
    {
        return ['key' => 'webx-audit::details.'.$key, 'params' => $params];
    }

    /**
     * A column of a details table. `type` is `url`, `status`, `bool`, `text`, `missing`, `edit`,
     * `code` (markup quoted from the page),
     * or `word` — a key of `webx-audit::details`, said in the reader's language.
     *
     * @return array{key: string, label: string, type: string}
     */
    public static function column(string $key, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => 'webx-audit::details.column-'.$key, 'type' => $type];
    }
}
