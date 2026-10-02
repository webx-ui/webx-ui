<?php

declare(strict_types=1);

namespace WebxUi\Pages\Audit;

use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Audit\Contracts\AuditContentSource;
use WebxUi\Pages\Models\Page;

/**
 * The pages' text for the site audit (§7 of the audit spec): the published blocks and whatever
 * the draft holds, so an address of a development stand is found before "Publish" puts it on
 * the site. Pages in the bin are left out — nothing brings them to the site but a restore.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class PageContentSource implements AuditContentSource
{
    public function id(): string
    {
        return 'pages';
    }

    public function records(): iterable
    {
        foreach (Page::query()->lazyById(100) as $page) {
            yield $this->record($page);
        }
    }

    public function find(string $id): ?ContentRecord
    {
        $page = ctype_digit($id) ? Page::query()->find((int) $id) : null;

        return $page instanceof Page ? $this->record($page) : null;
    }

    private function record(Page $page): ContentRecord
    {
        $title = $page->getTranslation('title');

        return new ContentRecord(
            (string) $page->getKey(),
            is_string($title) && $title !== '' ? $title : '#'.$page->getKey(),
            $page->isVisible(),
            '/pages/'.$page->getKey(),
            $page,
        );
    }

    public function fields(ContentRecord $record): iterable
    {
        $page = $record->subject;

        if (! $page instanceof Page) {
            return;
        }

        $blocks = $page->getAttribute($page->blocksColumn());

        if (is_array($blocks) && $blocks !== []) {
            yield new ContentField('blocks', ContentField::json($blocks));
        }

        foreach ($page->draftValues() as $name => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                yield new ContentField('draft.'.$name, is_string($value) ? $value : ContentField::json($value), published: false);
            }
        }
    }

    public function replace(ContentRecord $record, ContentField $field, string $value): void
    {
        $page = $record->subject;

        if (! $page instanceof Page) {
            return;
        }

        $decoded = json_decode($value, true);

        if (str_starts_with($field->name, 'draft.')) {
            $draft = $page->draftValues();
            $draft[substr($field->name, strlen('draft.'))] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            $page->saveDraft($draft);

            return;
        }

        if ($field->name === 'blocks' && is_array($decoded)) {
            $page->setAttribute($page->blocksColumn(), $decoded);
            $page->save();
        }
    }
}
