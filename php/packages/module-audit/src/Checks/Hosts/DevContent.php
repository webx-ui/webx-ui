<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\ContentUrl;

/**
 * An address of a development stand in the database — in a published record, a draft or a
 * field the template never prints (decision 13). The most expensive thing an audit can catch:
 * content filled in on a stand with absolute links goes live pointing back at the stand, and is
 * noticed by accident.
 *
 * One finding per field of a record, every stand address of that field in its table, so the
 * expansion says where, in which language, and opens the editor.
 */
final class DevContent extends Check
{
    protected const ID = 'hosts.dev_content';

    protected const GROUP = 'hosts';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['database'];

    public function run(AuditContext $context): iterable
    {
        $rows = ContentUrl::query()
            ->where('run_id', $context->run->id)
            ->where('host_class', HostClassifier::DEV)
            ->orderBy('source')->orderBy('record_id')->orderBy('field')->orderBy('id')
            ->get();

        foreach ($rows->groupBy(static fn (ContentUrl $row): string => implode('|', [$row->source, $row->record_id, $row->field, $row->locale ?? ''])) as $key => $group) {
            /** @var ContentUrl $first */
            $first = $group->first();
            $hosts = $group->pluck('host')->unique()->values()->all();

            yield new Finding(self::ID, self::SEVERITY, $first->url, [
                'summary' => Finding::summary('dev-content', [
                    'hosts' => implode(', ', $hosts),
                    'record' => $first->record_label ?? $first->record_id,
                    'field' => $first->field,
                    'count' => $group->count(),
                ]),
                'table' => [
                    'columns' => [
                        Finding::column('record'),
                        Finding::column('field'),
                        Finding::column('locale'),
                        Finding::column('url', 'url'),
                        Finding::column('published', 'bool'),
                        Finding::column('edit', 'edit'),
                    ],
                    'rows' => $group->map(static fn (ContentUrl $row): array => [
                        'record' => $row->record_label ?? $row->record_id,
                        'field' => $row->field,
                        'locale' => $row->locale,
                        'url' => $row->url,
                        'published' => $row->published,
                        'edit' => $row->edit_url,
                    ])->values()->all(),
                ],
            ], (string) $key);
        }
    }
}
