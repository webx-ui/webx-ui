<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * A model whose saves are written into the journal (§4).
 *
 * One line in the model and one `HistoryTypes::register()` in the module's provider. Each save
 * becomes one row with the fields it really changed; creating, deleting and restoring are rows
 * without changes. Publishing is the module's word, not a column's, so it is written by the
 * module with `History::record($model, 'published')`.
 *
 * What is compared is the attribute through the model's own cast on both sides, so a boolean
 * saved as `1` over `true` is no change. A translated field is compared language by language
 * and written as `name.ru`; an encrypted one and anything in `$hidden` are never written — a
 * journal readable by every editor is not the place for a password hash or a token.
 *
 * Which fields: `historyFields()` names them outright; otherwise every attribute but the ones
 * `historySkipped()` names and the global `webx-admin.history.skip_fields` (timestamps, the
 * tree's bounds). `historyValue()` is where a model turns an id into words — a picture's name
 * rather than its key.
 *
 * Registered with `static::created()` and the rest rather than an observer: `observe()` inside a
 * trait's boot builds the model while it is booting (docs/pitfalls/laravel-and-php.md).
 *
 * @mixin Model
 */
trait RecordsHistory
{
    public static function bootRecordsHistory(): void
    {
        static::created(static function (Model $model): void {
            /** @var Model&self $model */
            $model->writeHistory(HistoryEntry::CREATED);
        });

        static::updated(static function (Model $model): void {
            /** @var Model&self $model */
            $model->writeHistory(HistoryEntry::UPDATED, $model->historyChanges());
        });

        static::deleted(static function (Model $model): void {
            /** @var Model&self $model */
            $model->writeHistory(HistoryEntry::DELETED);
        });

        // Only a model with `SoftDeletes` ever fires it; registering it for the rest is harmless.
        static::registerModelEvent('restored', static function (Model $model): void {
            /** @var Model&self $model */
            $model->writeHistory(HistoryEntry::RESTORED);
        });
    }

    /**
     * The fields the journal follows; null for every attribute but the skipped ones.
     *
     * @return list<string>|null
     */
    public function historyFields(): ?array
    {
        return null;
    }

    /**
     * Fields of this model that are never written, on top of the global list.
     *
     * @return list<string>
     */
    public function historySkipped(): array
    {
        return [];
    }

    /** How a value is shown in the journal: an id made into a name, say. */
    public function historyValue(string $field, mixed $value): mixed
    {
        return $value;
    }

    /**
     * What the save that just happened changed, from the model's own state: `getChanges()` holds
     * the new values and the original is not synced until after the `updated` event.
     *
     * @return list<array{field: string, from: mixed, to: mixed}>
     */
    public function historyChanges(): array
    {
        $changes = [];

        foreach (array_keys($this->getChanges()) as $field) {
            if (! $this->followsInHistory($field)) {
                continue;
            }

            $from = $this->historyCast($field, $this->getRawOriginal($field));
            $to = $this->historyCast($field, $this->getAttributes()[$field] ?? null);

            if ($this->historyTranslated($field) && (is_array($from) || is_array($to))) {
                $from = is_array($from) ? $from : [];
                $to = is_array($to) ? $to : [];

                foreach (array_unique([...array_keys($from), ...array_keys($to)]) as $locale) {
                    $changes[] = [
                        'field' => $field.'.'.$locale,
                        'from' => $this->historyValue($field, $from[$locale] ?? null),
                        'to' => $this->historyValue($field, $to[$locale] ?? null),
                    ];
                }

                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => $this->historyValue($field, $from),
                'to' => $this->historyValue($field, $to),
            ];
        }

        return $changes;
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    protected function writeHistory(string $event, array $changes = []): void
    {
        Container::getInstance()->make(Journal::class)->record($this, $event, $changes);
    }

    private function followsInHistory(string $field): bool
    {
        $only = $this->historyFields();

        if ($only !== null) {
            return in_array($field, $only, true);
        }

        $skipped = [
            ...(array) Container::getInstance()->make('config')->get('webx-admin.history.skip_fields', []),
            ...$this->historySkipped(),
            ...$this->getHidden(),
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ];

        if (in_array($field, $skipped, true)) {
            return false;
        }

        $cast = $this->getCasts()[$field] ?? null;

        return ! (is_string($cast) && str_starts_with($cast, 'encrypted'));
    }

    private function historyTranslated(string $field): bool
    {
        return self::translatedIn($this, $field);
    }

    /**
     * Asked of the model as a model, not as `$this`: the trait does not require
     * `HasTranslations`, and a model without it has no languages to compare.
     */
    private static function translatedIn(Model $model, string $field): bool
    {
        return method_exists($model, 'isTranslatableAttribute') && $model->isTranslatableAttribute($field);
    }

    /**
     * A raw value through the model's cast, without its accessors: an accessor is how the model
     * shows a value, and a translated field's accessor answers in one language.
     */
    private function historyCast(string $field, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return $this->hasCast($field) ? $this->castAttribute($field, $raw) : $raw;
    }
}
