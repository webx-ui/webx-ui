<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

/**
 * A type whose settings are right or wrong only together: a hold and instalments in one method
 * (spec §6.4, §13.3). Optional: without it each field is checked on its own and nothing more.
 */
interface ValidatesSettings extends MethodType
{
    /**
     * Called by the only door to the settings, `MethodForm::save()`, with the settings as they will
     * be stored: what was sent over what is kept, gateway's fields included, keys never. An empty
     * answer saves; otherwise nothing is written and the panel and the agent get the fields.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string> field of `settings()` => words, or a key of the type's dictionary
     */
    public function validateSettings(array $settings): array;
}
