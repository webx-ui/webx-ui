<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use WebxUi\Inbox\Fields\Conditions;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Fields\Summary;
use WebxUi\Localization\HasCodes;
use WebxUi\Localization\HasTranslations;
use WebxUi\Localization\Slug;

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
    use HasCodes;
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'inbox_form_fields';

    protected $guarded = [];

    /**
     * The machine name (§2.6), brought rather than refused (#125): a typed `_` stays and so does a
     * `-` — `vash_telefon` made out of a label and `step-contacts` written by hand are both kept —
     * any other gap is a `_`, the one the thank-you page reads (`{{field.vash_telefon}}`), and a
     * name that starts with a digit goes after `field`. A null name is left alone: it is the field
     * nobody named, `f17`.
     *
     * @return array<string, array{max: int, separator?: string, lead?: string}>
     */
    protected static function codes(): array
    {
        return ['name' => ['max' => 64, 'separator' => Slug::BOTH_UNDERSCORED, 'lead' => 'field']];
    }

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

    /**
     * A field renamed takes the form's Reply-To with it: `email_field` holds the name, and a
     * rename that left it behind would make every letter after it lose its Reply-To without a
     * word. The answers already received need nothing — they point at the field by id and keep
     * the name they arrived under.
     */
    protected static function booted(): void
    {
        static::updated(static function (Field $field): void {
            if (! $field->wasChanged('name')) {
                return;
            }

            $old = trim((string) $field->getOriginal('name'));
            $old = $old !== '' ? $old : 'f'.$field->getKey();
            $form = $field->form;

            if ($form instanceof Form && $form->option('email_field') === $old) {
                $form->options = [...($form->options ?? []), 'email_field' => $field->key()];
                $form->save();
            }

            // The conditions that name it follow it too (FORM_STEPS §4.2): left behind, they would
            // name a field that is not there, and their fields would never be shown again. So does a
            // summary that names it (§7).
            $others = self::query()->where('form_id', $field->form_id)->whereKeyNot($field->getKey())->get();

            foreach ($others as $other) {
                $options = $other->options ?? [];
                $renamed = Summary::rename(Conditions::rename($options, $old, $field->key()), $old, $field->key());

                if ($renamed !== $options) {
                    $other->options = $renamed;
                    $other->save();
                }
            }
        });
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

    /**
     * Drawn as cards (FORM_STEPS §5): a group of radios or ticks with `view: cards`.
     */
    public function isCards(): bool
    {
        return in_array($this->type, [FieldType::Radio, FieldType::Checkbox], true) && $this->option('view') === 'cards';
    }

    /**
     * The choices as a card shows them: the label and the description in the language being read,
     * the key of the picture. Only the look of the field — an answer keeps the label alone.
     *
     * @return list<array{value: string, label: string, description: ?string, image: ?string}>
     */
    public function cardChoices(): array
    {
        $cards = [];

        foreach ((array) $this->option('choices', []) as $choice) {
            if (! is_array($choice) || ! isset($choice['value'])) {
                continue;
            }

            $description = $this->pick($choice['description'] ?? null);
            $image = $choice['image']['path'] ?? null;

            $cards[] = [
                'value' => (string) $choice['value'],
                'label' => $this->label($choice),
                'description' => trim($description) !== '' ? $description : null,
                'image' => is_string($image) && $image !== '' ? $image : null,
            ];
        }

        return $cards;
    }

    /** Whether one answer to this field may be several things. */
    public function isMultiple(): bool
    {
        return $this->type->isMultiple()
            || ($this->type === FieldType::File && (bool) $this->option('multiple', false));
    }

    /**
     * A translated option, read in the language being read — the consent sentence, mostly.
     *
     * Options are a JSON column and not a translated one, so `HasTranslations` knows nothing
     * about what is inside them; the ones that hold words hold a map of languages, and this is
     * what reads it.
     */
    public function optionText(string $key, string $default = ''): string
    {
        $text = $this->pick($this->option($key));

        return $text !== '' ? $text : $default;
    }

    /**
     * The label of a choice, which is translated the way everything else here is: a map of
     * languages, or a plain string from a form written before the site had a second one.
     *
     * @param  array<string, mixed>  $choice
     */
    private function label(array $choice): string
    {
        $label = $this->pick($choice['label'] ?? $choice['value']);

        return $label !== '' ? $label : (string) $choice['value'];
    }

    /** One value of a map of languages, or the value itself when it is not one. */
    private function pick(mixed $value): string
    {
        if (! is_array($value)) {
            return is_scalar($value) ? (string) $value : '';
        }

        foreach ([app()->getLocale(), config('app.fallback_locale')] as $candidate) {
            if (is_string($candidate) && isset($value[$candidate]) && $value[$candidate] !== '') {
                return (string) $value[$candidate];
            }
        }

        // Written in one language and read in another the site does not have a word for: the
        // first thing anybody wrote beats an empty label.
        $written = array_filter($value, static fn ($one): bool => is_scalar($one) && (string) $one !== '');

        return $written === [] ? '' : (string) reset($written);
    }
}
