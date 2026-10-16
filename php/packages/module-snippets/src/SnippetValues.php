<?php

declare(strict_types=1);

namespace WebxUi\Snippets;

use Illuminate\Validation\ValidationException;
use WebxUi\Localization\Locales;
use WebxUi\Snippets\Models\Snippet;

/**
 * What a snippet may hold (§3.3), checked the same way for every door that writes one — the
 * panel's form today, the agent's tools next.
 *
 * A field that did not come is left as it is: an update names what it changes. A refusal is a
 * `ValidationException` under the name of the field, the words from the module's dictionary.
 *
 * The tags of a whole document in the code are a warning, not a refusal (§7.4): the save goes
 * through and the answer says what to look at.
 */
final class SnippetValues
{
    /** Fields a save may carry. */
    public const FIELDS = ['name', 'place', 'code', 'consent', 'only_paths', 'except_paths', 'locales', 'on_stage', 'enabled'];

    private const NAME_LENGTH = 160;

    private const MASKS = 50;

    public function __construct(
        private readonly Places $places,
        private readonly Locales $locales,
    ) {}

    /**
     * The values to write, checked and brought to their stored shape.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $input, ?Snippet $existing = null): array
    {
        $creating = $existing === null || ! $existing->exists;
        $values = [];
        $errors = [];

        foreach (['name', 'place', 'code'] as $required) {
            if ($creating && ! array_key_exists($required, $input)) {
                $errors[$required] = $this->say('required', ['field' => $required]);
            }
        }

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            try {
                $values[$field] = $this->value($field, $input[$field]);
            } catch (Refusal $refusal) {
                $errors[$field] = $refusal->getMessage();
            }
        }

        if ($errors === []) {
            $place = (string) ($values['place'] ?? $existing->place ?? '');
            $enabled = (bool) ($values['enabled'] ?? $existing->enabled ?? false);

            // A place taken out of the config keeps its codes (§4.3): one may be saved there to be
            // switched off or moved, but not switched on — it would be printed nowhere.
            if (! $this->places->has($place)) {
                $moved = array_key_exists('place', $values) && $values['place'] !== ($existing?->place);

                if ($creating || $moved) {
                    $errors['place'] = $this->say('place-undeclared', ['place' => $place]);
                } elseif ($enabled) {
                    $errors['place'] = $this->say('place-orphaned', ['place' => $place]);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    /**
     * What the code says about a whole document: `<html>`, `<head>`, `<body>`, opening or
     * closing. A hint, not a parser — a tag inside a comment counts too.
     *
     * @return list<string>
     */
    public function warnings(string $code): array
    {
        if (preg_match_all('~<\s*/?\s*(html|head|body)\b~i', $code, $found) < 1) {
            return [];
        }

        $tags = array_values(array_unique(array_map(static fn (string $tag): string => '<'.strtolower($tag).'>', $found[1])));

        return [$this->say('document-tags', ['tags' => implode(', ', $tags)])];
    }

    /**
     * @throws Refusal
     */
    private function value(string $field, mixed $value): mixed
    {
        return match ($field) {
            'name' => $this->name($value),
            'place' => $this->place($value),
            'code' => $this->code($value),
            'consent' => $this->consent($value),
            'only_paths', 'except_paths' => $this->masks($value),
            'locales' => $this->languages($value),
            default => $this->flag($field, $value),
        };
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';

        if ($name === '') {
            throw new Refusal($this->say('required', ['field' => 'name']));
        }

        if (mb_strlen($name) > self::NAME_LENGTH) {
            throw new Refusal($this->say('name-long', ['max' => self::NAME_LENGTH]));
        }

        return $name;
    }

    /** The key as it came; whether it is declared is decided with the rest of the record. */
    private function place(mixed $value): string
    {
        $place = is_string($value) ? trim($value) : '';

        if ($place === '') {
            throw new Refusal($this->say('required', ['field' => 'place']));
        }

        return $place;
    }

    private function code(mixed $value): string
    {
        $code = is_string($value) ? $value : '';

        if (trim($code) === '') {
            throw new Refusal($this->say('required', ['field' => 'code']));
        }

        if (strlen($code) > Snippet::MAX_BYTES) {
            throw new Refusal($this->say('code-long', ['bytes' => strlen($code), 'max' => Snippet::MAX_BYTES]));
        }

        return $code;
    }

    private function consent(mixed $value): string
    {
        if (! is_string($value) || ! in_array($value, Snippet::CATEGORIES, true)) {
            throw new Refusal($this->say('consent', ['categories' => implode(', ', Snippet::CATEGORIES)]));
        }

        return $value;
    }

    /**
     * Paths from the root without the language prefix, `*` for any run of characters: `/`,
     * `/blog/*`. Empty means the whole site — stored as null, so "no list" has one shape.
     *
     * @return list<string>|null
     */
    private function masks(mixed $value): ?array
    {
        $list = $this->list($value);
        $masks = [];

        foreach ($list as $mask) {
            $mask = trim($mask);

            if ($mask === '') {
                continue;
            }

            if (! str_starts_with($mask, '/') || str_contains($mask, '?') || str_contains($mask, '#') || str_starts_with($mask, '//')) {
                throw new Refusal($this->say('mask', ['mask' => $mask]));
            }

            $masks[] = $mask;
        }

        $masks = array_values(array_unique($masks));

        if (count($masks) > self::MASKS) {
            throw new Refusal($this->say('masks-many', ['max' => self::MASKS]));
        }

        return $masks === [] ? null : $masks;
    }

    /**
     * Codes of the site's languages; empty is every language.
     *
     * @return list<string>|null
     */
    private function languages(mixed $value): ?array
    {
        $codes = array_values(array_unique(array_filter(array_map(trim(...), $this->list($value)), static fn (string $code): bool => $code !== '')));
        $known = $this->locales->codes();
        $unknown = array_values(array_diff($codes, $known));

        if ($unknown !== []) {
            throw new Refusal($this->say('locales', ['unknown' => implode(', ', $unknown), 'known' => implode(', ', $known)]));
        }

        return $codes === [] ? null : $codes;
    }

    private function flag(string $field, mixed $value): bool
    {
        $flag = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($flag === null) {
            throw new Refusal($this->say('boolean', ['field' => $field]));
        }

        return $flag;
    }

    /**
     * A list of strings, from a list or from one string with a value per line.
     *
     * @return list<string>
     *
     * @throws Refusal
     */
    private function list(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            return preg_split('/\R/', $value) ?: [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new Refusal($this->say('list'));
        }

        $strings = [];

        foreach ($value as $item) {
            // An emptied row of the form arrives as null once Laravel has had its middleware.
            if ($item === null) {
                continue;
            }

            if (! is_string($item)) {
                throw new Refusal($this->say('list'));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function say(string $key, array $replace = []): string
    {
        $line = __('webx-snippets::errors.'.$key, $replace);

        return is_string($line) ? $line : $key;
    }
}
