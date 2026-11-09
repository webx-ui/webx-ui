<?php

declare(strict_types=1);

return [
    'refused' => 'This form could not be sent. Please try again in a moment.',
    'enclosure' => 'What was attached to this form cannot be sent with it. Reload the page and try again.',
    'captcha' => 'Please confirm you are not a robot and send the form again.',
    'captcha-script' => 'This form checks that you are not a robot with JavaScript. Please turn it on and send the form again.',
    'captcha-unavailable' => 'We could not check that you are not a robot. Please reload the page or try another browser.',
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
    'condition-unknown-field' => 'The condition “:key” of “:field” names “:name”, a field this form does not have (or that this change removes).',
    'condition-not-choice' => 'The condition “:key” of “:field” names “:name”, which is not a choice: only a list, radio buttons, ticks or a consent above it can be a condition.',
    'condition-not-before' => 'The condition “:key” of “:field” names “:name”, which does not stand above it. A condition names only a field above.',
    'condition-unknown-value' => 'The condition “:key” of “:field” counts on the value “:value” of “:name”, which that field does not offer.',
    'field-has-dependents' => 'The field “:field” is named in the conditions of :fields. Change those conditions first.',
    'step-empty' => 'The step :field has no field after it. Put a field under it or remove it.',
    'step-too-many' => 'A form has at most :max steps.',
    'step-unknown' => 'The form has changed since the page was opened. Reload the page.',
];
