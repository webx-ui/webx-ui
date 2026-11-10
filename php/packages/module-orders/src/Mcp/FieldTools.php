<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use Closure;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Checkout\FieldPlace;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\FieldPlan;
use WebxUi\Orders\FieldRefused;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\OrderFields;
use WebxUi\Orders\Panel\FieldBoard;
use WebxUi\Orders\Panel\OrdersModule;

/**
 * The extra fields of the checkout for an agent (WEBX_UI_ORDER_FIELDS.md §8.1), a part of
 * {@see CheckoutTools}: registered only while the checkout is on.
 *
 * The list is the panel's own ({@see FieldBoard}), and every change is a call of `OrderFields`, the
 * one the panel's API makes: a dry run answers the lines the panel's dialog shows, a refusal the
 * panel's words with what the agent does next. A field is named by its key, which never changes.
 */
final readonly class FieldTools
{
    use AnswersAgents;

    public function __construct(
        private OrderFields $fields,
        private FieldBoard $board,
        private FieldPlaces $places,
        private CheckoutSteps $steps,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $key = ['type' => 'string', 'description' => 'The field\'s key: `delivery_time` (orders_checkout_fields_list).'];
        $words = static fn (string $what): array => ['type' => ['object', 'null'], 'description' => "{$what} by language code: { \"en\": …, \"ru\": … }; a language sent empty is removed.", 'additionalProperties' => ['type' => ['string', 'null']]];

        // Made strict and named `orders_…` by CheckoutTools, whose list they are part of.
        return [
            Tool::read(
                'checkout_fields_list',
                'The extra fields of the checkout, as the panel lists them: the places they may stand in (in the order a buyer '
                .'meets them, each with its step), then every field in its order — key, type, place (`place_known` false — the '
                .'place is gone and the buyer is not asked it), label and hint in every language, options of a list, required, '
                .'active, `parent` — the checkbox it is shown behind; and the limits.',
                fn (): array => $this->board->describe(app()->getLocale()),
                permission: [OrdersModule::VIEW, OrdersModule::CHECKOUT_SETTINGS],
            ),

            Tool::mutating(
                'checkout_fields_save',
                'Add a field by a new `key`, or change the one with this key; what is not sent stays. The key and the type '
                .'never change once saved. A list (`select`) has `options`, nothing else does. `parent` — the key of a checkbox '
                .'of the same place the field is shown behind (one level; null — always shown); without `place` the field '
                .'stands where its checkbox does. Moving a checkbox moves the fields behind it. `dry_run` answers what the buyer '
                .'would see differently («Buyers will not place an order without it.»). Orders placed keep their answers.',
                fn (array $arguments): array => $this->save($arguments),
                ['properties' => [
                    'key' => ['type' => 'string', 'description' => 'Lowercase Latin letters, digits and `_`, starting with a letter, at most 32: what a letter\'s `{{field.<key>}}` says. Other text is made one, as the panel makes it of a label.'],
                    'type' => ['type' => 'string', 'enum' => OrderField::TYPES, 'description' => 'Required for a new field.'],
                    'place' => ['type' => 'string', 'description' => 'The key of a place (orders_checkout_fields_list `places`); `'.FieldPlaces::DEFAULT.'` by default.'],
                    'label' => $words('The label'),
                    'hint' => $words('A line under the field'),
                    'options' => ['type' => 'array', 'description' => 'The options of a list, in their order: the list sent replaces the one kept.', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'value' => ['type' => 'string', 'description' => 'The option\'s key, by the rule of a field\'s key; never changes once orders chose it.'],
                            'label' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                        ],
                        'required' => ['value', 'label'],
                    ]],
                    'required' => ['type' => 'boolean', 'description' => 'A checkbox — must be ticked; anything else — must be answered.'],
                    'active' => ['type' => 'boolean', 'description' => 'Asked at the checkout; off — the buyer sees it no more.'],
                    'parent' => ['type' => ['string', 'null'], 'description' => 'The key of the checkbox it is shown behind; null — always shown.'],
                ], 'required' => ['key']],
                permission: OrdersModule::CHECKOUT_SETTINGS,
            ),

            Tool::mutating(
                'checkout_fields_delete',
                'Delete a field by its key: the buyer is asked it no more. Orders placed keep what their buyers answered — '
                .'nothing of them is touched. A checkbox with fields shown behind it is refused, and the answer names them. '
                .'`dry_run` answers what goes, or why it is refused.',
                fn (array $arguments): array => $this->remove($arguments),
                ['properties' => ['key' => $key], 'required' => ['key']],
                permission: OrdersModule::CHECKOUT_SETTINGS,
            ),

            Tool::mutating(
                'checkout_fields_reorder',
                'The order of the fields: `keys` — every key, each once, first to last; a list that leaves one out, names one '
                .'twice or one that is not there is refused. The places keep their own order (orders_checkout_fields_list); a '
                .'field behind a checkbox is printed right after it, so only their order among themselves is read.',
                fn (array $arguments): array => $this->reorder($arguments),
                ['properties' => ['keys' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['keys']],
                permission: OrdersModule::CHECKOUT_SETTINGS,
            ),
        ];
    }

    /**
     * What `orders://checkout` says of the fields (§8.3): the places with their steps, the
     * definitions, the types, the limits and the codes — all read off the registry, the classes and
     * the table.
     *
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $board = $this->board->describe(app()->getLocale());

        return [
            'places' => array_map(static fn (FieldPlace $place): array => [
                'key' => $place->key,
                'step' => $place->step,
                'label' => $place->name(),
                'default' => $place->key === FieldPlaces::DEFAULT,
            ], $this->places->all($this->steps->positions())),
            'definitions' => $board['fields'],
            'types' => [
                OrderField::CHECKBOX => 'a box; its answer is true, kept only when ticked; required — must be ticked',
                OrderField::TEXT => 'a line, at most '.OrderField::MAX_TEXT.' characters',
                OrderField::TEXTAREA => 'a text, line breaks kept, at most '.OrderField::MAX_TEXTAREA.' characters',
                OrderField::SELECT => 'a list of one choice: its answer is the `value` of an option, at most '.OrderField::MAX_OPTIONS.' options',
            ],
            'limits' => $board['limits'],
            'error_codes' => OrderField::ERROR_CODES,
            'refusals' => FieldRefused::CODES,
            'tools' => ['orders_checkout_fields_list', 'orders_checkout_fields_save', 'orders_checkout_fields_delete', 'orders_checkout_fields_reorder'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function save(array $arguments): array
    {
        $given = is_string($arguments['key'] ?? null) ? $arguments['key'] : '';
        $key = (string) OrderField::codeFor('key', $given);
        $field = $key !== '' ? OrderField::query()->where('key', $key)->first() : null;
        $input = array_intersect_key($arguments, array_flip(['type', 'place', 'label', 'hint', 'options', 'required', 'active', 'parent']));

        if ($field === null) {
            $input['key'] = $given;
        }

        $parent = $input['parent'] ?? null;

        if (is_string($parent) && trim($parent) !== '' && ! OrderField::query()->where('key', trim($parent))->exists()) {
            throw new ToolFailure('Unknown parent „'.trim($parent).'“: orders_checkout_fields_list lists the fields.');
        }

        $place = is_string($input['place'] ?? null) && $input['place'] !== '' ? $input['place'] : null;
        $dryRun = $this->dryRun($arguments);
        $plan = $this->planned(fn (): FieldPlan => $this->fields->write($input, $field, $dryRun), $place);

        return [
            ...$plan,
            'created' => $field === null,
            'definition' => $dryRun ? null : $this->row((string) $plan['key']),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function remove(array $arguments): array
    {
        $field = $this->field($arguments['key'] ?? null);
        $dryRun = $this->dryRun($arguments);

        return $this->planned(fn (): FieldPlan => $this->fields->remove($field, $dryRun));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $keys = $arguments['keys'] ?? null;

        if (! is_array($keys) || $keys === [] || array_filter($keys, is_string(...)) !== $keys) {
            throw new ToolFailure('`keys` is the list of every field\'s key, first to last (orders_checkout_fields_list).');
        }

        $keys = array_values($keys);
        $dryRun = $this->dryRun($arguments);
        $plan = $this->planned(fn (): FieldPlan => $this->fields->arrange($keys, $dryRun), keys: $keys);

        return [...$plan, 'fields' => $dryRun ? null : array_column($this->board->describe(app()->getLocale())['fields'], 'key')];
    }

    /**
     * A dry run answers the panel's lines; a refusal — dry or not — is a `ToolFailure` in the
     * panel's words and what to do next.
     *
     * @param  Closure(): FieldPlan  $work
     * @param  list<string>  $keys  the order asked for, to say what is wrong with it
     * @return array<string, mixed>
     */
    private function planned(Closure $work, ?string $place = null, array $keys = []): array
    {
        $locale = app()->getLocale();

        try {
            $plan = $this->attempt($work);
        } catch (FieldRefused $refused) {
            $plan = $refused->plan;
        }

        if ($plan->refusal !== null) {
            throw new ToolFailure($this->refusal($plan, $locale, $keys));
        }

        return [
            ...$this->board->consequences($plan, $locale, $place),
            'key' => $plan->key,
            'orders_touched' => false,
            'dry_run' => $plan->dryRun,
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private function refusal(FieldPlan $plan, string $locale, array $keys): string
    {
        $words = (string) $this->board->consequences($plan, $locale)['message'];
        $about = $plan->field !== null ? "`{$plan->field}`: " : '';

        $next = match ($plan->refusal) {
            FieldRefused::LOCKED => 'The '.$plan->field.' of a field never changes: delete „'.$plan->key.'“ and add another.',
            FieldRefused::PLACE_INVALID => 'The places: '.implode(', ', $this->places->keys()).'.',
            FieldRefused::KEY_TAKEN => 'orders_checkout_fields_save with that key changes the field instead.',
            FieldRefused::HAS_DEPENDENTS => 'The field „'.$plan->key.'“ has fields shown behind it: '.implode(', ', $plan->dependents).'. Delete them, or give them another `parent`, first.',
            FieldRefused::ARRANGEMENT => $this->arrangement($keys),
            default => null,
        };

        return $about.$words.($next !== null ? ' '.$next : '');
    }

    /**
     * What is wrong with an order asked for: the keys left out, the unknown, the twice named.
     *
     * @param  list<string>  $keys
     */
    private function arrangement(array $keys): string
    {
        $known = array_column($this->board->describe(app()->getLocale())['fields'], 'key');
        $said = [];

        foreach ([
            'Left out' => array_diff($known, $keys),
            'Not a field' => array_diff($keys, $known),
            'Named twice' => array_keys(array_filter(array_count_values($keys), static fn (int $times): bool => $times > 1)),
        ] as $what => $which) {
            if ($which !== []) {
                $said[] = $what.': '.implode(', ', array_unique(array_map(strval(...), $which))).'.';
            }
        }

        return implode(' ', $said);
    }

    /** @return array<string, mixed>|null the field as orders_checkout_fields_list shows it */
    private function row(string $key): ?array
    {
        foreach ($this->board->describe(app()->getLocale())['fields'] as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    private function field(mixed $key): OrderField
    {
        $key = is_string($key) ? (string) OrderField::codeFor('key', $key) : '';

        return ($key !== '' ? OrderField::query()->where('key', $key)->first() : null)
            ?? throw new ToolFailure('No field „'.$key.'“: orders_checkout_fields_list lists the fields.');
    }
}
