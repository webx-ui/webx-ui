<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * Whether a field's rules make it required whatever else the form holds — the one case the
 * panel marks with an asterisk.
 *
 * Only the bare `required` counts. `required_without:email`, `required_if:…` and a rule object
 * depend on the rest of the form, and a star on «phone» of «a phone or an e-mail» would tell
 * the editor that the phone cannot be left out, which is not true: such a pair gets a hint
 * instead.
 */
final class Required
{
    /**
     * @param  string|array<int|string, mixed>|null  $rules  Laravel's rules for one field: a list, or a `|` string.
     */
    public static function among(string|array|null $rules): bool
    {
        if ($rules === null) {
            return false;
        }

        foreach (is_string($rules) ? explode('|', $rules) : $rules as $rule) {
            if (is_string($rule) && trim($rule) === 'required') {
                return true;
            }
        }

        return false;
    }
}
