<?php

declare(strict_types=1);

namespace WebxUi\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Localization\HasCodes;
use WebxUi\Localization\HasTranslations;
use WebxUi\Orders\Checkout\Steps\CommentStep;
use WebxUi\Orders\OrderFields;

/**
 * An extra field of the checkout (WEBX_UI_ORDER_FIELDS.md §2.1): what the administrator asks the
 * buyer on top of the contacts — a checkbox, a line, a text or a list of one choice — and where.
 *
 * Orders never read it: they keep a snapshot of the label and the value (§2.2), so renaming,
 * switching off or deleting a field leaves the orders placed as they were. Written only through
 * {@see OrderFields}, which holds the rules of §7.3.
 *
 * @property int $id
 * @property string $key
 * @property string $type
 * @property string $place
 * @property mixed $label
 * @property mixed $hint
 * @property list<array{value: string, label: array<string, string>}>|null $options
 * @property bool $required
 * @property bool $active
 * @property int|null $parent_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OrderField|null $parent
 */
class OrderField extends Model
{
    use HasCodes;
    use HasTranslations;
    use RecordsHistory;

    /** The journal's type of a definition (§7.3). */
    public const string TYPE = 'orders.field';

    public const string CHECKBOX = 'checkbox';

    public const string TEXT = 'text';

    public const string TEXTAREA = 'textarea';

    public const string SELECT = 'select';

    /** @var list<string> */
    public const array TYPES = [self::CHECKBOX, self::TEXT, self::TEXTAREA, self::SELECT];

    /** The key of a field, and of an option of a list: what a variable of a letter may be named (§5.3). */
    public const string KEY = '/^[a-z][a-z0-9_]{0,31}$/';

    /** How many definitions a site keeps, and options a list (§2.1): read whole on every checkout request. */
    public const int MAX_FIELDS = 30;

    public const int MAX_OPTIONS = 50;

    /** The longest label and hint, a language (§2.1). */
    public const int MAX_LABEL = 190;

    public const int MAX_HINT = 500;

    /** The longest answer to a line and to a text (§3.1): a name, and a comment of the checkout. */
    public const int MAX_TEXT = 190;

    public const int MAX_TEXTAREA = CommentStep::MAX;

    /**
     * The codes a step answers about an extra field (§3.4), and in English when: one for all the
     * fields, whose key is the error's `field` — the shop names its fields, the codes are fixed.
     *
     * @var array<string, string>
     */
    public const array ERROR_CODES = [
        'fields.required' => 'a required field is empty, or a required checkbox is not ticked',
        'fields.invalid' => 'the answer is too long or not of the type of the field',
        'fields.option_gone' => 'the option chosen is no longer one of the list',
    ];

    protected $table = 'order_fields';

    protected $guarded = [];

    /** Typed by the administrator: «Время доставки» is kept as `vremya_dostavki`, not refused (#125). */
    protected static function codes(): array
    {
        return ['key' => ['max' => 32, 'separator' => '_', 'lead' => 'field']];
    }

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['label', 'hint'];
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'active' => 'boolean',
            'parent_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<OrderField, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<OrderField, $this> */
    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The values of its options in their order; empty for anything but a list.
     *
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_values(array_map(static fn (array $option): string => (string) $option['value'], $this->options ?? []));
    }
}
