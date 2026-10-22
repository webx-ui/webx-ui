<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionValue;

/**
 * The visitor's own answers in the thank-you: `{{field.<name>}}` in `thank-you.heading` and
 * `thank-you.text`, the way the templates of the channels have them ("Thanks, {{field.name}}").
 *
 * A value is escaped and wrapped in `<em class="webx-form__answer-value">`, so a theme can set it
 * apart; an empty answer, a field the form does not have and a file are nothing at all. A heading
 * with nothing to fill in stays the plain text it was — only one with a value in it becomes HTML.
 */
final class ThankYou
{
    /** The class of a value put in: the widgets' form shell names it in its contract. */
    public const string VALUE = 'webx-form__answer-value';

    private const string PATTERN = '/\{\{\s*field\.([a-z0-9_]+)\s*\}\}/';

    /**
     * @param  array<string, string>  $answers  by the field's machine name
     */
    public static function heading(?string $template, array $answers): HtmlString|string|null
    {
        if ($template === null || preg_match(self::PATTERN, $template) !== 1) {
            return $template;
        }

        $parts = preg_split(self::PATTERN, $template, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $html = '';

        // Text and names take turns: the odd parts are what the pattern captured.
        foreach ($parts as $index => $part) {
            $html .= $index % 2 === 0 ? e($part) : self::value($answers[$part] ?? '');
        }

        return new HtmlString($html);
    }

    /**
     * The text is the panel's rich text, HTML already: only the values are escaped.
     *
     * The site's shortcodes in it are printed first, and the visitor's answers go in after: a
     * `[phone]` the administrator wrote is the number, one the visitor typed stays as typed.
     *
     * @param  array<string, string>  $answers
     */
    public static function text(?string $html, array $answers): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = Container::getInstance()->make(Shortcodes::class)->htmlIn($html);

        return (string) preg_replace_callback(self::PATTERN, static fn (array $match): string => self::value($answers[$match[1]] ?? ''), $html);
    }

    /**
     * What the submission written for the visitor says — choices as their labels, a consent in
     * words, the way the letter has them.
     *
     * @return array<string, string>
     */
    public static function of(Submission $submission): array
    {
        $answers = [];

        foreach ($submission->values as $answer) {
            if ($answer->type === FieldType::File->value) {
                continue;
            }

            $answers[(string) $answer->name] = (string) SubmissionValue::read($answer->type, $answer->value);
        }

        return $answers;
    }

    /**
     * What was sent, as it was sent: for a submission the antispam dropped, which is thanked all
     * the same — a thank-you without the name in it would tell the robot it was seen.
     *
     * @return array<string, string>
     */
    public static function sent(Form $form, Request $request): array
    {
        $answers = [];

        foreach ($form->liveFields as $field) {
            $value = $request->input('fields.'.$field->key());

            if ($field->type !== FieldType::File && is_scalar($value)) {
                $answers[$field->key()] = (string) $value;
            }
        }

        return $answers;
    }

    private static function value(string $value): string
    {
        $value = trim($value);

        return $value === '' ? '' : '<em class="'.self::VALUE.'">'.e($value).'</em>';
    }
}
