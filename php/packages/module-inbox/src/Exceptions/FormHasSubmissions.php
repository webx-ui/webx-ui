<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Exceptions;

/**
 * A form that has taken submissions is disabled, not deleted (§2.4).
 *
 * The reference implementation cascaded, and that is the whole reason this rule exists: one
 * tidy-up of an old form took every enquiry that had ever come through it.
 */
final class FormHasSubmissions extends InboxException
{
    public function __construct(string $slug)
    {
        parent::__construct((string) trans('webx-inbox::errors.form-has-submissions', ['form' => $slug]));
    }
}
