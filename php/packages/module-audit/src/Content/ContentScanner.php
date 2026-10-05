<?php

declare(strict_types=1);

namespace WebxUi\Audit\Content;

use Illuminate\Support\Carbon;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Hosts\UrlFinder;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\ContentUrl;

/**
 * The database half of stage 6 (§3): every field of every record of every source, searched for
 * absolute addresses, each one classified and kept in `audit_content_urls`.
 *
 * Works for a budget of seconds and answers with where to go on from, or null when every source
 * is done — one queued job does a piece and the next one continues (decision 5).
 */
final class ContentScanner
{
    public function __construct(
        private readonly AuditContentSources $sources,
        private readonly UrlFinder $finder,
    ) {}

    /**
     * @param  array{source?: int, skip?: int}  $cursor
     * @return array{source: int, skip: int}|null
     */
    public function scan(AuditRun $run, HostClassifier $hosts, float $budget, array $cursor = []): ?array
    {
        $deadline = microtime(true) + $budget;
        $sources = array_values($this->sources->all());
        $index = $cursor['source'] ?? 0;
        $skip = $cursor['skip'] ?? 0;

        while ($index < count($sources)) {
            $source = $sources[$index];
            $seen = 0;

            foreach ($source->records() as $record) {
                if ($seen++ < $skip) {
                    continue;
                }

                $this->record($run, $source->id(), $source->fields($record), $record, $hosts);
                $skip++;

                if (microtime(true) >= $deadline) {
                    return ['source' => $index, 'skip' => $skip];
                }
            }

            $index++;
            $skip = 0;
        }

        return null;
    }

    /**
     * @param  iterable<ContentField>  $fields
     */
    private function record(AuditRun $run, string $source, iterable $fields, ContentRecord $record, HostClassifier $hosts): void
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($fields as $field) {
            // What the site prints, not what the panel keeps beside a library key ({@see LibraryAddresses}).
            foreach ($this->finder->find(LibraryAddresses::strip($field->value)) as $url) {
                $host = HostClassifier::hostOf($url);

                if ($host === null) {
                    continue;
                }

                $rows[] = [
                    'run_id' => $run->id,
                    'source' => $source,
                    'record_id' => $record->id,
                    'record_label' => mb_substr($record->label, 0, 255),
                    'field' => mb_substr($field->name, 0, 128),
                    'locale' => $field->locale,
                    'url' => mb_substr($url, 0, 2048),
                    'host' => mb_substr($host, 0, 255),
                    'host_class' => $hosts->classifyHost($host),
                    'published' => $field->published ?? $record->published,
                    'edit_url' => $record->editUrl,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ContentUrl::query()->insert($chunk);
        }
    }
}
