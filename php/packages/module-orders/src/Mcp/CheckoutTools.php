<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Money;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Checkout\CheckoutAnswers;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\CheckoutPreview;
use WebxUi\Orders\Checkout\CheckoutSettings;
use WebxUi\Orders\Checkout\ContactMask;
use WebxUi\Orders\Checkout\Events\CheckoutPayableChanged;
use WebxUi\Orders\Checkout\Events\CheckoutPlaced;
use WebxUi\Orders\Checkout\Events\CheckoutStepSaved;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Checkout\Steps\NamesErrors;
use WebxUi\Orders\Checkout\Steps\Step;
use WebxUi\Orders\Checkout\Summary\CheckoutSummaryParts;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Panel\SettingsScreen;

/**
 * The checkout for an agent (spec §32): what the page is made of, a trial confirmation of a cart,
 * where buyers stop, the drafts, the tab «Checkout». Registered only while the checkout is on.
 *
 * A draft is somebody who did not place an order: its contacts come masked ({@see ContactMask});
 * the contacts of an order are `orders_get`. The trial confirmation asks the questions the page's
 * `place` asks ({@see CheckoutPreview}) and writes nothing.
 */
final readonly class CheckoutTools
{
    use AnswersAgents;

    private const int PER_PAGE = 20;

    private const int PER_PAGE_MAX = 100;

    /** The periods of the funnel, in days. */
    private const array PERIODS = ['day' => 1, 'week' => 7, 'month' => 30];

    /** The words of a setting's change, by key, for the dry run of `orders_checkout_settings_set`. */
    private const array FIELDS = ['last_name' => 'last name', 'phone' => 'phone', 'email' => 'e-mail'];

    public function __construct(
        private CheckoutSteps $steps,
        private CheckoutSlots $slots,
        private CheckoutSummaryParts $parts,
        private CheckoutContexts $contexts,
        private CheckoutPreview $preview,
        private OrderSettings $settings,
        private ScreenValues $values,
        private Money $money,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $cart = ['type' => 'integer', 'description' => 'The cart\'s id (orders_checkout_drafts_list, or `cart_id` of an order).'];

        return Arguments::strictAll([
            Tool::read(
                'checkout_steps',
                'The steps of the checkout page in their order: key, title, position, a step of the checkout or a slot of '
                .'another module, the class that owns it. With `cart_id` — whether each is shown for that cart, and why '
                .'not («no shippable lines»). The confirmation is always last and is not a step.',
                fn (array $arguments): array => $this->stepsOf($arguments),
                ['properties' => ['cart_id' => $cart]],
                permission: OrdersModule::VIEW,
            ),

            Tool::read(
                'checkout_preview',
                'Try the confirmation of a cart without placing it: the totals the page shows, every step checked as the '
                .'confirmation checks it, and what the page would answer — `outcome` order (with the amount; `decrease` when '
                .'it comes to less than shown and is placed at once), invalid (the errors by field, a 422 of the page), '
                .'conflict (the `code` of the 409: a line cannot be bought, the amount went up) or empty. The antispam, '
                .'the key of a repeat and the box of the terms are the buyer\'s request and are left out. Writes nothing.',
                fn (array $arguments): array => $this->preview->of($this->cart($arguments['cart_id'] ?? null)),
                ['properties' => ['cart_id' => $cart], 'required' => ['cart_id']],
                permission: OrdersModule::VIEW,
            ),

            Tool::read(
                'checkout_funnel',
                'Where buyers stop, for the last day, week and month: the drafts touched in the period by the last step '
                .'they saved (`confirm` — they placed an order), how many came to an order, and how many times the amount '
                .'they saw was not what the order came to.',
                fn (): array => $this->funnel(),
                permission: OrdersModule::VIEW,
            ),

            Tool::read(
                'checkout_drafts_list',
                'The drafts of the checkout, the last touched first: the cart, the last step, the amount the page showed, '
                .'when. They are people who have not placed an order: names, phones and e-mails come masked '
                .'(`B*** A`, `+800 *** **78`, `b***@example.com`) and are not given in full.',
                fn (array $arguments): array => $this->drafts($arguments),
                ['properties' => [
                    'step' => ['type' => 'string', 'description' => 'Only drafts whose last step is this one; `confirm` — the ones that placed an order.'],
                    'page' => ['type' => 'integer', 'description' => '1 by default.'],
                    'per_page' => ['type' => 'integer', 'description' => '20 by default, at most 100.'],
                ]],
                permission: OrdersModule::VIEW,
            ),

            Tool::read(
                'checkout_settings_get',
                'The tab «Checkout» of the settings: which contacts are asked for, the recipient, the comment, the '
                .'agreement with the terms and its documents — each value with its choices and default. `stored` false — '
                .'the site keeps no settings, and the defaults rule.',
                fn (): array => $this->describeSettings(),
                permission: [OrdersModule::VIEW, OrdersModule::CHECKOUT_SETTINGS],
            ),

            Tool::mutating(
                'checkout_settings_set',
                'Change the tab «Checkout»: `values` of the keys checkout_settings_get answers; the others stay. A phone or '
                .'an e-mail stays required — an order needs one of the two. `dry_run` checks them and answers what changes '
                .'on the page («the step Comment disappears»).',
                fn (array $arguments, ?Authenticatable $caller = null): array => $this->settingsSet($arguments, $caller),
                ['properties' => ['values' => ['type' => 'object']], 'required' => ['values']],
                permission: OrdersModule::CHECKOUT_SETTINGS,
            ),
        ], 'orders_');
    }

    /**
     * @return list<McpResource>
     */
    public function resources(): array
    {
        return [
            new McpResource(
                'orders://checkout',
                'Checkout',
                'The checkout page as it stands on this site: the steps and the slots of other modules in their order, the '
                .'parts of the column of the totals, the settings of the tab «Checkout» with their values, the codes a step\'s '
                .'error and the confirmation\'s refusal come with, the events. Read it before trying a cart or changing the tab.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $positions = $this->steps->positions();

        return [
            'steps' => array_map(fn (Step $step): array => [
                ...$this->step($step, $positions),
                'error_codes' => $step instanceof NamesErrors ? $step->errorCodes() : null,
            ], $this->steps->all()),
            // The places other modules fill (§22): null — the module is not installed, no such step.
            'slots' => array_map(fn (string $key): array => [
                'key' => $key,
                'position' => CheckoutSlots::POSITIONS[$key],
                'registered' => ($slot = $this->slots->get($key)) !== null ? $slot::class : null,
            ], array_keys(CheckoutSlots::POSITIONS)),
            'summary_parts' => $this->parts->describe(),
            'settings' => $this->describeSettings(),
            'answers' => CheckoutAnswers::ALL,
            'events' => [
                CheckoutStepSaved::class => 'a step was kept in the draft (cartId, step)',
                CheckoutPayableChanged::class => 'the amount the buyer saw is not what the order came to (cartId, from, to)',
                CheckoutPlaced::class => 'the page placed an order, after the commit (orderId, cartId); the order itself — OrderPlaced',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function stepsOf(array $arguments): array
    {
        $positions = $this->steps->positions();
        $context = isset($arguments['cart_id']) ? $this->contexts->of($this->cart($arguments['cart_id'])) : null;

        return [
            'steps' => array_map(function (Step $step) use ($positions, $context): array {
                $row = $this->step($step, $positions);

                if ($context !== null) {
                    $shown = $step->applies($context);
                    $row['shown'] = $shown;
                    $row['why_not'] = $shown ? null : $this->whyNot($step);
                }

                return $row;
            }, $this->steps->all()),
            'confirm' => 'last, always: the column of the totals with the confirmation',
            'cart_id' => $context?->cart?->id,
        ];
    }

    /**
     * @param  array<string, int>  $positions
     * @return array{key: string, title: string, position: int|null, kind: string, owner: class-string}
     */
    private function step(Step $step, array $positions): array
    {
        $slot = $step instanceof SlotStep ? $step->slot() : null;

        return [
            'key' => $step->key(),
            'title' => $step->title(),
            'position' => $positions[$step->key()] ?? null,
            'kind' => $slot !== null ? 'slot' : 'step',
            'owner' => $slot !== null ? $slot::class : $step::class,
        ];
    }

    private function whyNot(Step $step): string
    {
        return match ($step->key()) {
            CheckoutSlots::DELIVERY => 'no shippable lines in the cart',
            CheckoutSlots::PAYMENT => 'nothing to pay, or no method of payment for this cart',
            default => 'the step does not apply to this cart',
        };
    }

    /**
     * Every period in one pass over the drafts of the month (the index of `updated_at`, §34).
     *
     * @return array<string, mixed>
     */
    private function funnel(): array
    {
        $now = Carbon::now();
        $since = array_map(static fn (int $days): Carbon => $now->copy()->subDays($days), self::PERIODS);
        $select = ['last_step'];
        $bindings = [];

        foreach ($since as $period => $from) {
            $select[] = "SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END) AS {$period}_drafts";
            $select[] = "SUM(CASE WHEN updated_at >= ? THEN payable_changes ELSE 0 END) AS {$period}_changes";
            array_push($bindings, $from, $from);
        }

        $rows = CheckoutDraft::query()
            ->selectRaw(implode(', ', $select), $bindings)
            ->where('updated_at', '>=', $since['month'])
            ->groupBy('last_step')
            ->toBase()
            ->get();

        $funnel = [];

        foreach (array_keys(self::PERIODS) as $period) {
            $byStep = [];
            $changes = 0;

            foreach ($rows as $row) {
                $count = (int) $row->{$period.'_drafts'};
                $changes += (int) $row->{$period.'_changes'};

                if ($count > 0) {
                    $byStep[$row->last_step ?? 'none'] = $count;
                }
            }

            arsort($byStep);
            $funnel[$period] = [
                'since' => $since[$period]->toIso8601String(),
                'drafts' => array_sum($byStep),
                'by_last_step' => $byStep,
                'placed' => $byStep[CheckoutSteps::CONFIRM] ?? 0,
                'payable_changes' => $changes,
            ];
        }

        return [
            ...$funnel,
            'steps' => array_map(static fn (Step $step): string => $step->key(), $this->steps->all()),
            'note' => '`none` — a draft whose first step is not saved yet; `confirm` — the draft placed an order.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function drafts(array $arguments): array
    {
        $perPage = (int) ($arguments['per_page'] ?? self::PER_PAGE);

        if ($perPage < 1 || $perPage > self::PER_PAGE_MAX) {
            throw new ToolFailure('`per_page` is from 1 to '.self::PER_PAGE_MAX.'.');
        }

        $query = CheckoutDraft::query()
            ->select('order_checkout_drafts.*', 'carts.currency as cart_currency', 'carts.user_id as cart_user_id')
            ->join('carts', 'carts.id', '=', 'order_checkout_drafts.cart_id')
            ->orderByDesc('order_checkout_drafts.updated_at')
            ->orderByDesc('order_checkout_drafts.cart_id');

        if (isset($arguments['step'])) {
            $query->where('order_checkout_drafts.last_step', (string) $arguments['step']);
        }

        $page = $query->paginate($perPage, page: max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'drafts' => array_map(fn (CheckoutDraft $draft): array => [
                'cart_id' => $draft->cart_id,
                'signed_in' => $draft->getAttribute('cart_user_id') !== null,
                'last_step' => $draft->last_step,
                'customer' => ContactMask::contact($draft->customer),
                'recipient' => ContactMask::contact($draft->recipient),
                'comment' => $draft->comment !== null && $draft->comment !== '',
                'shown_payable' => $draft->shown_payable,
                'shown_payable_formatted' => $draft->shown_payable !== null && is_string($draft->getAttribute('cart_currency'))
                    ? $this->money->format($draft->shown_payable, $draft->getAttribute('cart_currency'))
                    : null,
                'payable_changes' => $draft->payable_changes,
                'updated_at' => $draft->updated_at?->toIso8601String(),
            ], $page->items()),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }

    /**
     * @return array{stored: bool, values: array<string, array{value: mixed, default: mixed, choices: list<string>|string}>}
     */
    private function describeSettings(): array
    {
        $values = [];

        foreach ($this->settings->checkout() as $key => $value) {
            $values[$key] = ['value' => $value, 'default' => OrderSettings::CHECKOUT[$key], 'choices' => self::choices($key)];
        }

        return ['stored' => $this->settings->stored() !== null, 'values' => $values];
    }

    /** @return list<string>|string */
    private static function choices(string $key): array|string
    {
        return match (true) {
            str_starts_with($key, 'orders.checkout.fields.') => [CheckoutSettings::REQUIRED, CheckoutSettings::OPTIONAL, CheckoutSettings::HIDDEN],
            $key === 'orders.checkout.terms' => [CheckoutSettings::TERMS_NONE, CheckoutSettings::TERMS_NOTICE, CheckoutSettings::TERMS_CHECKBOX],
            $key === 'orders.checkout.quick.message' => 'an object of language → text ({"en": "…"}) the dialog «Buy in one click» says after the order, or null for the package\'s words',
            str_ends_with($key, '_page') => 'a link as the panel keeps it ({"target": "entity", "entity_type": "pages", "entity_id": 3} or {"target": "url", "url": "/terms"}), or null',
            default => ['on', 'off'],
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function settingsSet(array $arguments, ?Authenticatable $caller): array
    {
        $given = $arguments['values'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`values` is an object of setting → value; orders_checkout_settings_get lists the keys.');
        }

        Arguments::refuseUnknown($given, array_keys(OrderSettings::CHECKOUT), 'orders_checkout_settings_set `values`');

        if ($this->settings->stored() === null) {
            throw new ToolFailure('This site keeps no settings (module-settings is not installed): the defaults rule, and a person changes them in the code.');
        }

        $can = $caller instanceof HasPermissions ? static fn (string $permission): bool => $caller->hasPermission($permission) : null;

        return $this->attempt(function () use ($given, $can, $arguments): array {
            $valid = $this->values->validate(SettingsScreen::NAME, $given, $can);
            $this->settings->check($valid);
            $changes = $this->changes($this->settings->checkout(), $valid);

            if ($this->dryRun($arguments)) {
                return [...$this->describeSettings(), 'changes' => $changes, 'dry_run' => true];
            }

            $this->settings->store($valid);

            return [...$this->describeSettings(), 'changes' => $changes, 'dry_run' => false];
        });
    }

    /**
     * What the buyer would see differently, a line a setting that changes.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changes(array $before, array $after): array
    {
        $lines = [];

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] == $value) {
                continue;
            }

            $on = ! in_array($value, ['off', false, 0, '0'], true);
            $field = str_starts_with($key, 'orders.checkout.fields.') ? substr($key, strlen('orders.checkout.fields.')) : null;

            $lines[] = match (true) {
                $field !== null => $value === CheckoutSettings::HIDDEN
                    ? 'The field «'.self::FIELDS[$field].'» disappears from the contacts.'
                    : 'The field «'.self::FIELDS[$field]."» is {$value}.",
                $key === 'orders.checkout.comment' => $on ? 'The step «Comment» appears.' : 'The step «Comment» disappears.',
                $key === 'orders.checkout.recipient' => $on ? 'The checkbox «The recipient is someone else» appears.' : 'The checkbox «The recipient is someone else» disappears: the buyer receives the order.',
                $key === 'orders.checkout.terms' => match ($value) {
                    CheckoutSettings::TERMS_NONE => 'No agreement with the terms by the confirmation.',
                    CheckoutSettings::TERMS_CHECKBOX => 'The buyer ticks a box to agree with the terms before the order.',
                    default => 'A line under the confirmation says that placing the order agrees with the terms.',
                },
                $key === 'orders.checkout.quick.enabled' => $on
                    ? 'The button «Buy in one click» appears where the product\'s page prints it.'
                    : 'The button «Buy in one click» disappears, and its address answers 404.',
                $key === 'orders.checkout.quick.message' => 'The words of the dialog «Buy in one click» after an order change.',
                default => 'The link to the '.($key === 'orders.checkout.privacy_page' ? 'privacy policy' : 'terms').' changes.',
            };
        }

        return $lines;
    }

    private function cart(mixed $id): Cart
    {
        return (is_int($id) || ctype_digit((string) $id) ? Cart::query()->find((int) $id) : null)
            ?? throw new ToolFailure('No cart '.json_encode($id).': its id is `cart_id` of orders_checkout_drafts_list or of an order.');
    }
}
