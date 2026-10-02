<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Audit\Contracts\AuditContentSource;

/** A content source over an array: id → [label, published, fields by name]. */
final class FakeContentSource implements AuditContentSource
{
    /** @var array<array-key, array{label: string, published: bool, fields: array<string, string>, draft?: array<string, string>}> */
    public array $records = [];

    /** @var list<array{record: string, field: string, value: string}> */
    public array $replaced = [];

    public function id(): string
    {
        return 'posts';
    }

    public function records(): iterable
    {
        foreach ($this->records as $id => $record) {
            yield new ContentRecord((string) $id, $record['label'], $record['published'], '/posts/'.$id);
        }
    }

    public function find(string $id): ?ContentRecord
    {
        $record = $this->records[$id] ?? null;

        return $record === null ? null : new ContentRecord($id, $record['label'], $record['published'], '/posts/'.$id);
    }

    public function fields(ContentRecord $record): iterable
    {
        foreach ($this->records[$record->id]['fields'] as $name => $value) {
            yield new ContentField($name, $value, 'en');
        }

        foreach ($this->records[$record->id]['draft'] ?? [] as $name => $value) {
            yield new ContentField('draft.'.$name, $value, 'en', false);
        }
    }

    public function replace(ContentRecord $record, ContentField $field, string $value): void
    {
        $this->replaced[] = ['record' => $record->id, 'field' => $field->name, 'value' => $value];
    }
}
