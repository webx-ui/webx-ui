<?php

declare(strict_types=1);

namespace WebxUi\Seo\Links;

use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * A brief's interlinking applied as it came (§18.4): lines grouped by donor in the order of the
 * file, each donor's block replaced by its lines (the default) or followed by them.
 *
 * Always runnable as a preview first — the same walk with nothing written — so the editor sees
 * how many donors and links there are, which blocks are new and which get replaced, and what is
 * wrong with which line, before anything changes. A bad line costs that line, not the file.
 */
final class LinkImport
{
    public const REPLACE = 'replace';

    public const APPEND = 'append';

    public function __construct(
        private readonly LinkWriter $writer,
        private readonly UrlTargets $targets,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  keyed by the line number errors are reported under
     * @return array<string, mixed>
     */
    public function run(array $rows, string $mode = self::REPLACE, bool $dryRun = true, ?string $locale = null): array
    {
        $append = $mode === self::APPEND;
        $groups = [];
        $problems = [];

        foreach ($rows as $line => $row) {
            $donor = is_string($row['donor'] ?? null) ? trim($row['donor']) : '';

            if ($donor === '') {
                $problems[] = $this->problem($line, 'donor', 'empty');

                continue;
            }

            try {
                $target = $this->targets->resolve($donor, $locale)->target;
            } catch (ForeignHost) {
                $problems[] = $this->problem($line, 'donor', 'foreign-host');

                continue;
            }

            // Two spellings of one donor — with the host, with a slash, an old address of the same
            // page — are one block.
            $key = $target->isBound()
                ? $target->locale.'|'.$target->entityType.'|'.$target->entityId
                : $target->locale.'|'.$target->path;

            $groups[$key] ??= ['donor' => $donor, 'line' => $line, 'heading' => null, 'items' => []];

            $heading = is_string($row['heading'] ?? null) ? trim($row['heading']) : '';

            if ($groups[$key]['heading'] === null && $heading !== '') {
                $groups[$key]['heading'] = $heading;
            }

            $groups[$key]['items'][] = [
                'acceptor' => $row['acceptor'] ?? '',
                'anchor' => $row['anchor'] ?? '',
                'line' => $line,
            ];
        }

        $counts = ['donors' => 0, 'links' => 0, 'created' => 0, 'replaced' => 0, 'appended' => 0];
        $donors = [];

        foreach ($groups as $group) {
            $existing = null;
            $plan = $this->writer->plan($group['donor'], $group['items'], $locale, strict: true, donorLine: $group['line']);

            if ($plan->donor !== null) {
                $existing = $this->writer->blockFor($plan->donor->target);

                if ($append && $existing !== null) {
                    // Planned again against what the block already holds, so a link it has is a
                    // duplicate rather than a second copy.
                    $existing->load('items');
                    $plan = $this->writer->plan($group['donor'], $group['items'], $locale, strict: true, into: $existing, donorLine: $group['line']);
                }
            }

            array_push($problems, ...$plan->problems);

            if ($plan->donor === null || $plan->items === []) {
                continue;
            }

            $counts['donors']++;
            $counts['links'] += count($plan->items);
            $counts[$existing === null ? 'created' : ($append ? 'appended' : 'replaced')]++;

            $donors[] = [
                'donor' => $this->targets->address($plan->donor->target),
                'links' => count($plan->items),
                'action' => $existing === null ? 'create' : ($append ? 'append' : 'replace'),
                'heading' => $group['heading'],
            ];

            if (! $dryRun) {
                $this->writer->save($plan, $group['heading'] === null ? [] : ['heading' => $group['heading']], $append, $existing);
            }
        }

        usort($problems, static fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return [
            'ok' => true,
            'applied' => ! $dryRun,
            'mode' => $append ? self::APPEND : self::REPLACE,
            ...$counts,
            'errors' => count(array_filter($problems, static fn (array $problem): bool => $problem['level'] === LinkWriter::ERROR)),
            'problems' => $problems,
            'blocks' => $donors,
        ];
    }

    /**
     * @return array{line: int, field: string, code: string, level: string, message: string}
     */
    private function problem(int $line, string $field, string $code): array
    {
        return [
            'line' => $line,
            'field' => $field,
            'code' => $code,
            'level' => LinkWriter::ERROR,
            'message' => (string) __('webx-seo::links.'.$code),
        ];
    }
}
