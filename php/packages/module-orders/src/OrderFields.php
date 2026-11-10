<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Cache\CacheGroups;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Models\OrderField;

/**
 * The extra fields of the checkout as the panel and the agent write them (WEBX_UI_ORDER_FIELDS.md
 * §2.1, §3, §7.3): what each field is, where it stands, behind which checkbox — and the order.
 *
 * What it guards is that every definition can be asked and checked: the key and the type stay what
 * they were, the place is one the registry knows, a list has options and nothing else does, and a
 * field shown behind a checkbox stands in its place, one level deep. Each refusal is a
 * {@see FieldRefused} with the dry run's answer; a dry run answers the same and writes nothing.
 * Orders are never written here: they keep their snapshot (§2.2).
 */
final readonly class OrderFields
{
    /** The cache group of the definitions (§2.1): every checkout request reads them whole. */
    public const string GROUP = 'order-fields';

    public function __construct(
        private FieldPlaces $places,
        private ConnectionInterface $db,
        private Validation $validation,
        private Locales $locales,
        private CacheGroups $groups,
    ) {}

    /**
     * Every definition in its order, switched off ones too — one query, kept in the group.
     *
     * @return Collection<int, OrderField>
     */
    public function all(): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->groups->remember('orders.fields', [self::GROUP], static fn (): array => OrderField::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(static fn (OrderField $field): array => $field->getAttributes())
            ->all(), localized: false);

        return OrderField::hydrate($rows);
    }

    /**
     * A new field at the end, or the one given changed. Left out of `$input` on a change — as it was.
     *
     * @param  array<string, mixed>  $input  `key`, `type`, `place`, `label` and `hint` by language,
     *                                       `options` (`[{value, label: {locale: text}}]`), `required`,
     *                                       `active`, `parent` — the key of a checkbox, or null
     *
     * @throws ValidationException the shape of the input: lengths, types of the arguments
     * @throws FieldRefused
     */
    public function write(array $input, ?OrderField $field = null, bool $dryRun = false): FieldPlan
    {
        $valid = $this->valid($input, $field === null);
        $fallback = $this->locales->fallback();
        $key = array_key_exists('key', $valid) ? strtolower(trim((string) $valid['key'])) : (string) $field?->key;
        $type = array_key_exists('type', $valid) ? (string) $valid['type'] : (string) ($field->type ?? '');
        $label = array_key_exists('label', $valid) ? self::words($valid['label']) : self::words($field?->getTranslations('label'));
        $hint = array_key_exists('hint', $valid) ? self::words($valid['hint']) : self::words($field?->getTranslations('hint'));
        $options = array_key_exists('options', $valid) ? self::options($valid['options']) : ($field->options ?? []);
        $required = array_key_exists('required', $valid) ? (bool) $valid['required'] : (bool) $field?->required;
        $active = array_key_exists('active', $valid) ? (bool) $valid['active'] : ($field === null || $field->active);

        $current = $field?->parent_id !== null ? OrderField::query()->find($field->parent_id) : null;
        $parentKey = array_key_exists('parent', $valid) ? (is_string($valid['parent']) && trim($valid['parent']) !== '' ? trim($valid['parent']) : null) : $current?->key;
        $parent = $parentKey !== null ? OrderField::query()->where('key', $parentKey)->first() : null;
        // A field behind a checkbox stands where the checkbox does, unless it says otherwise.
        $place = array_key_exists('place', $valid) && is_string($valid['place']) && $valid['place'] !== ''
            ? $valid['place']
            : ($parent->place ?? $field->place ?? FieldPlaces::DEFAULT);

        $dependents = $field !== null ? $this->dependents($field) : [];
        $plan = fn (?string $refusal, ?string $input = null, array $effects = []): FieldPlan => new FieldPlan(
            $refusal,
            $effects,
            $input,
            $dependents,
            $field !== null ? array_values(array_diff($field->optionValues(), array_column($options, 'value'))) : [],
            $dryRun,
            $field?->id,
            $key,
        );

        [$refusal, $about] = $this->refusal($field, $key, $type, $place, $label, $options, $parentKey, $parent, $dependents, $fallback);

        if ($refusal !== null) {
            $refused = $plan($refusal, $about);

            if ($dryRun) {
                return $refused;
            }

            throw new FieldRefused($refused);
        }

        $answer = $plan(null, null, $this->effects($field, $required, $active, $place, $options));

        if ($dryRun) {
            return $answer;
        }

        $saved = $this->db->transaction(function () use ($field, $key, $type, $place, $label, $hint, $options, $required, $active, $parent): OrderField {
            $field ??= new OrderField(['position' => (int) OrderField::query()->max('position') + 1]);
            $moved = $field->exists && $field->place !== $place;

            $field->key = $key;
            $field->type = $type;
            $field->place = $place;
            $field->setAttribute('label', $label);
            $field->setAttribute('hint', $hint);

            // No hint is null, as the column says, not an empty map of languages.
            if ($hint === []) {
                $field->setRawAttributes(['hint' => null] + $field->getAttributes());
            }

            $field->options = $type === OrderField::SELECT ? $options : null;
            $field->required = $required;
            $field->active = $active;
            $field->parent_id = $parent?->id;
            $field->save();

            // The fields behind a checkbox go where it goes, in the same write (§3.3).
            if ($moved) {
                foreach ($field->dependents()->get() as $dependent) {
                    $dependent->place = $place;
                    $dependent->save();
                }
            }

            return $field;
        });

        return $answer->done($saved->id);
    }

    /**
     * Delete a definition. A checkbox with fields shown behind it is refused, and the plan names
     * them; the orders that answered it keep their snapshot (§6.4).
     *
     * @throws FieldRefused
     */
    public function remove(OrderField $field, bool $dryRun = false): FieldPlan
    {
        $dependents = $this->dependents($field);
        $refusal = $dependents !== [] ? FieldRefused::HAS_DEPENDENTS : null;
        $plan = new FieldPlan($refusal, $refusal === null ? [FieldPlan::DELETED] : [], $refusal !== null ? 'key' : null, $dependents, [], $dryRun, $field->id, $field->key);

        if ($dryRun) {
            return $plan;
        }

        if ($refusal !== null) {
            throw new FieldRefused($plan);
        }

        $field->delete();

        return $plan;
    }

    /**
     * The order the list was dragged into: every key once (§7.1, §8.1). Fields behind a checkbox are
     * printed right after it whatever their places in the list, so only their order among
     * themselves is read from it.
     *
     * @param  list<string>  $keys
     *
     * @throws FieldRefused
     */
    public function arrange(array $keys, bool $dryRun = false): FieldPlan
    {
        $fields = OrderField::query()->get(['id', 'key', 'position'])->keyBy('key');
        $keys = array_values(array_map(static fn (mixed $key): string => is_scalar($key) ? (string) $key : '', $keys));
        $known = array_map(strval(...), $fields->keys()->all());
        $same = count($keys) === count(array_unique($keys)) && array_diff($keys, $known) === [] && array_diff($known, $keys) === [];
        $plan = new FieldPlan($same ? null : FieldRefused::ARRANGEMENT, $same ? [FieldPlan::REORDERED] : [], $same ? null : 'keys', dryRun: $dryRun);

        if ($dryRun) {
            return $plan;
        }

        if (! $same) {
            throw new FieldRefused($plan);
        }

        $this->db->transaction(function () use ($keys, $fields): void {
            // Thirty rows at most, written only when the list is dragged: a statement a row.
            foreach ($keys as $position => $key) {
                $field = $fields->get($key);

                if ($field !== null && $field->position !== $position) {
                    OrderField::query()->whereKey($field->id)->update(['position' => $position]);
                }
            }

            // A mass update fires no event of the model: the group is moved by hand.
            $this->groups->bump(self::GROUP);
        });

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function valid(array $input, bool $new): array
    {
        /** @var array<string, mixed> */
        return $this->validation->make($input, [
            'key' => [$new ? 'required' : 'sometimes', 'string', 'max:64'],
            'type' => [$new ? 'required' : 'sometimes', 'string', Rule::in(OrderField::TYPES)],
            'place' => ['sometimes', 'nullable', 'string', 'max:64'],
            'label' => ['sometimes', 'array'],
            'label.*' => ['nullable', 'string', 'max:'.OrderField::MAX_LABEL],
            'hint' => ['sometimes', 'nullable', 'array'],
            'hint.*' => ['nullable', 'string', 'max:'.OrderField::MAX_HINT],
            'options' => ['sometimes', 'nullable', 'array'],
            'options.*' => ['array'],
            'options.*.value' => ['nullable', 'string', 'max:64'],
            'options.*.label' => ['nullable', 'array'],
            'options.*.label.*' => ['nullable', 'string', 'max:'.OrderField::MAX_LABEL],
            'required' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'parent' => ['sometimes', 'nullable', 'string', 'max:64'],
        ])->validate();
    }

    /**
     * The first rule of §7.3 the definition breaks, and the input it is about.
     *
     * @param  array<string, string>  $label
     * @param  list<array{value: string, label: array<string, string>}>  $options
     * @param  list<string>  $dependents
     * @return array{0: string|null, 1: string|null}
     */
    private function refusal(
        ?OrderField $field,
        string $key,
        string $type,
        string $place,
        array $label,
        array $options,
        ?string $parentKey,
        ?OrderField $parent,
        array $dependents,
        string $fallback,
    ): array {
        if ($field !== null && ($key !== $field->key || $type !== $field->type)) {
            return [FieldRefused::LOCKED, $key !== $field->key ? 'key' : 'type'];
        }

        if (preg_match(OrderField::KEY, $key) !== 1) {
            return [FieldRefused::KEY_INVALID, 'key'];
        }

        if ($field === null && OrderField::query()->where('key', $key)->exists()) {
            return [FieldRefused::KEY_TAKEN, 'key'];
        }

        if ($field === null && OrderField::query()->count() >= OrderField::MAX_FIELDS) {
            return [FieldRefused::TOO_MANY, 'key'];
        }

        if (! $this->places->has($place)) {
            return [FieldRefused::PLACE_INVALID, 'place'];
        }

        if (($label[$fallback] ?? '') === '') {
            return [FieldRefused::NO_LABEL, 'label'];
        }

        if (($type === OrderField::SELECT) !== ($options !== [])) {
            return [FieldRefused::NO_OPTIONS, 'options'];
        }

        if (count($options) > OrderField::MAX_OPTIONS) {
            return [FieldRefused::TOO_MANY, 'options'];
        }

        $values = array_column($options, 'value');

        foreach ($options as $option) {
            if (preg_match(OrderField::KEY, $option['value']) !== 1 || ($option['label'][$fallback] ?? '') === '') {
                return [FieldRefused::OPTION_INVALID, 'options'];
            }
        }

        if (count($values) !== count(array_unique($values))) {
            return [FieldRefused::OPTION_INVALID, 'options'];
        }

        if ($parentKey !== null && (
            $parent === null
            || $parent->key === $key
            || $parent->type !== OrderField::CHECKBOX
            || $parent->parent_id !== null
            || $parent->place !== $place
            || $dependents !== []
        )) {
            return [FieldRefused::PARENT_INVALID, 'parent'];
        }

        return [null, null];
    }

    /**
     * What the buyer will see because of the change.
     *
     * @param  list<array{value: string, label: array<string, string>}>  $options
     * @return list<string>
     */
    private function effects(?OrderField $field, bool $required, bool $active, string $place, array $options): array
    {
        if ($field === null) {
            return [FieldPlan::CREATED];
        }

        $effects = [];

        if ($required !== $field->required) {
            $effects[] = $required ? FieldPlan::REQUIRED_ON : FieldPlan::REQUIRED_OFF;
        }

        if ($active !== $field->active) {
            $effects[] = $active ? FieldPlan::SHOWN : FieldPlan::HIDDEN;
        }

        if ($place !== $field->place) {
            $effects[] = FieldPlan::MOVED;
        }

        if (array_diff($field->optionValues(), array_column($options, 'value')) !== []) {
            $effects[] = FieldPlan::OPTIONS_REMOVED;
        }

        return $effects;
    }

    /** @return list<string> the keys of the fields shown behind this one, in their order */
    private function dependents(OrderField $field): array
    {
        return array_values(array_map(strval(...), $field->dependents()->orderBy('position')->orderBy('id')->pluck('key')->all()));
    }

    /**
     * @param  array<mixed>|null  $words
     * @return array<string, string>
     */
    private static function words(?array $words): array
    {
        $kept = [];

        foreach ($words ?? [] as $locale => $value) {
            if (is_string($value) && trim($value) !== '') {
                $kept[(string) $locale] = trim($value);
            }
        }

        return $kept;
    }

    /**
     * @return list<array{value: string, label: array<string, string>}>
     */
    private static function options(mixed $options): array
    {
        $kept = [];

        foreach (is_array($options) ? $options : [] as $option) {
            if (! is_array($option)) {
                continue;
            }

            $kept[] = [
                'value' => strtolower(trim(is_string($option['value'] ?? null) ? $option['value'] : '')),
                'label' => self::words(is_array($option['label'] ?? null) ? $option['label'] : null),
            ];
        }

        return $kept;
    }
}
