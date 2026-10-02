<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Audit\Checks\CheckTexts;
use WebxUi\Audit\Runs\AuditIssue;

/**
 * A finding with its details said in the reader's language: the summary line, and the table's
 * column labels. The cells stay data — the panel draws them by their type.
 *
 * @mixin AuditIssue
 */
final class IssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditIssue $issue */
        $issue = $this->resource;

        return [
            'id' => $issue->id,
            'check' => $issue->check,
            'severity' => $issue->severity,
            'url' => $issue->url,
            'state' => $issue->state,
            'ignored' => $issue->ignored_by !== null,
            'details' => self::details($issue->details ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{summary: string|null, table: array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>}|null}
     */
    public static function details(array $details): array
    {
        $summary = $details['summary'] ?? null;
        $table = $details['table'] ?? null;

        $columns = [];

        if (is_array($table)) {
            foreach ((array) ($table['columns'] ?? []) as $column) {
                if (is_array($column)) {
                    $columns[] = [
                        'key' => (string) ($column['key'] ?? ''),
                        'label' => CheckTexts::line((string) ($column['label'] ?? '')),
                        'type' => (string) ($column['type'] ?? 'text'),
                    ];
                }
            }
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = is_array($table) ? array_values(array_filter((array) ($table['rows'] ?? []), 'is_array')) : [];

        return [
            'summary' => is_array($summary) && is_string($summary['key'] ?? null)
                ? CheckTexts::line($summary['key'], is_array($summary['params'] ?? null) ? $summary['params'] : [])
                : null,
            'table' => is_array($table) ? ['columns' => $columns, 'rows' => $rows] : null,
        ];
    }
}
