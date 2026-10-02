<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Audit;

use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Audit\Contracts\AuditContentSource;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;

/**
 * The regions' text for the site audit (§7 of the audit spec): the header and the footer are on
 * every page, so a stand address in one of them is on every page too. The published tree and the
 * draft are both searched.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class RegionContentSource implements AuditContentSource
{
    public function __construct(private readonly Regions $regions) {}

    public function id(): string
    {
        return 'regions';
    }

    public function records(): iterable
    {
        foreach (Region::query()->lazyById(100) as $region) {
            yield $this->record($region);
        }
    }

    public function find(string $id): ?ContentRecord
    {
        $region = Region::query()->where('name', $id)->first();

        return $region instanceof Region ? $this->record($region) : null;
    }

    private function record(Region $region): ContentRecord
    {
        $name = (string) $region->getAttribute('name');

        return new ContentRecord(
            $name,
            $this->regions->has($name) ? $this->regions->title($name) : $name,
            $region->isPublished(),
            '/regions/'.$name,
            $region,
        );
    }

    public function fields(ContentRecord $record): iterable
    {
        $region = $record->subject;

        if (! $region instanceof Region) {
            return;
        }

        $blocks = $region->getAttribute($region->blocksColumn());

        if (is_array($blocks) && $blocks !== []) {
            yield new ContentField('blocks', ContentField::json($blocks));
        }

        $draft = $region->draftValues();

        if (is_array($draft['blocks'] ?? null) && $draft['blocks'] !== []) {
            yield new ContentField('draft.blocks', ContentField::json($draft['blocks']), published: false);
        }
    }

    public function replace(ContentRecord $record, ContentField $field, string $value): void
    {
        $region = $record->subject;
        $decoded = json_decode($value, true);

        if (! $region instanceof Region || ! is_array($decoded)) {
            return;
        }

        if ($field->name === 'draft.blocks') {
            $region->saveDraft([...$region->draftValues(), 'blocks' => $decoded]);

            return;
        }

        if ($field->name === 'blocks') {
            $region->setAttribute($region->blocksColumn(), $decoded);
            $region->save();
        }
    }
}
