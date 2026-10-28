<?php

declare(strict_types=1);

namespace WebxUi\Users\Fields;

use Illuminate\Contracts\Container\Container;
use WebxUi\Settings\Settings;

/**
 * How much the site asks for each field the visitor fills in (spec §10.5): the settings
 * `users.fields.<name>` — `hidden`, `optional`, `required` — for the built-in first name, last
 * name and phone and for the fields of the site in the registration or the profile.
 *
 * Nothing stored: a built-in field is optional, a field of the site is what its `required` says.
 * The e-mail and the password are not here — they are always required. A site without the
 * settings module reads the defaults.
 */
final readonly class Requirements
{
    /** The built-in fields the visitor may be asked for, in the order of the forms. */
    public const array BUILT_IN = ['first_name', 'last_name', 'phone'];

    public const string PREFIX = 'users.fields.';

    public function __construct(
        private Container $container,
        private UserFields $fields,
    ) {}

    public function of(string $name): Requirement
    {
        $stored = $this->stored($name);

        if ($stored !== null) {
            return $stored;
        }

        return $this->fields->get($name)?->required === true ? Requirement::Required : Requirement::Optional;
    }

    public function required(string $name): bool
    {
        return $this->of($name) === Requirement::Required;
    }

    /**
     * Every field the setting is for, name => what is asked: the built-in ones first, then the
     * site's in the registration or the profile — the rows of the settings screen (§16.3).
     *
     * @return array<string, Requirement>
     */
    public function all(): array
    {
        $all = [];

        foreach (self::BUILT_IN as $name) {
            $all[$name] = $this->of($name);
        }

        foreach ($this->fields->all() as $field) {
            if ($field->in(Placement::Registration) || $field->in(Placement::Profile)) {
                $all[$field->name] = $this->of($field->name);
            }
        }

        return $all;
    }

    private function stored(string $name): ?Requirement
    {
        // Through the container on every read: the settings drop their cache on a save, and a
        // value kept here would outlive it.
        if (! $this->container->bound(Settings::class)) {
            return null;
        }

        $value = $this->container->make(Settings::class)->get(self::PREFIX.$name);

        return is_string($value) ? Requirement::tryFrom($value) : null;
    }
}
