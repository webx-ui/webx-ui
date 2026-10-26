<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Pricing;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Configurator\Schema\Validator;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;

/**
 * The engine and the check as the rest of the module asks for them: in the site's languages, with
 * the media library behind `image.unknown`, on today's date.
 *
 * The one place that turns «values from outside» into a price — the MCP `quote`, the panel's
 * preview and (stage 4) the request a visitor sends all come through here, so they cannot
 * disagree about what a set of values costs.
 */
final class Quoter
{
    public function __construct(private readonly Locales $locales) {}

    /**
     * The problems of a document. `$known` are the media keys it had before: only new ones are
     * looked up in the library.
     *
     * @param  list<string>  $known
     * @return list<Problem>
     */
    public function problems(mixed $schema, array $known = []): array
    {
        return $this->validator($known)->validate($schema);
    }

    /**
     * @param  list<string>  $known
     */
    public function validator(array $known = []): Validator
    {
        $media = class_exists(MediaFile::class)
            ? static fn (array $keys): array => MediaFile::query()->whereIn('path', $keys)->pluck('path')->map(strval(...))->all()
            : null;

        return new Validator($this->locales->defaultCode(), $media, $known);
    }

    /**
     * Values (and the visitor's actions) to a price, or the reasons there is none.
     *
     * A schema with problems is not priced: what it would cost is a guess. Values that are refused
     * — a size outside its limits — are errors too, never quietly clamped (§6.4).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $values
     * @param  list<mixed>  $actions  `{"select": [step, value]}` or `{"set": [field, number]}`
     * @param  array<string, mixed>|null  $initial  a block's preset over the schema's `initial`
     * @return array<string, mixed>
     */
    public function quote(array $schema, array $values, array $actions = [], ?array $initial = null, ?string $today = null, ?string $locale = null): array
    {
        $problems = $this->problems($schema, Validator::images($schema));

        if ($problems !== []) {
            return ['errors' => array_map(static fn (Problem $problem): array => $problem->toArray(), $problems)];
        }

        if ($initial !== null) {
            $initial = [...Engine::map($schema['initial'] ?? null), ...$initial];
        }

        $state = Engine::resolve($schema, $values, $initial);

        if ($state->errors !== []) {
            return ['errors' => array_map(fn (array $error): array => $error + ['message' => $this->valueMessage($error['code'], $error['path'], $state->limits)], $state->errors)];
        }

        foreach ($actions as $at => $action) {
            $select = is_array($action) ? ($action['select'] ?? null) : null;
            $set = is_array($action) ? ($action['set'] ?? null) : null;

            if (is_array($select) && is_string($select[0] ?? null) && is_scalar($select[1] ?? null)) {
                $state = Engine::select($schema, $state, $select[0], (string) $select[1], $initial);
            } elseif (is_array($set) && is_string($set[0] ?? null) && Engine::float($set[1] ?? null) !== null) {
                $state = Engine::set($schema, $state, $set[0], (float) Engine::float($set[1]), $initial);
            } else {
                throw new InvalidArgumentException("actions.{$at} is neither {\"select\": [step, value]} nor {\"set\": [field, number]}.");
            }
        }

        $locale ??= $this->locales->content();
        $quote = Engine::price($schema, $state, $today ?? Carbon::today()->toDateString());

        return Breakdown::describe($schema, $quote, $locale, $this->locales->defaultCode());
    }

    /**
     * @param  array<string, array{0: float, 1: float}>  $limits
     */
    private function valueMessage(string $code, string $path, array $limits): string
    {
        $field = substr($path, strlen('values.'));
        [$min, $max] = $limits[$field] ?? [0, 0];

        return (string) trans('webx-configurator::errors.'.$code, [
            'key' => $field,
            'min' => Engine::number($min),
            'max' => Engine::number($max),
        ]);
    }
}
