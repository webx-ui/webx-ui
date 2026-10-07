<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Audit;

use WebxUi\Inbox\Models\Form;

/**
 * @internal
 */
final class FormTitle
{
    /** The name in the language the audit is read in, or the slug where it has none. */
    public static function of(Form $form): string
    {
        $title = $form->getTranslation('title', app()->getLocale());

        return is_string($title) && $title !== '' ? $title : $form->slug;
    }
}
