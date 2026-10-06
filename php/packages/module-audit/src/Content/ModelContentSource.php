<?php

declare(strict_types=1);

namespace WebxUi\Audit\Content;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Contracts\AuditContentSource;

/**
 * A content source made of Eloquent models, for a module whose records are rows with text in
 * columns: name the models and the columns, and the rest is here — every locale of a translated
 * column, arrays as JSON, the draft beside the published value, the write back through the model.
 *
 * The models are read by what they can do, not by what they are: `getTranslations()` for a
 * translated column, `draftValues()`/`saveDraft()` for a draft, `isVisible()` or a `published`
 * column for whether the site shows a record. So a module needs nothing of the audit but this
 * class, and only when the audit is installed. Records in the bin are left out by the models'
 * own soft-delete scope — nothing brings them to the site but a restore.
 */
abstract class ModelContentSource implements AuditContentSource
{
    /**
     * The models, by the prefix their record ids get: one model under '' keeps the plain key; a
     * module with two (outlets and their articles) names each, so `find()` knows which to ask.
     *
     * @return array<string, class-string<Model>>
     */
    abstract protected function models(): array;

    /**
     * The columns that hold text a visitor reads or an address a link follows.
     *
     * @return list<string>
     */
    abstract protected function columns(Model $model): array;

    /** Where the panel edits the record, relative to the panel: `/services/12`, `/faq?question=4`. */
    abstract protected function editUrl(Model $model): ?string;

    public function records(): iterable
    {
        foreach ($this->models() as $prefix => $class) {
            foreach ($class::query()->lazyById(100) as $model) {
                yield $this->record($prefix, $model);
            }
        }
    }

    public function find(string $id): ?ContentRecord
    {
        [$prefix, $key] = str_contains($id, ':') ? explode(':', $id, 2) : ['', $id];
        $class = $this->models()[$prefix] ?? null;

        if ($class === null || ! ctype_digit($key)) {
            return null;
        }

        $model = $class::query()->find((int) $key);

        return $model instanceof Model ? $this->record($prefix, $model) : null;
    }

    public function fields(ContentRecord $record): iterable
    {
        $model = $record->subject;

        if (! $model instanceof Model) {
            return;
        }

        foreach ($this->columns($model) as $column) {
            if ($this->translated($model, $column) && method_exists($model, 'getTranslations')) {
                foreach ((array) $model->getTranslations($column) as $locale => $value) {
                    if (self::filled($value)) {
                        yield new ContentField($column, self::text($value), (string) $locale);
                    }
                }

                continue;
            }

            $value = $model->getAttribute($column);

            if (self::filled($value)) {
                yield new ContentField($column, self::text($value));
            }
        }

        foreach ($this->draft($model) as $column => $value) {
            if (in_array($column, $this->columns($model), true) && self::filled($value)) {
                yield new ContentField('draft.'.$column, self::text($value), published: false);
            }
        }
    }

    public function replace(ContentRecord $record, ContentField $field, string $value): void
    {
        $model = $record->subject;

        if (! $model instanceof Model) {
            return;
        }

        $decoded = json_decode($value, true);
        $structured = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;

        if (str_starts_with($field->name, 'draft.') && method_exists($model, 'saveDraft')) {
            $draft = $this->draft($model);
            $draft[substr($field->name, strlen('draft.'))] = $structured ?? $value;
            $model->saveDraft($draft);

            return;
        }

        if (! in_array($field->name, $this->columns($model), true)) {
            return;
        }

        if ($field->locale !== null && $this->translated($model, $field->name) && method_exists($model, 'setTranslation')) {
            $model->setTranslation($field->name, $field->locale, $structured ?? $value);
        } else {
            $model->setAttribute($field->name, $structured ?? $value);
        }

        $model->save();
    }

    /** What the panel calls the record: its title, name or question, in the site's language. */
    protected function label(Model $model): string
    {
        foreach (['title', 'name', 'question'] as $column) {
            $value = $this->translated($model, $column) && method_exists($model, 'getTranslation')
                ? $model->getTranslation($column)
                : $model->getAttribute($column);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#'.$model->getKey();
    }

    /** Whether the site shows the record: as the model says, or by its `published` column. */
    protected function published(Model $model): bool
    {
        if (method_exists($model, 'isVisible')) {
            return (bool) $model->isVisible();
        }

        $flag = $model->getAttribute('published');

        return $flag === null || (bool) $flag;
    }

    private function record(string $prefix, Model $model): ContentRecord
    {
        $key = (string) $model->getKey();

        return new ContentRecord(
            $prefix === '' ? $key : $prefix.':'.$key,
            $this->label($model),
            $this->published($model),
            $this->editUrl($model),
            $model,
        );
    }

    private function translated(Model $model, string $column): bool
    {
        return method_exists($model, 'isTranslatableAttribute') && $model->isTranslatableAttribute($column);
    }

    /**
     * @return array<string, mixed>
     */
    private function draft(Model $model): array
    {
        if (! method_exists($model, 'draftValues')) {
            return [];
        }

        $draft = $model->draftValues();

        return is_array($draft) ? $draft : [];
    }

    private static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    /** Text as it is; anything structured as the JSON the finder reads and `replace()` takes back. */
    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : ContentField::json($value);
    }
}
