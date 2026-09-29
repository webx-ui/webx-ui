<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Contracts\HasPermissions;

/**
 * A kind of record the journal is kept for, as the module that owns it described it.
 *
 * The labels are what makes the journal readable — "Price", not `price` — and they are
 * translation keys or plain words, resolved when the journal is read, in the reader's language.
 * The permission is what makes it safe: the history of a record says as much as the record
 * does, so it is behind the same right the record's list is.
 */
final readonly class HistoryType
{
    /**
     * @param  class-string<Model>|null  $model
     * @param  array<string, string>  $fields  field → label (a translation key or words)
     * @param  list<string>  $permissions  any of them lets a reader see this type's history
     */
    public function __construct(
        public string $type,
        public ?string $model,
        public array $fields,
        public array $permissions,
        public string $module,
        public ?string $label = null,
    ) {}

    /**
     * The label of a field, with the language after it for a translated one: `name.ru` reads
     * "Name (RU)". A field nobody labelled is shown by its name — better than hiding a change.
     */
    public function label(string $field, ?string $stored = null): string
    {
        if (isset($this->fields[$field])) {
            return self::words($this->fields[$field]);
        }

        // Whoever wrote the row by hand named the field already, and knew better than a guess.
        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        [$base, $locale] = str_contains($field, '.') ? explode('.', $field, 2) : [$field, null];
        $label = isset($this->fields[$base]) ? self::words($this->fields[$base]) : $base;

        return $locale === null ? $label : $label.' ('.strtoupper($locale).')';
    }

    /**
     * Whether this reader may see the history of this type. A reader who carries no
     * permissions at all is refused, the way the notes are: the panel's API always has an
     * administrator behind it, and an anonymous one is a misconfigured door.
     */
    public function allows(mixed $reader): bool
    {
        if (! $reader instanceof HasPermissions) {
            return false;
        }

        foreach ($this->permissions as $permission) {
            if ($reader->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What an agent is told about the type: enough to know what to ask.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $fields = [];

        foreach (array_keys($this->fields) as $field) {
            $fields[$field] = $this->label($field);
        }

        return [
            'type' => $this->type,
            'module' => $this->module,
            'label' => $this->label === null ? $this->type : self::words($this->label),
            'fields' => $fields,
            'permissions' => $this->permissions,
        ];
    }

    private static function words(string $label): string
    {
        $translated = __($label);

        return is_string($translated) ? $translated : $label;
    }
}
