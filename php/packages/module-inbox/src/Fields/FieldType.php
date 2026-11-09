<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

/**
 * What a field is, which is mostly what it looks like in HTML and how its answer is checked.
 *
 * The reference implementation had four of these; the rest are here because the four always
 * ended with somebody asking a question in a `textarea` that a `select` would have answered.
 *
 * A type may be changed after submissions have arrived. Nothing happens to them: every answer
 * carries the type it was given under (§2.2), so a year-old submission is read the way it was
 * written.
 */
enum FieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Tel = 'tel';
    case Textarea = 'textarea';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Consent = 'consent';
    case Date = 'date';
    case File = 'file';
    case Hidden = 'hidden';
    // Not a question: the line that starts a step of the form (FORM_STEPS §3.1). The fields after
    // it, up to the next one, are its step; it is never answered and never a column.
    case Step = 'step';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** Types whose answer is one of a written-down list. */
    public function hasChoices(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox], true);
    }

    /** Whether a visitor answers it: everything but the line that starts a step. */
    public function isQuestion(): bool
    {
        return $this !== self::Step;
    }

    /** Types whose answer may be more than one thing. */
    public function isMultiple(): bool
    {
        return $this === self::Checkbox;
    }
}
