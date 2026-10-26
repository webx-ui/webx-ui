<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Schema;

use Closure;
use DateTimeImmutable;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Engine\Formula;
use WebxUi\Configurator\Engine\FormulaError;

/**
 * Whether a schema document is one the engine can price and a site can show (§9).
 *
 * The source of truth for the document's shape: the draft save, the publication, the MCP tools
 * and the `configurators://schema` resource all read it, the last one from the constants below,
 * so the reference an agent reads cannot drift from the check it is held to. The JS copy gives
 * the same paths and codes for the editor to underline before a save; the server checks anyway.
 *
 *     $problems = (new Validator('uk'))->validate($schema);
 *     // [Problem('steps.0.options.1.area', 'area.syntax'), …]
 *
 * A draft is saved with problems, a publication is refused with them: an autosave of a
 * configurator half built must not bounce every thirty seconds (§9).
 */
final class Validator
{
    /** The schema format this module reads. */
    public const int VERSION = 1;

    /** A step or field key: also a name in a formula, so letters, digits and `_` only. */
    public const string KEY = '/^[a-z][a-z0-9_]{0,63}$/';

    /** An option value: a slug. */
    public const string VALUE = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    /** Every code, with what it means — the reference `configurators://schema` hands an agent. */
    public const array CODES = [
        'schema.invalid' => 'The document is not a JSON object.',
        'schema.version' => 'version is not 1.',
        'currency.invalid' => 'currency is not a three-letter ISO 4217 code in capitals.',
        'pricing.round' => 'pricing.round is not a number above 0.',
        'discount.percent' => 'discount.percent is not a number between 0 and 100 (both excluded).',
        'discount.until' => 'discount.until is not a date YYYY-MM-DD.',
        'steps.empty' => 'There are no steps.',
        'key.invalid' => 'A step or field key is not a-z, 0-9 and _, starting with a letter, up to 64.',
        'key.duplicate' => 'Two steps or fields share a key: steps and size fields have one namespace.',
        'type.unknown' => 'A step type is not cards, swatches, buttons or dimensions.',
        'options.empty' => 'A choice step has no options.',
        'fields.empty' => 'A dimensions step has no fields.',
        'value.invalid' => 'An option value is not a slug (a-z, 0-9, - and _).',
        'value.duplicate' => 'Two options of one step share a value.',
        'label.missing' => 'There is no text in the default language.',
        'text.invalid' => 'A text is neither a string nor an object of language → string.',
        'image.missing' => 'A picture is not a media library key — required for every option of a cards step.',
        'image.unknown' => 'No such file in the media library.',
        'swatch.invalid' => 'A swatch is not #rgb, #rrggbb or a linear-/radial-gradient of hex colours, numbers with % or deg, to and sides.',
        'price.invalid' => 'A price is not { fixed, perM2, percent } of numbers.',
        'price.per_m2' => 'perM2 needs an area, and no step has an area formula.',
        'area.main_count' => 'Options of more than one step have an area formula; exactly one step is the main one.',
        'area.missing' => 'An option of the main step has no area formula.',
        'area.syntax' => 'An area formula is not numbers, field keys, + - * / and brackets.',
        'area.unknown_key' => 'An area formula names a key that is not a size field.',
        'range.invalid' => 'A size range is wrong: min above max, step not above 0, or not a number.',
        'limits.unknown_field' => 'limits names a key that is not a size field.',
        'defaults.unknown_field' => 'defaults names a key that is not a size field.',
        'condition.invalid' => 'A condition is not { step key: [values] }.',
        'condition.unknown_step' => 'A condition names a step that does not exist.',
        'condition.forward' => 'A condition names a step that is not above the element.',
        'condition.not_choice' => 'A condition names a dimensions step; only choice steps can be named.',
        'condition.unknown_value' => 'A condition names a value its step does not have.',
        'initial.unknown' => 'initial names a step, field or value that does not exist, or a size that is not a number.',
        'previews.condition' => 'A preview rule names a step or value that does not exist.',
    ];

    /** The step types and what they hold. */
    public const array TYPES = [
        'cards' => 'options with a picture (image); each shows «from N»',
        'swatches' => 'options with a swatch (a colour or gradient) or swatchImage (a media key)',
        'buttons' => 'plain options',
        'dimensions' => 'number fields with min, max, step and a − / + stepper',
    ];

    /** @var list<Problem> */
    private array $problems = [];

    /** @var array<string, string> media key → the path of the first place it is used */
    private array $images = [];

    /**
     * @param  string  $locale  the site's default language: every text needs words in it
     * @param  (Closure(list<string>): list<string>)|null  $media  the keys of these that the library has; null to skip the check
     * @param  list<string>  $known  keys that were in the document before: an image is checked only when new (`ChecksNewValues`)
     */
    public function __construct(
        private readonly string $locale,
        private readonly ?Closure $media = null,
        private readonly array $known = [],
    ) {}

    /**
     * @return list<Problem>
     */
    public function validate(mixed $schema): array
    {
        $this->problems = [];
        $this->images = [];

        if (! is_array($schema) || ($schema !== [] && array_is_list($schema))) {
            return [new Problem('', 'schema.invalid')];
        }

        if (($schema['version'] ?? null) !== self::VERSION) {
            $this->fail('version', 'schema.version');
        }

        if (! is_string($schema['currency'] ?? null) || preg_match('/^[A-Z]{3}$/', $schema['currency']) !== 1) {
            $this->fail('currency', 'currency.invalid');
        }

        if (array_key_exists('pricing', $schema)) {
            $round = is_array($schema['pricing']) ? ($schema['pricing']['round'] ?? 1) : null;

            if (Engine::float($round) === null || Engine::float($round) <= 0 || is_string($round)) {
                $this->fail('pricing.round', 'pricing.round');
            }
        }

        $this->discount($schema);

        $steps = $schema['steps'] ?? null;

        if (! is_array($steps) || $steps === [] || ! array_is_list($steps)) {
            $this->fail('steps', 'steps.empty');
            $steps = [];
        }

        $fields = $this->fieldKeys($steps);
        $this->steps($steps, $fields);
        $this->initial($schema['initial'] ?? null, $steps);
        $this->previews($schema['previews'] ?? null, $steps);
        $this->checkImages();

        return $this->problems;
    }

    /**
     * Every media key the document uses — what `MediaUsage` finds and a demo or a copy carries.
     *
     * @return list<string>
     */
    public static function images(mixed $schema): array
    {
        $found = [];

        foreach (Engine::steps(Engine::map($schema)) as $step) {
            foreach (Engine::options($step) as $option) {
                foreach (['image', 'swatchImage'] as $key) {
                    if (is_string($option[$key] ?? null) && $option[$key] !== '') {
                        $found[] = $option[$key];
                    }
                }
            }
        }

        foreach (is_array($schema['previews'] ?? null) ? $schema['previews'] : [] as $preview) {
            if (is_array($preview) && is_string($preview['image'] ?? null) && $preview['image'] !== '') {
                $found[] = $preview['image'];
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * A swatch goes into a `style` attribute, so it is a closed list of tokens (§5.5): a `url()`
     * or a `;` in there would be a door for anybody's CSS.
     */
    public static function swatch(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1) {
            return true;
        }

        if (preg_match('/^(?:linear|radial)-gradient\((.+)\)$/s', $value, $match) !== 1) {
            return false;
        }

        $token = '(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|-?\d+(?:\.\d+)?(?:%|deg)?|to|left|right|top|bottom|circle|ellipse|at|center|,)';

        return preg_match('/^(?:\s*'.$token.'\s*)+$/', $match[1]) === 1;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function discount(array $schema): void
    {
        if (! array_key_exists('discount', $schema) || $schema['discount'] === null) {
            return;
        }

        $discount = is_array($schema['discount']) ? $schema['discount'] : [];
        $percent = $discount['percent'] ?? null;

        if (is_string($percent) || Engine::float($percent) === null || Engine::float($percent) <= 0 || Engine::float($percent) >= 100) {
            $this->fail('discount.percent', 'discount.percent');
        }

        $until = $discount['until'] ?? null;

        if ($until !== null && ! $this->date($until)) {
            $this->fail('discount.until', 'discount.until');
        }

        if (array_key_exists('label', $discount)) {
            $this->text($discount['label'], 'discount.label');
        }
    }

    /**
     * The keys of every size field, wherever its step stands: a formula or a `defaults` above
     * the dimensions step still names them.
     *
     * @param  array<mixed>  $steps
     * @return list<string>
     */
    private function fieldKeys(array $steps): array
    {
        $keys = [];

        foreach ($steps as $step) {
            if (! is_array($step) || ($step['type'] ?? null) !== 'dimensions' || ! is_array($step['fields'] ?? null)) {
                continue;
            }

            foreach ($step['fields'] as $field) {
                if (is_array($field) && is_string($field['key'] ?? null)) {
                    $keys[] = $field['key'];
                }
            }
        }

        return $keys;
    }

    /**
     * @param  array<mixed>  $steps
     * @param  list<string>  $fields
     */
    private function steps(array $steps, array $fields): void
    {
        $keys = [];
        /** @var array<string, array{index: int, choice: bool, values: list<string>}> $known */
        $known = [];
        $main = [];
        $perM2 = [];

        // Steps first, to know what a condition may name and which step is the main one.
        foreach ($steps as $index => $step) {
            if (is_array($step) && is_string($step['key'] ?? null)) {
                $known[$step['key']] ??= [
                    'index' => (int) $index,
                    'choice' => in_array($step['type'] ?? null, Engine::CHOICE_TYPES, true),
                    'values' => array_values(array_map(
                        static fn (array $option): string => (string) $option['value'],
                        Engine::options($step),
                    )),
                ];
            }
        }

        foreach ($steps as $index => $step) {
            $path = "steps.{$index}";

            if (! is_array($step)) {
                $this->fail($path, 'type.unknown');

                continue;
            }

            $this->key($step['key'] ?? null, "{$path}.key", $keys);

            $type = $step['type'] ?? null;

            if (! is_string($type) || ! array_key_exists($type, self::TYPES)) {
                $this->fail("{$path}.type", 'type.unknown');

                continue;
            }

            $this->label($step, $path, ['label', 'title']);
            $this->texts($step, $path, ['label', 'title', 'hint']);
            $this->conditions($step, $path, $known, (int) $index);

            if ($type === 'dimensions') {
                $this->fields($step, $path, $keys, $known, (int) $index);

                continue;
            }

            $options = $step['options'] ?? null;

            if (! is_array($options) || $options === [] || ! array_is_list($options)) {
                $this->fail("{$path}.options", 'options.empty');

                continue;
            }

            $values = [];

            foreach ($options as $at => $option) {
                $here = "{$path}.options.{$at}";

                if (! is_array($option)) {
                    $this->fail("{$here}.value", 'value.invalid');

                    continue;
                }

                $this->option($option, $here, $type, $values, $fields, $known, (int) $index);

                if (array_key_exists('area', $option)) {
                    $main[(int) $index][] = $at;
                }

                $rate = is_array($option['price'] ?? null) ? Engine::float($option['price']['perM2'] ?? null) : null;

                if ($rate !== null && $rate != 0.0) {
                    $perM2[] = "{$here}.price.perM2";
                }
            }
        }

        $this->main($main, $steps, $perM2);
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  list<string>  $values
     * @param  list<string>  $fields
     * @param  array<string, array{index: int, choice: bool, values: list<string>}>  $known
     */
    private function option(array $option, string $path, string $type, array &$values, array $fields, array $known, int $index): void
    {
        $value = $option['value'] ?? null;

        if (! is_string($value) || preg_match(self::VALUE, $value) !== 1) {
            $this->fail("{$path}.value", 'value.invalid', ['value' => is_scalar($value) ? (string) $value : '']);
        } elseif (in_array($value, $values, true)) {
            $this->fail("{$path}.value", 'value.duplicate', ['value' => $value]);
        } else {
            $values[] = $value;
        }

        $this->label($option, $path, ['label']);
        $this->texts($option, $path, ['label', 'subtitle']);

        // A card without a picture; one given but not a key is told once, by the loop below.
        if ($type === 'cards' && ($option['image'] ?? null) === null) {
            $this->fail("{$path}.image", 'image.missing');
        }

        foreach (['image', 'swatchImage'] as $key) {
            if (! array_key_exists($key, $option) || $option[$key] === null) {
                continue;
            }

            if (! is_string($option[$key]) || trim($option[$key]) === '') {
                $this->fail("{$path}.{$key}", 'image.missing');
            } else {
                $this->images[$option[$key]] ??= "{$path}.{$key}";
            }
        }

        if (array_key_exists('swatch', $option) && $option['swatch'] !== null && ! self::swatch($option['swatch'])) {
            $this->fail("{$path}.swatch", 'swatch.invalid');
        }

        $this->price($option['price'] ?? null, "{$path}.price", array_key_exists('price', $option));

        if (array_key_exists('area', $option)) {
            $this->formula($option['area'], "{$path}.area", $fields);
        }

        $this->sizes($option['defaults'] ?? null, "{$path}.defaults", $fields, 'defaults.unknown_field', pair: false, given: array_key_exists('defaults', $option));
        $this->sizes($option['limits'] ?? null, "{$path}.limits", $fields, 'limits.unknown_field', pair: true, given: array_key_exists('limits', $option));
        $this->conditions($option, $path, $known, $index);
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  list<string>  $keys
     * @param  array<string, array{index: int, choice: bool, values: list<string>}>  $known
     */
    private function fields(array $step, string $path, array &$keys, array $known, int $index): void
    {
        $fields = $step['fields'] ?? null;

        if (! is_array($fields) || $fields === [] || ! array_is_list($fields)) {
            $this->fail("{$path}.fields", 'fields.empty');

            return;
        }

        foreach ($fields as $at => $field) {
            $here = "{$path}.fields.{$at}";

            if (! is_array($field)) {
                $this->fail("{$here}.key", 'key.invalid');

                continue;
            }

            $this->key($field['key'] ?? null, "{$here}.key", $keys);
            $this->label($field, $here, ['label']);
            $this->texts($field, $here, ['label', 'unit']);

            $min = $field['min'] ?? null;
            $max = $field['max'] ?? null;

            if (! $this->isNumber($min) || ! $this->isNumber($max) || (float) $min > (float) $max) {
                $this->fail($here, 'range.invalid');
            }

            if (array_key_exists('step', $field) && (! $this->isNumber($field['step']) || (float) $field['step'] <= 0)) {
                $this->fail("{$here}.step", 'range.invalid');
            }

            if (array_key_exists('default', $field) && $field['default'] !== null && ! $this->isNumber($field['default'])) {
                $this->fail("{$here}.default", 'range.invalid');
            }

            $this->conditions($field, $here, $known, $index);
        }
    }

    /**
     * Exactly one step is the main one, and all of its options have a formula (§7.1); with none,
     * nothing may be priced per square metre.
     *
     * @param  array<int, list<int|string>>  $main  step index → options with an area
     * @param  array<mixed>  $steps
     * @param  list<string>  $perM2
     */
    private function main(array $main, array $steps, array $perM2): void
    {
        if ($main === []) {
            foreach ($perM2 as $path) {
                $this->fail($path, 'price.per_m2');
            }

            return;
        }

        $indexes = array_keys($main);

        foreach (array_slice($indexes, 1) as $index) {
            $this->fail("steps.{$index}", 'area.main_count');
        }

        $first = $indexes[0];

        foreach (is_array($steps[$first]['options'] ?? null) ? $steps[$first]['options'] : [] as $at => $option) {
            if (is_array($option) && ! array_key_exists('area', $option)) {
                $this->fail("steps.{$first}.options.{$at}.area", 'area.missing');
            }
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function key(mixed $key, string $path, array &$keys): void
    {
        if (! is_string($key) || preg_match(self::KEY, $key) !== 1) {
            $this->fail($path, 'key.invalid', ['key' => is_scalar($key) ? (string) $key : '']);

            return;
        }

        if (in_array($key, $keys, true)) {
            $this->fail($path, 'key.duplicate', ['key' => $key]);

            return;
        }

        $keys[] = $key;
    }

    /**
     * Words in the default language in at least one of the keys — a step's `label` falls back to
     * its `title`, so either will do there.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    private function label(array $item, string $path, array $keys): void
    {
        foreach ($keys as $key) {
            $text = $item[$key] ?? null;

            if ((is_string($text) && trim($text) !== '') || (is_array($text) && is_string($text[$this->locale] ?? null) && trim($text[$this->locale]) !== '')) {
                return;
            }
        }

        $this->fail("{$path}.{$keys[0]}", 'label.missing', ['locale' => $this->locale]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    private function texts(array $item, string $path, array $keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                $this->text($item[$key], "{$path}.{$key}");
            }
        }
    }

    private function text(mixed $text, string $path): void
    {
        if (is_string($text)) {
            return;
        }

        if (is_array($text) && ! array_is_list($text)) {
            foreach ($text as $locale => $words) {
                if (! is_string($locale) || ! is_string($words)) {
                    $this->fail($path, 'text.invalid');

                    return;
                }
            }

            return;
        }

        $this->fail($path, 'text.invalid');
    }

    private function price(mixed $price, string $path, bool $given): void
    {
        if (! $given || $price === null) {
            return;
        }

        if (! is_array($price) || ($price !== [] && array_is_list($price))) {
            $this->fail($path, 'price.invalid');

            return;
        }

        foreach ($price as $key => $amount) {
            if (! in_array($key, ['fixed', 'perM2', 'percent'], true) || ($amount !== null && ! $this->isNumber($amount))) {
                $this->fail("{$path}.{$key}", 'price.invalid');
            }
        }
    }

    /**
     * @param  list<string>  $fields
     */
    private function formula(mixed $area, string $path, array $fields): void
    {
        if (! is_string($area)) {
            $this->fail($path, 'area.syntax', ['position' => 0, 'reason' => 'not a string']);

            return;
        }

        try {
            $tree = Formula::parse($area);
        } catch (FormulaError $error) {
            $this->fail($path, 'area.syntax', ['position' => $error->position, 'reason' => $error->reason]);

            return;
        }

        foreach (Formula::keys($tree) as $key) {
            if (! in_array($key, $fields, true)) {
                $this->fail($path, 'area.unknown_key', ['key' => $key]);
            }
        }
    }

    /**
     * `defaults` (field → number) and `limits` (field → [min, max]).
     *
     * @param  list<string>  $fields
     */
    private function sizes(mixed $map, string $path, array $fields, string $unknown, bool $pair, bool $given): void
    {
        if (! $given || $map === null) {
            return;
        }

        if (! is_array($map) || ($map !== [] && array_is_list($map))) {
            $this->fail($path, 'range.invalid');

            return;
        }

        foreach ($map as $field => $value) {
            $field = (string) $field;

            if (! in_array($field, $fields, true)) {
                $this->fail("{$path}.{$field}", $unknown, ['key' => $field]);

                continue;
            }

            $valid = $pair
                ? is_array($value) && array_is_list($value) && count($value) === 2 && $this->isNumber($value[0]) && $this->isNumber($value[1]) && (float) $value[0] <= (float) $value[1]
                : $this->isNumber($value);

            if (! $valid) {
                $this->fail("{$path}.{$field}", 'range.invalid');
            }
        }
    }

    /**
     * `when` and `unless` of a step, an option or a field: steps above, choice steps, their values.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, array{index: int, choice: bool, values: list<string>}>  $known
     */
    private function conditions(array $item, string $path, array $known, int $index): void
    {
        foreach (['when', 'unless'] as $key) {
            if (! array_key_exists($key, $item) || $item[$key] === null) {
                continue;
            }

            $this->condition($item[$key], "{$path}.{$key}", $known, $index, 'condition');
        }
    }

    /**
     * @param  array<string, array{index: int, choice: bool, values: list<string>}>  $known
     * @param  int|null  $index  the step the element stands in; null for a preview, which stands after them all
     * @param  string  $prefix  `condition` — or `previews`, whose problems all go by one code
     */
    private function condition(mixed $condition, string $path, array $known, ?int $index, string $prefix): void
    {
        $code = static fn (string $what): string => $prefix === 'previews' ? 'previews.condition' : "condition.{$what}";

        if (! is_array($condition) || ($condition !== [] && array_is_list($condition))) {
            $this->fail($path, $code('invalid'));

            return;
        }

        foreach ($condition as $step => $values) {
            $step = (string) $step;
            $here = "{$path}.{$step}";

            if (! is_array($values) || ! array_is_list($values)) {
                $this->fail($here, $code('invalid'));

                continue;
            }

            if (! isset($known[$step])) {
                $this->fail($here, $code('unknown_step'), ['step' => $step]);

                continue;
            }

            if ($index !== null && $known[$step]['index'] >= $index) {
                $this->fail($here, $code('forward'), ['step' => $step]);

                continue;
            }

            if (! $known[$step]['choice']) {
                $this->fail($here, $code('not_choice'), ['step' => $step]);

                continue;
            }

            foreach ($values as $at => $value) {
                if (! is_string($value) || ! in_array($value, $known[$step]['values'], true)) {
                    $this->fail("{$here}.{$at}", $code('unknown_value'), ['step' => $step, 'value' => is_scalar($value) ? (string) $value : '']);
                }
            }
        }
    }

    /**
     * @param  array<mixed>  $steps
     */
    private function initial(mixed $initial, array $steps): void
    {
        if ($initial === null) {
            return;
        }

        if (! is_array($initial) || ($initial !== [] && array_is_list($initial))) {
            $this->fail('initial', 'initial.unknown');

            return;
        }

        $choices = [];
        $sizes = [];

        foreach (Engine::steps(['steps' => $steps]) as $step) {
            if (in_array($step['type'] ?? null, Engine::CHOICE_TYPES, true)) {
                $choices[(string) $step['key']] = array_map(static fn (array $option): string => (string) $option['value'], Engine::options($step));
            }

            foreach (Engine::fields($step) as $field) {
                $sizes[] = (string) $field['key'];
            }
        }

        foreach ($initial as $key => $value) {
            $key = (string) $key;

            $valid = isset($choices[$key])
                ? is_string($value) && in_array($value, $choices[$key], true)
                : in_array($key, $sizes, true) && $this->isNumber($value);

            if (! $valid) {
                $this->fail("initial.{$key}", 'initial.unknown', ['key' => $key]);
            }
        }
    }

    /**
     * @param  array<mixed>  $steps
     */
    private function previews(mixed $previews, array $steps): void
    {
        if ($previews === null) {
            return;
        }

        if (! is_array($previews) || ! array_is_list($previews)) {
            $this->fail('previews', 'previews.condition');

            return;
        }

        $known = [];

        foreach (Engine::steps(['steps' => $steps]) as $index => $step) {
            $known[(string) $step['key']] ??= [
                'index' => $index,
                'choice' => in_array($step['type'] ?? null, Engine::CHOICE_TYPES, true),
                'values' => array_map(static fn (array $option): string => (string) $option['value'], Engine::options($step)),
            ];
        }

        foreach ($previews as $at => $preview) {
            $path = "previews.{$at}";

            if (! is_array($preview)) {
                $this->fail($path, 'previews.condition');

                continue;
            }

            $this->condition($preview['when'] ?? null, "{$path}.when", $known, null, 'previews');

            if (! is_string($preview['image'] ?? null) || trim($preview['image']) === '') {
                $this->fail("{$path}.image", 'image.missing');
            } else {
                $this->images[$preview['image']] ??= "{$path}.image";
            }
        }
    }

    /**
     * The pictures the library does not have — asked once for the whole document, and only about
     * keys that are new to it, so a file deleted from the library later does not lock the editor.
     */
    private function checkImages(): void
    {
        if ($this->media === null) {
            return;
        }

        $new = array_values(array_diff(array_keys($this->images), $this->known));

        if ($new === []) {
            return;
        }

        $found = ($this->media)($new);

        foreach ($new as $key) {
            if (! in_array($key, $found, true)) {
                $this->fail($this->images[$key], 'image.unknown', ['key' => $key]);
            }
        }
    }

    private function date(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    /**
     * @param  array<string, string|int|float>  $params
     */
    private function fail(string $path, string $code, array $params = []): void
    {
        $this->problems[] = new Problem($path, $code, $params);
    }
}
