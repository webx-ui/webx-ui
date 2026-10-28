<?php

declare(strict_types=1);

namespace WebxUi\Users\Fields;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use WebxUi\Admin\Screens\FieldTypes;

/**
 * The fields of the site on its visitors (spec §10): what modules registered in `boot()` and what
 * the site wrote in `webx-users.fields`, in `position` order, then the order they came in.
 *
 * A field that cannot work is refused loudly (§10.1, §10.2): a name a column of `users` or another
 * field holds, a type the panel's `FieldTypes` does not know, a choice without options, a type the
 * site's forms have no field for in a place the visitor fills in. The config is read, and the
 * types checked, on the first question — after every provider booted, so a type a module
 * registers in its own `boot()` is known by then whatever the order of the providers.
 */
final class UserFields
{
    /** The columns of `users` (§3): the visitor's own, never a field of the site. */
    public const array COLUMNS = [
        'id', 'email', 'email_verified_at', 'password', 'first_name', 'last_name', 'phone', 'locale',
        'extra', 'blocked_at', 'remember_token', 'last_sign_in_at', 'created_at', 'updated_at',
    ];

    /** What a type of the panel becomes in `<x-webx-field>` (§10.2); `wx-input` goes by its `props.type`. */
    private const array PAIRS = [
        'wx-textarea' => 'textarea',
        'wx-input-number' => 'number',
        'wx-switch' => 'checkbox',
        'wx-checkbox' => 'checkbox',
        'wx-select' => 'select',
        'wx-segmented' => 'select',
        'wx-radio-group' => 'radio',
        'wx-checkbox-group' => 'checkbox',
        'wx-date-picker' => 'date',
    ];

    private const array INPUTS = ['text', 'email', 'tel', 'url'];

    /** A choice among options: nothing to choose from is a field nobody can fill in. */
    private const array CHOICES = ['wx-select', 'wx-segmented', 'wx-radio-group', 'wx-checkbox-group'];

    /** What a project's type may become on the site: a value typed or picked, not a file or a secret. */
    private const array SITE_TYPES = ['text', 'email', 'tel', 'url', 'number', 'textarea', 'select', 'radio', 'checkbox', 'date'];

    /** @var array<string, UserField> */
    private array $fields = [];

    /** @var array<string, string> a project's type => its field on the site */
    private array $siteTypes = [];

    /** @var array<int, UserField> registered before the types were all known: checked on the first question */
    private array $unchecked = [];

    private bool $configured = false;

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
    ) {}

    /** A module's fields, in its `boot()`. */
    public function register(UserField ...$fields): void
    {
        foreach ($fields as $field) {
            $this->check($field);
            $this->fields[$field->name] = $field;

            if ($this->app->isBooted()) {
                $this->checkType($field);
            } else {
                $this->unchecked[] = $field;
            }
        }
    }

    /**
     * The field on the site of a type a project added (`FieldTypes::register('map', …)`): without
     * it such a field is the panel's only.
     */
    public function siteType(string $type, string $siteType): void
    {
        if (! in_array($siteType, self::SITE_TYPES, true)) {
            throw new InvalidArgumentException("UserFields::siteType('{$type}'): «{$siteType}» is not a field of the site — one of ".implode(', ', self::SITE_TYPES).'.');
        }

        $this->siteTypes[$type] = $siteType;
    }

    /** What `<x-webx-field type>` draws the field with, or null when the site has no field for it. */
    public function siteTypeOf(UserField $field): ?string
    {
        if ($field->type === 'wx-input') {
            $input = $field->props['type'] ?? 'text';

            return in_array($input, self::INPUTS, true) ? (string) $input : null;
        }

        return self::PAIRS[$field->type] ?? $this->siteTypes[$field->type] ?? null;
    }

    /** @return list<UserField>  in `position` order, then the order of registration */
    public function all(): array
    {
        $this->settle();

        $fields = array_values($this->fields);
        $order = array_flip(array_keys($this->fields));
        usort($fields, static fn (UserField $a, UserField $b): int => [$a->position, $order[$a->name]] <=> [$b->position, $order[$b->name]]);

        return $fields;
    }

    /** @return list<UserField> */
    public function for(Placement $placement): array
    {
        return array_values(array_filter($this->all(), static fn (UserField $field): bool => $field->in($placement)));
    }

    public function get(string $name): ?UserField
    {
        $this->settle();

        return $this->fields[$name] ?? null;
    }

    /**
     * The site's fields from the config, then every check that waited for the types. A refusal
     * leaves nothing half-taken: the next question refuses the same way.
     */
    private function settle(): void
    {
        if (! $this->configured) {
            $site = [];

            foreach ((array) $this->config->get('webx-users.fields', []) as $key => $entry) {
                if (! is_array($entry)) {
                    throw new InvalidArgumentException('webx-users.fields: every field is an array of the keys of UserField.');
                }

                $field = UserField::fromConfig($entry, is_string($key) ? $key : null);
                $this->check($field, $site);
                $site[$field->name] = $field;
            }

            $this->fields = [...$this->fields, ...$site];
            $this->unchecked = [...$this->unchecked, ...array_values($site)];
            $this->configured = true;
        }

        foreach ($this->unchecked as $index => $field) {
            $this->checkType($field);
            unset($this->unchecked[$index]);
        }
    }

    /** @param  array<string, UserField>  $more  declared alongside, not registered yet */
    private function check(UserField $field, array $more = []): void
    {
        $name = $field->name;

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException("A field of the visitors «{$name}»: the name is lower-case letters, digits and dashes.");
        }

        if (in_array($name, self::COLUMNS, true)) {
            throw new InvalidArgumentException("A field of the visitors «{$name}»: the name is a column of `users` — give the field another one.");
        }

        if (isset($this->fields[$name]) || isset($more[$name])) {
            $by = ($this->fields[$name] ?? $more[$name])->source ?? 'another module';

            throw new InvalidArgumentException("A field of the visitors «{$name}» is declared twice: {$by} has it already.");
        }

        if ($field->placements === []) {
            throw new InvalidArgumentException("A field of the visitors «{$name}» is in no place: registration, profile or panel.");
        }

        if (in_array($field->type, self::CHOICES, true) && ! is_array($field->props['options'] ?? null)) {
            throw new InvalidArgumentException("A field of the visitors «{$name}» ({$field->type}) has no `props.options` — there is nothing to choose from.");
        }
    }

    /** That the panel knows the type, and the site has a field for it where the visitor fills it in. */
    private function checkType(UserField $field): void
    {
        if (! $this->app->make(FieldTypes::class)->has($field->type)) {
            throw new InvalidArgumentException("A field of the visitors «{$field->name}»: the type «{$field->type}» is not registered in FieldTypes.");
        }

        $onSite = array_filter($field->placements, static fn (Placement $placement): bool => $placement->onSite());

        if ($onSite !== [] && $this->siteTypeOf($field) === null) {
            throw new InvalidArgumentException(
                "A field of the visitors «{$field->name}»: the site has no field for the type «{$field->type}», so it can be only in the panel — "
                ."drop the places registration and profile, or give it one with UserFields::siteType('{$field->type}', 'text').",
            );
        }
    }
}
