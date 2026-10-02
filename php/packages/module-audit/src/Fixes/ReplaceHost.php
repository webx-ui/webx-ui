<?php

declare(strict_types=1);

namespace WebxUi\Audit\Fixes;

use WebxUi\Audit\AuditSettings;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Audit\Contracts\AuditContentSource;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\ContentUrl;

/**
 * `audit.replace-host` (§5.6): the stand's host in one field of one record becomes the site's
 * own, written back through the module's model — `AuditContentSource::replace()` — and never by
 * SQL, so the history journal has every change and each can be rolled back.
 *
 * One finding of `hosts.dev_content` is one field of one record in one language, which is what
 * its key says; the hosts to replace are the stand hosts the run found there. The field is read
 * again when the fix is previewed and when it is pressed — what was found yesterday may have been
 * edited since, and the count says what is there now.
 */
final readonly class ReplaceHost implements AuditFix
{
    public const ID = 'audit.replace-host';

    public function __construct(
        private AuditContentSources $sources,
        private AuditSettings $settings,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function fixes(): array
    {
        return ['hosts.dev_content'];
    }

    public function available(Finding $finding): bool
    {
        return $this->target($finding) !== null;
    }

    public function preview(Finding $finding): FixPreview
    {
        $target = $this->target($finding);

        if ($target === null) {
            return new FixPreview;
        }

        [, $record, $field, $hosts, $base] = $target;
        [, $count] = self::swap($field->value, $hosts, $base);

        if ($count === 0) {
            return new FixPreview;
        }

        return new FixPreview([[
            'label' => $record->label,
            'field' => $field->locale === null ? $field->name : $field->name.' · '.$field->locale,
            'count' => $count,
            'before' => implode(', ', $hosts),
            'after' => (string) parse_url($base, PHP_URL_HOST),
            'edit_url' => $record->editUrl,
        ]]);
    }

    public function apply(Finding $finding): void
    {
        $target = $this->target($finding);

        if ($target === null) {
            return;
        }

        [$source, $record, $field, $hosts, $base] = $target;
        [$value, $count] = self::swap($field->value, $hosts, $base);

        if ($count > 0) {
            $source->replace($record, $field, $value);
        }
    }

    /**
     * Every `//host` of the given hosts — with a scheme or without, with a port or without —
     * turned into the site's own origin. Only where the host ends: `dev.shop.com.evil` stays.
     *
     * @param  list<string>  $hosts
     * @return array{string, int}
     */
    public static function swap(string $value, array $hosts, string $base): array
    {
        $total = 0;

        foreach ($hosts as $host) {
            $pattern = '~(?:https?:)?//'.preg_quote($host, '~').'(?::\d+)?(?![\w.-])~i';
            $value = (string) preg_replace($pattern, $base, $value, -1, $count);
            $total += $count;
        }

        return [$value, $total];
    }

    /**
     * The source, the record, the field as it is now, the stand hosts and the site's origin —
     * or null when any of them is gone.
     *
     * @return array{AuditContentSource, ContentRecord, ContentField, list<string>, string}|null
     */
    private function target(Finding $finding): ?array
    {
        $parts = explode('|', $finding->key);

        if ($finding->runId === null || count($parts) !== 4) {
            return null;
        }

        [$sourceId, $recordId, $fieldName, $locale] = $parts;
        $source = $this->sources->get($sourceId);

        if ($source === null) {
            return null;
        }

        $hosts = ContentUrl::query()
            ->where('run_id', $finding->runId)
            ->where('source', $sourceId)
            ->where('record_id', $recordId)
            ->where('field', $fieldName)
            ->where('host_class', HostClassifier::DEV)
            ->distinct()
            ->pluck('host')
            ->map(static fn (mixed $host): string => (string) $host)
            ->filter()
            ->values()
            ->all();

        $record = $hosts === [] ? null : $source->find($recordId);

        if ($record === null) {
            return null;
        }

        foreach ($source->fields($record) as $field) {
            if ($field->name === $fieldName && (string) $field->locale === $locale) {
                return [$source, $record, $field, $hosts, $this->origin($finding->runId)];
            }
        }

        return null;
    }

    /** `https://shop.com` — of the run, or of the settings when the run is gone. */
    private function origin(int $runId): string
    {
        $base = AuditRun::query()->whereKey($runId)->value('base_url');
        $parts = parse_url(is_string($base) && $base !== '' ? $base : $this->settings->baseUrl());
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'localhost');

        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }
}
