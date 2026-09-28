<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Support;

/**
 * Where on the site a form stands — `footer`, `article`, `sidebar` (layout regions §11).
 *
 * One form of subscription in the footer and in an article is one form, and this is the word
 * that tells the two apart: in a class on `<form>`, so the site styles each by CSS alone, and
 * in the submission, so the panel can say which of the two brought it in.
 *
 * It is a name and nothing else. It goes into a class and into a column of 32, and it arrives
 * the second time from the visitor's browser — so the same rule is applied at both ends.
 */
final class Placement
{
    /** The shape of a name: a letter, then letters, digits and dashes, 32 at most. */
    public const PATTERN = '/^[a-z][a-z0-9-]{0,31}$/';

    /**
     * What a filter says for "the page did not say". Reserved rather than invented per
     * caller, so that the list, the agent and the component agree on it: a form placed
     * `none` stands nowhere in particular, which is what null already means.
     */
    public const NONE = 'none';

    /** The value when it is a name, and null when it is anything else — nothing, or a typo. */
    public static function of(mixed $value): ?string
    {
        return is_string($value) && $value !== self::NONE && preg_match(self::PATTERN, $value) === 1 ? $value : null;
    }
}
