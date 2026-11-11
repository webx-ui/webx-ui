<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Exceptions;

/**
 * A field that the conditions of other fields name is not deleted (FORM_STEPS §4.2): they would
 * be left counting on an answer nobody can give, and quietly never be shown again.
 */
final class FieldHasDependents extends InboxException
{
    /**
     * @param  list<string>  $dependents
     */
    public function __construct(string $field, public readonly array $dependents)
    {
        parent::__construct((string) trans('webx-inbox::errors.field-has-dependents', [
            'field' => $field,
            'fields' => '“'.implode('”, “', $dependents).'”',
        ]), 'field');
    }
}
