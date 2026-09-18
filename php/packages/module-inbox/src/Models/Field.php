<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Localization\HasTranslations;

/**
 * One question of a form.
 *
 * @property int $id
 * @property int $form_id
 * @property string|null $name
 * @property FieldType $type
 * @property mixed $title
 * @property mixed $placeholder
 * @property mixed $help
 * @property array<string, mixed> $options
 * @property bool $is_enabled
 * @property bool $is_required
 * @property bool $is_fullsize
 * @property bool $in_table
 * @property int $position
 * @property Carbon|null $deleted_at
 */
class Field extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'inbox_form_fields';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title', 'placeholder', 'help'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'options' => 'array',
            'is_enabled' => 'boolean',
            'is_required' => 'boolean',
            'is_fullsize' => 'boolean',
            'in_table' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    /**
     * What the field is called in the HTML and in everything downstream of it: `fields[email]`
     * for a named field, `fields[f17]` for one nobody bothered to name (§2.6).
     */
    public function key(): string
    {
        $name = trim((string) $this->name);

        return $name !== '' ? $name : 'f'.$this->getKey();
    }

    /** One option of §4, with a default for the forms that predate it. */
    public function option(string $key, mixed $default = null): mixed
    {
        $options = $this->options ?? [];

        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    /**
     * The written-down answers, as `[value => label]` in the language being read.
     *
     * @return array<string, string>
     */
    public function choices(): array
    {
        if (! $this->type->hasChoices()) {
            return [];
        }

        $choices = [];

        foreach ((array) $this->option('choices', []) as $choice) {
            if (! is_array($choice) || ! isset($choice['value'])) {
                continue;
            }

            $choices[(string) $choice['value']] = $this->label($choice);
        }

        return $choices;
    }

    /** Whether one answer to this field may be several things. */
    public function isMultiple(): bool
    {
        return $this->type->isMultiple()
            || ($this->type === FieldType::File && (bool) $this->option('multiple', false));
    }

    /**
     * The label of a choice, which is translated the way everything else here is: a map of
     * languages, or a plain string from a form written before the site had a second one.
     *
     * @param  array<string, mixed>  $choice
     */
    private function label(array $choice): string
    {
        $label = $choice['label'] ?? $choice['value'];

        if (! is_array($label)) {
            return (string) $label;
        }

        $locale = app()->getLocale();

        foreach ([$locale, config('app.fallback_locale')] as $candidate) {
            if (isset($label[$candidate]) && $label[$candidate] !== '') {
                return (string) $label[$candidate];
            }
        }

        $first = array_filter($label, static fn ($value): bool => is_scalar($value) && (string) $value !== '');

        return $first === [] ? (string) $choice['value'] : (string) reset($first);
    }
}
