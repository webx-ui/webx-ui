<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Orders\Models\OrderField;

/**
 * A change of the checkout's extra fields that breaks a rule of a definition
 * (WEBX_UI_ORDER_FIELDS.md §7.3): nothing was written, and `plan` is what a dry run would have
 * answered — its `field` names the input the refusal is about, so the panel shows it there.
 */
final class FieldRefused extends Refusal
{
    public const string KEY_INVALID = 'key_invalid';

    public const string KEY_TAKEN = 'key_taken';

    /** The key and the type never change once a field is saved. */
    public const string LOCKED = 'locked';

    /** The place is not in the registry `FieldPlaces`. */
    public const string PLACE_INVALID = 'place_invalid';

    public const string NO_LABEL = 'no_label';

    /** A list without options, or options on a field that is not a list. */
    public const string NO_OPTIONS = 'no_options';

    public const string OPTION_INVALID = 'option_invalid';

    /** The parent is not a checkbox, depends itself, stands elsewhere, or the field has dependents. */
    public const string PARENT_INVALID = 'parent_invalid';

    /** A checkbox with fields shown behind it is not deleted: `plan->dependents` names them. */
    public const string HAS_DEPENDENTS = 'has_dependents';

    /** More than `OrderField::MAX_FIELDS` fields or `MAX_OPTIONS` options. */
    public const string TOO_MANY = 'too_many';

    /** An order that does not name every field exactly once. */
    public const string ARRANGEMENT = 'arrangement';

    /** @var list<string> */
    public const array CODES = [
        self::KEY_INVALID, self::KEY_TAKEN, self::LOCKED, self::PLACE_INVALID, self::NO_LABEL, self::NO_OPTIONS,
        self::OPTION_INVALID, self::PARENT_INVALID, self::HAS_DEPENDENTS, self::TOO_MANY, self::ARRANGEMENT,
    ];

    public function __construct(public readonly FieldPlan $plan)
    {
        $reason = (string) $plan->refusal;

        parent::__construct($reason, match ($reason) {
            self::KEY_INVALID => 'The key is a lowercase letter, then letters, digits or `_`, at most 32.',
            self::KEY_TAKEN => 'Another field has this key.',
            self::LOCKED => 'The key and the type of a field never change: delete it and add another.',
            self::PLACE_INVALID => 'No such place of the checkout.',
            self::NO_LABEL => 'The field needs a label in the main language of the site.',
            self::NO_OPTIONS => 'A list needs its options, and only a list has them.',
            self::OPTION_INVALID => 'Each option needs a key of its own by the rule of a field key and a label in the main language.',
            self::PARENT_INVALID => 'A field is shown behind a checkbox of the same place that is not shown behind another one itself.',
            self::HAS_DEPENDENTS => 'Fields are shown behind this checkbox: '.implode(', ', $plan->dependents).'.',
            self::TOO_MANY => sprintf('At most %d fields, and %d options a list.', OrderField::MAX_FIELDS, OrderField::MAX_OPTIONS),
            default => 'The order names every field, each once.',
        });
    }
}
