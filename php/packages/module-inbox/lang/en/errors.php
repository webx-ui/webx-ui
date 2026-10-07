<?php

declare(strict_types=1);

return [
    'refused' => 'This form could not be sent. Please try again in a moment.',
    'too-many' => 'Too many submissions from this address. Please try again in a minute.',
    'form-has-submissions' => 'The form “:form” has submissions, so it can be switched off but not deleted.',
    'status-in-use' => 'Submissions are still in the status “:status”, so it cannot be deleted.',
    'no-statuses' => 'There is no status to give a new submission. Run the migrations.',
    'file-missing' => 'This file is no longer here.',

    'slug-shape' => 'An address is made of lower-case letters, digits and hyphens: “contact-us”.',
    'field-name-shape' => 'A name starts with a letter and may hold letters, digits, hyphens and underscores.',
    'recipient-shape' => 'Recipient :entry is neither an administrator nor an e-mail address.',
    'recipient-unknown' => 'Recipient :entry names an administrator who does not exist.',
    'choices-required' => 'A list field needs at least one choice, each with a value.',
    'email-field' => 'The reply-to field has to be an e-mail field of this form.',
    'no-such-answer' => 'This submission has no answer under that name.',
    'not-correctable' => 'A file or a consent cannot be corrected.',
    'nobody-to-notify' => 'The form “:form” names nobody to write to.',
];
