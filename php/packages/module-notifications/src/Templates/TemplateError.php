<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Templates;

use InvalidArgumentException;

/**
 * A template Mustache cannot read, or one using what is refused (§7): a dictionary key under
 * `webx-notifications::errors` and its replacements, for the words under the field.
 */
final class TemplateError extends InvalidArgumentException
{
    /**
     * @param  array<string, string>  $replace
     */
    public function __construct(public readonly string $key, public readonly array $replace = [])
    {
        parent::__construct($key.($replace === [] ? '' : ' '.json_encode($replace)));
    }

    /** In the words of the panel. */
    public function words(): string
    {
        $line = __('webx-notifications::errors.'.$this->key, $this->replace);

        return is_string($line) ? $line : $this->key;
    }
}
