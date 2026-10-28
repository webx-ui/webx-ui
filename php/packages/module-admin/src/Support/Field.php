<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

/**
 * One setting of a provider a module plugs in: a bot's token, an SMTP host, the client id of a
 * social network. One class for every module that keeps such settings (`module-notifications`
 * channels, `module-users` sign-in providers), so the panel draws them alike.
 *
 * `secret` decides where it lives: in `credentials`, encrypted and answered only with "it is set",
 * or in `options`, open to the form and to the journal.
 */
final readonly class Field
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const CHOICE = 'choice';

    public const EMAIL = 'email';

    /**
     * @param  list<string>  $choices  the values a `choice` may take
     */
    public function __construct(
        public string $name,
        public string $label,
        public bool $secret = false,
        public string $kind = self::TEXT,
        public array $choices = [],
        public mixed $default = null,
        public int $max = 255,
    ) {}

    /**
     * For the panel's meta and the agent's resource: never a value.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => (string) __($this->label),
            'secret' => $this->secret,
            'kind' => $this->kind,
            'choices' => $this->choices,
            'default' => $this->default,
        ];
    }
}
