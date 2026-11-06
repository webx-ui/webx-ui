<?php

declare(strict_types=1);

namespace WebxUi\Payments\Mcp;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\DeliveryRule;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\HoldRefused;
use WebxUi\Payments\Kind;
use WebxUi\Payments\MarkRefused;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodGroup;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Panel\AttemptQuery;
use WebxUi\Payments\Panel\AttemptRows;
use WebxUi\Payments\Panel\EventNames;
use WebxUi\Payments\Panel\EventRows;
use WebxUi\Payments\Panel\ExchangeQuery;
use WebxUi\Payments\Panel\GroupForm;
use WebxUi\Payments\Panel\MethodForm;
use WebxUi\Payments\Panel\OrderPaymentCard;
use WebxUi\Payments\Panel\PaymentsSection;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentMarks;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\ProblemRefused;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Words;

/**
 * The payments for an agent (spec §16) — by the panel's own doors, so the two never tell two
 * stories: a method is saved by {@see MethodForm}, a group and the order by {@see GroupForm}, the
 * lists are {@see AttemptQuery} and {@see ExchangeQuery}, an order's payment is
 * {@see OrderPaymentCard}, and every action is a call of {@see Payments} with the source `mcp`.
 *
 * The gateway's keys are neither shown nor taken: a method says which keys are set, never their
 * values (§15.2). The exchange journal is read only (decision 31): a list without bodies for
 * `payments.view`, the headers and bodies of one event for `payments.exchange` (decision 47).
 * Actions on an order are the orders' (§13.1): `orders.manage`. Money is in minor units with a
 * `*_formatted` string beside it.
 */
final class PaymentsTools
{
    private const string SOURCE = 'mcp';

    /** What reads the section — the right to write the methods reads what it writes, as in the panel. */
    private const array READ = [PaymentsSection::VIEW, PaymentsSection::MANAGE];

    private const int PER_PAGE = 20;

    /** A body is given to the agent up to this many bytes (§16); the size and sha256 are whole. */
    public const int BODY_SHOWN = 65536;

    /** The events of one trace an agent reads at once. */
    private const int TRACE_MAX = 50;

    /** What `payments_problems` calls stuck: in progress for longer than this, in minutes. */
    private const int STUCK_MINUTES = 60;

    public function __construct(private readonly Container $container) {}

    /** @return list<Tool> */
    public function all(): array
    {
        $order = ['type' => ['string', 'integer'], 'description' => 'The order: its number (`1024`) or its id; a number wins when both match.'];
        $payment = ['type' => ['string', 'integer'], 'description' => 'The attempt: its uuid, as payments_list gives it, or its id.'];
        $method = ['type' => 'string', 'description' => 'The method by its key — "bank-transfer", as payments_methods_list gives it.'];
        $day = static fn (string $what): array => ['type' => 'string', 'description' => $what.', YYYY-MM-DD.'];

        return Arguments::strictAll([
            Tool::read(
                'methods_list',
                'The payment methods in the order of the checkout: key, type and its title, kind (offline, online), the '
                .'gateway, the title in the language of the site and by language, enabled, position, the rule of delivery, '
                .'the limits of the amount (minor units and in words), the settings of the type (the bank details of a '
                .'transfer), the hold, the address the gateway posts its notifications to, the group it stands in (`group`; '
                .'`group_key` — the move a manager made, null — it follows its type), its `marks`, `similar_to` — the '
                .'methods of its group a buyer could mistake it for, and the keys as '
                .'`credentials: ["secret_key: set", "shop_id: missing"]` — never their values.',
                fn (): array => $this->methods(),
                permission: self::READ,
            ),

            Tool::mutating(
                'methods_save',
                'Create a method of a type (`type`, as payments://catalog lists them) or change one by its `key`. `values`: '
                .'titles and descriptions (a string — the default language — or { "en": "…" }), enabled, group (a key of '
                .'payments_groups_list, the method goes to its end; null — the group of its type), delivery_mode '
                .'(any, on_hand, methods, except) with delivery_methods (keys of delivery methods), min_amount and '
                .'max_amount in major units (4.5 is 4.50; null — no limit), settings (the fields of the type: the bank '
                .'details of a transfer by language, the gateway\'s settings, `hold`; a pair the type does not allow is refused). A field left out keeps what it '
                .'had; a new method is switched off until `enabled: true`. The gateway\'s keys are set in the panel only. '
                .'`warnings`: what the panel would warn of, such as a method of the group with the same marks; never a refusal.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->saveMethod($arguments, $user)),
                ['properties' => [
                    'key' => $method,
                    'type' => ['type' => 'string', 'description' => 'The type of a new method: "store.bank_transfer", "store.on_receipt" or a gateway\'s.'],
                    'values' => ['type' => 'object', 'description' => 'Field → value; see the description.'],
                ], 'required' => ['values']],
            ),

            Tool::mutating(
                'methods_reorder',
                'Put the methods in a new order — the order the checkout lists them in inside their groups. `methods`: '
                .'keys, first to last, those left out follow in the order they had; no method changes group. `groups`: '
                .'[{ "key": "online", "methods": ["…"] }] — the methods of each group named, first to last, as dragged in the '
                .'panel: one named under another group moves there, one left out stays in its group after those named. The '
                .'order of the groups themselves is payments_groups_reorder.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'methods' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Method keys, first to last.'],
                    'groups' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Instead of `methods`: { key, methods } of the groups to change.'],
                ]],
            ),

            Tool::read(
                'groups_list',
                'The groups of the checkout in the order the buyer sees them, empty ones too: key, title and description in '
                .'the language of the site and by language (`titles`, `descriptions` — the manager\'s edits; `config_title` — '
                .'what an empty title falls back to), the pictogram, the keys of its methods (switched off too) and the marks '
                .'its enabled methods show. Which groups there are is the site\'s config: an empty list means a flat checkout.',
                fn (): array => $this->groups(),
                permission: self::READ,
            ),

            Tool::mutating(
                'groups_save',
                'Change a group by its `key`, as «Edit» of the panel: `values` — titles and descriptions (a string — the '
                .'default language — or { "en": "…" }; the languages not named stay, an empty one goes back to the config, '
                .'null clears them all) and pictogram (a name of payments://catalog; null — the config\'s). A field left out '
                .'keeps what it had. A group is neither created nor removed here: that is the site\'s config.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->saveGroup($arguments)),
                ['properties' => [
                    'key' => ['type' => 'string', 'description' => 'The group, as payments_groups_list gives it.'],
                    'values' => ['type' => 'object', 'description' => 'Field → value; see the description.'],
                ], 'required' => ['key', 'values']],
            ),

            Tool::mutating(
                'groups_reorder',
                'Put the groups in the order the buyer sees them. Name them by key, first to last; those left out follow '
                .'in the order they had. The methods stay in their groups.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorderGroups($arguments)),
                ['properties' => [
                    'groups' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Group keys, first to last.'],
                ], 'required' => ['groups']],
            ),

            Tool::read(
                'available',
                'A trial of the checkout\'s step: every enabled method for an amount, a currency and a delivery — or for '
                .'a real cart (`cart_id`) as its checkout stands — with whether it is offered and, if not, why (nothing '
                .'to pay, the type not installed, the currency, the limits, the rule of delivery, no keys), its `group`, and '
                .'`apple_pay_only`: such a method is offered here, yet the buyer\'s browser decides whether it is shown '
                .'(`apple_pay` says so). Nothing is kept.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->available($arguments)),
                ['properties' => [
                    'amount' => ['type' => 'integer', 'description' => '«To pay» in minor units.'],
                    'currency' => ['type' => 'string', 'description' => 'ISO code; the site\'s currency when omitted.'],
                    'delivery' => ['type' => 'object', 'description' => 'The chosen delivery: { "method": "store-pickup", "carrier": "store", "cash_on_delivery": false, "store_pickup": true }; omitted — no delivery.'],
                    'cart_id' => ['type' => 'integer', 'description' => 'A cart of the site, as cart_list gives it: its total and its chosen delivery.'],
                ]],
                permission: self::READ,
            ),

            Tool::read(
                'list',
                'Attempts to pay, newest first, twenty a page: the order\'s number, the method, the amount, the status '
                .'(created, pending, authorized, paid, failed, cancelled, expired), the problem, the gateway\'s id, when. '
                .'`tab`: pending (in progress and held), failed, problems; `search` — a uuid, a gateway\'s id or an '
                .'order\'s number; a method and a period.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->attempts($arguments)),
                ['properties' => [
                    'tab' => ['type' => 'string', 'enum' => ['pending', 'failed', 'problems'], 'description' => 'All when omitted.'],
                    'status' => ['type' => 'string', 'enum' => array_map(static fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases())],
                    'method' => $method,
                    'search' => ['type' => 'string'],
                    'from' => $day('Created on or after'),
                    'to' => $day('Created on or before'),
                    'page' => ['type' => 'integer', 'description' => '1 by default.'],
                ]],
                permission: self::READ,
            ),

            Tool::read(
                'get',
                'One attempt (`payment`) with its events of the exchange by time — or the whole payment of an order '
                .'(`order`), as its tab «Payment» shows it: the method and its bank details, the state, what is due and '
                .'paid, every attempt with its events, how it was paid, and the actions the caller may take. Events are '
                .'lines — direction, kind, code, signature, decision — never a header or a body: payments_exchange_get has those.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->show($arguments, $user)),
                ['properties' => ['payment' => $payment, 'order' => $order]],
                permission: self::READ,
            ),

            Tool::mutating(
                'check',
                'Ask the gateway about an attempt in progress or held and apply what it says — «Check with the gateway» '
                .'of the panel. Name the attempt, or the order (its latest unfinished attempt). A dry run asks the gateway '
                .'all the same — the request is in the exchange journal, as every one is — and answers what it said and what '
                .'would change, writing nothing of the attempt or the order.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->check($arguments, $user)),
                ['properties' => ['payment' => $payment, 'order' => $order]],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'capture',
                'Capture money held by an attempt (status `authorized`) — «Capture» of the order\'s tab «Payment». '
                .'Without `amount` all of the hold; a part only where the gateway takes one (payments://catalog: '
                .'`captures_partially`), never more than is held — what is not captured goes back to the buyer. The order '
                .'gets the milestone «paid» only where what is captured covers what it owes; less is money in without the '
                .'milestone and the rest still to pay — lower the order\'s items first (orders_items_edit) where the rest is not '
                .'to be paid. The journal gets an event `internal/captured`. A gateway that refuses leaves the money held with '
                .'the problem «capture refused». A dry run says what would be captured, what it does to the order, and asks '
                .'the gateway nothing.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->capture($arguments, $user)),
                ['properties' => [
                    'payment' => $payment,
                    'amount' => ['type' => 'string', 'description' => 'A decimal in the attempt\'s currency, "120.50"; all of the hold when omitted.'],
                ], 'required' => ['payment']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'void',
                'Release money held by an attempt (status `authorized`) — «Release the hold» of the order\'s tab: the gateway '
                .'lets the money go back to the buyer, the attempt becomes `cancelled` and the order awaits payment as '
                .'before — a release by the shop is no payment that failed. The journal gets an event `internal/released`. A '
                .'dry run says what would be released and asks the gateway nothing.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->void($arguments, $user)),
                ['properties' => ['payment' => $payment], 'required' => ['payment']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'mark_paid',
                'The money came by transfer or on receipt: «Mark as paid» of the order\'s card. An attempt `paid` for what '
                .'the order owes, the milestone «paid» with the document\'s number, an event `internal/manual` in the '
                .'exchange journal; the order stops awaiting payment. Never an amount: the whole debt. A dry run says the '
                .'milestone, the attempt and what closes.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->markPaid($arguments, $user)),
                ['properties' => [
                    'order' => $order,
                    'reference' => ['type' => 'string', 'description' => 'The document: the number of the transfer, the receipt.'],
                    'paid_at' => $day('The day the money came; today when omitted'),
                    'note' => ['type' => 'string'],
                ], 'required' => ['order']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'switch_method',
                'Another method for an order that still owes: the order\'s block «payment» is replaced, its unfinished '
                .'attempts are closed, the order awaits payment or stops awaiting it as the new method does, and an online '
                .'method gets an attempt — the buyer pays by the link of payments_pay_link. A dry run says the new block, '
                .'what closes and whether «awaiting payment» comes off.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->switchMethod($arguments, $user)),
                ['properties' => ['order' => $order, 'method' => $method], 'required' => ['order', 'method']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::read(
                'pay_link',
                'The signed link to the order\'s page of payment and the amount the buyer will see there (`due`). The link '
                .'opens the payment of somebody\'s order, so it is behind the right to work the orders. An order that owes '
                .'nothing gets no link and «Order 1024 is paid».',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->payLink($arguments)),
                ['properties' => ['order' => $order], 'required' => ['order']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::read(
                'problems',
                'What needs a hand: the problems not yet resolved by kind (paid twice, a wrong amount, money for a '
                .'cancelled order, a hold about to end, a capture refused) with their attempts, the attempts in progress '
                .'for longer than an hour, the orders whose payment did not go through, and the enabled methods without keys.',
                fn (): array => $this->problems(),
                permission: self::READ,
            ),

            Tool::mutating(
                'resolve_problem',
                '«Problem resolved»: the money was given back or the payment put right by hand. The attempt keeps its '
                .'status and its problem; it is marked resolved with the note, and the order\'s history gets a line. '
                .'A hold\'s problem is resolved by capturing or releasing it, not here.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->resolve($arguments, $user)),
                ['properties' => ['payment' => $payment, 'note' => ['type' => 'string']], 'required' => ['payment']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::read(
                'exchange_list',
                'The journal of the exchange with gateways, newest first, fifty a page (keyset: `before` — the `next` of '
                .'the page before): time, gateway, method, attempt, order, direction (out, in, browser, internal), kind, '
                .'HTTP code, signature, decision, the sizes of the bodies — never a body. Filters as in the panel: gateway, '
                .'method, attempt (uuid), order (number), kind, direction, `signature` — bad signatures, `errors` — '
                .'rejected, unmatched, an error, a code of 400 and more, a transport failure; a period. Read only.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->exchange($arguments, $user),
                ['properties' => [
                    'gateway' => ['type' => 'string'],
                    'method' => $method,
                    'payment' => ['type' => 'string', 'description' => 'The attempt\'s uuid.'],
                    'order' => ['type' => 'string', 'description' => 'The order\'s number.'],
                    'kind' => ['type' => 'string', 'description' => 'start, status, notify, return, handoff, capture, … — payments://catalog.'],
                    'direction' => ['type' => 'string', 'enum' => ['out', 'in', 'browser', 'internal']],
                    'signature' => ['type' => 'boolean', 'description' => 'Only the bad signatures.'],
                    'errors' => ['type' => 'boolean', 'description' => 'Only the errors.'],
                    'from' => $day('On or after'),
                    'to' => $day('On or before'),
                    'before' => ['type' => 'integer', 'description' => 'The `next` of the previous page.'],
                ]],
                permission: self::READ,
            ),

            Tool::read(
                'exchange_get',
                'One event of the exchange journal (`event`), or every event of a trace (`trace`), with the headers and '
                .'the bodies of the request and the answer as the journal keeps them — the shop\'s keys masked. A body is '
                .'text (UTF-8) up to 64 KB, or base64 when it is binary; `size` and `sha256` are of the whole body, '
                .'`truncated` — the journal itself cut it, `cut` — this answer shows only the first 64 KB. Read only; the '
                .'bodies hold the buyer\'s contacts, hence the right payments.exchange.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->exchangeEvent($arguments, $user)),
                ['properties' => [
                    'event' => ['type' => 'integer', 'description' => 'The event\'s id, as payments_exchange_list gives it.'],
                    'trace' => ['type' => 'string', 'description' => 'A trace: every event of one action.'],
                ]],
                permission: PaymentsSection::EXCHANGE,
            ),
        ], 'payments_');
    }

    // Methods

    /** @return array<string, mixed> */
    private function methods(): array
    {
        $form = $this->container->make(MethodForm::class);

        return [
            'methods' => PaymentMethod::query()->orderBy('position')->orderBy('id')->get()
                ->map(fn (PaymentMethod $method): array => $this->method($method, $form))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function method(PaymentMethod $method, MethodForm $form): array
    {
        return $this->described($method, $form->describe($method));
    }

    /**
     * @param  array<string, mixed>  $described  of `MethodForm::describe()`
     * @return array<string, mixed>
     */
    private function described(PaymentMethod $method, array $described): array
    {
        $row = $described['method'];
        $currency = $this->currency();
        $money = $this->money();

        return [
            ...$row,
            'credentials_state' => $row['credentials'],
            // Which keys are there, never what they are (§15.2).
            'credentials' => array_map(
                static fn (array $key): string => $key['name'].': '.($key['set'] ? 'set' : ($key['environment'] ? 'environment' : 'missing')),
                $described['credentials'],
            ),
            'titles' => $method->title,
            'descriptions' => $method->description ?? [],
            'delivery_rule' => DeliveryRule::fromArray($method->delivery_rule)->toArray(),
            'min_amount' => $method->min_amount,
            'min_amount_formatted' => $money->formatOrNull($method->min_amount, $currency),
            'max_amount' => $method->max_amount,
            'max_amount_formatted' => $money->formatOrNull($method->max_amount, $currency),
            'settings' => $described['settings']['values'],
            'hold_settings' => $described['hold'],
            'notify_url' => $described['notify_url'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveMethod(array $arguments, ?Authenticatable $user): array
    {
        $values = $arguments['values'] ?? null;

        if (! is_array($values)) {
            throw new ToolFailure('`values` is an object of field → value.');
        }

        // Neither shown nor taken (§15.2): a key typed into a chat would be in its log for good.
        foreach (['credentials', 'keys', 'clear', 'secret', 'secret_key'] as $field) {
            if (array_key_exists($field, $values)) {
                throw new ToolFailure('Gateway keys are set in the panel only.');
            }
        }

        Arguments::refuseUnknown($values, ['titles', 'descriptions', 'enabled', 'group', 'delivery_mode', 'delivery_methods', 'min_amount', 'max_amount', 'settings'], 'payments_methods_save');

        $key = $arguments['key'] ?? null;
        $types = $this->container->make(MethodTypes::class);

        if (is_string($key) && $key !== '') {
            $method = PaymentMethod::query()->where('key', $key)->first()
                ?? throw new ToolFailure("There is no method {$key}. payments_methods_list names them.");
        } else {
            $type = $arguments['type'] ?? null;

            if (! is_string($type) || ! $types->has($type)) {
                throw new ToolFailure('A new method needs `type`, one of: '.implode(', ', array_map(static fn ($one): string => $one->key(), $types->all())).'.');
            }

            $method = new PaymentMethod(['type' => $type]);
        }

        $settings = $values['settings'] ?? null;

        if ($settings !== null && ! is_array($settings)) {
            throw new ToolFailure('`settings` is an object of the type\'s fields.');
        }

        if (is_array($settings) && $types->has($method->type)) {
            $this->refuseKeys($method, $settings);
        }

        $screen = [];

        foreach (['titles', 'descriptions'] as $field) {
            if (array_key_exists($field, $values)) {
                $screen[$field] = $this->texts($values[$field], $field);
            }
        }

        foreach (['enabled', 'delivery_mode', 'delivery_methods', 'min_amount', 'max_amount'] as $field) {
            if (array_key_exists($field, $values)) {
                $screen[$field] = $values[$field];
            }
        }

        if (array_key_exists('group', $values)) {
            $screen['group_key'] = $values['group'];
        }

        $created = ! $method->exists;
        $form = $this->container->make(MethodForm::class);
        $can = $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;

        $work = function () use ($form, $method, $screen, $settings, $can): array {
            $saved = $form->save($method, $screen, $settings, null, null, $can);
            $described = $form->describe($saved);

            return ['method' => $this->described($saved, $described), 'warnings' => $described['warnings']];
        };
        $dryRun = $this->dryRun($arguments);

        return [...($dryRun ? $this->rehearse($work) : $work()), 'created' => $created, 'dry_run' => $dryRun];
    }

    /**
     * A key of the gateway put among the settings is refused, not dropped: the agent would think
     * it was kept.
     *
     * @param  array<string, mixed>  $settings
     */
    private function refuseKeys(PaymentMethod $method, array $settings): void
    {
        $gateway = $this->container->make(MethodForm::class)->blank($method->type)['credentials'] ?? [];

        foreach ($gateway as $field) {
            if (array_key_exists((string) $field['name'], $settings)) {
                throw new ToolFailure('Gateway keys are set in the panel only.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['methods'] ?? null;

        if (array_key_exists('groups', $arguments)) {
            return $given === null ? $this->reorderInGroups($arguments) : throw new ToolFailure('Name `methods` or `groups`, not both.');
        }

        if (! is_array($given) || $given === [] || array_filter($given, 'is_string') !== $given) {
            throw new ToolFailure('`methods` is a list of method keys, first to last.');
        }

        $all = PaymentMethod::query()->orderBy('position')->orderBy('id')->get(['id', 'key']);
        $known = $all->pluck('key')->all();
        $unknown = array_values(array_diff($given, $known));

        if ($unknown !== []) {
            throw new ToolFailure('No such method: '.implode(', ', $unknown).'. payments_methods_list names them.');
        }

        $order = array_values(array_unique([...$given, ...$known]));
        $dryRun = $this->dryRun($arguments);

        if (! $dryRun) {
            $ids = $all->pluck('id', 'key')->all();

            DB::transaction(static function () use ($order, $ids): void {
                foreach ($order as $position => $key) {
                    PaymentMethod::query()->whereKey($ids[$key])->update(['position' => $position]);
                }
            });

            // A mass update fires no model events: the step's list is cached (§18).
            $this->container->make(CacheGroups::class)->bump(Methods::GROUP);
        }

        return ['methods' => $order, 'dry_run' => $dryRun];
    }

    /**
     * The methods as dragged in the panel, of the groups named: the rest of the list stays as it is.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorderInGroups(array $arguments): array
    {
        $form = $this->container->make(GroupForm::class);
        $layout = $form->layout();
        $named = $this->namedInGroups($arguments['groups'], array_keys($layout));
        $ids = [];

        foreach (array_merge([], ...array_values($layout)) as $row) {
            $ids[$row->key] = $row->id;
        }

        $all = array_merge([], ...array_values($named));
        $unknown = array_values(array_diff($all, array_keys($ids)));

        if ($unknown !== []) {
            throw new ToolFailure('No such method: '.implode(', ', $unknown).'. payments_methods_list names them.');
        }

        $twice = array_values(array_unique(array_diff_assoc($all, array_unique($all))));

        if ($twice !== []) {
            throw new ToolFailure('Named twice: '.implode(', ', $twice).'.');
        }

        $order = [];

        foreach ($layout as $key => $rows) {
            $stayed = array_map(static fn (PaymentMethod $row): string => $row->key, $rows);
            $order[$key] = [...($named[$key] ?? []), ...array_values(array_diff($stayed, $all))];
        }

        return $this->writeOrder($form, $order, $ids, $arguments);
    }

    /**
     * @param  list<string>  $known  the keys of the groups of the site
     * @return array<string, list<string>> the method keys named, by group
     */
    private function namedInGroups(mixed $groups, array $known): array
    {
        $named = [];

        foreach (is_array($groups) && $groups !== [] ? $groups : [null] as $group) {
            $key = is_array($group) ? ($group['key'] ?? null) : null;
            $methods = is_array($group) ? ($group['methods'] ?? null) : null;

            if (! is_string($key) || ! is_array($methods) || ! array_is_list($methods) || array_filter($methods, 'is_string') !== $methods) {
                throw new ToolFailure('`groups` is a list of { "key": "<group>", "methods": ["<method key>", …] }.');
            }

            if (! in_array($key, $known, true)) {
                throw $this->unknownGroup($key);
            }

            if (array_key_exists($key, $named)) {
                throw new ToolFailure("Named twice: {$key}.");
            }

            $named[$key] = $methods;
        }

        return $named;
    }

    /**
     * The panel's own write of the order, so a move keeps what it keeps there (groups spec §7).
     *
     * @param  array<string, list<string>>  $order  method keys by group
     * @param  array<string, int>  $ids  method ids by key
     * @param  array<string, mixed>  $arguments
     * @return array{groups: list<array{key: string, methods: list<string>}>, dry_run: bool}
     */
    private function writeOrder(GroupForm $form, array $order, array $ids, array $arguments): array
    {
        $groups = [];
        $lines = [];

        foreach ($order as $key => $methods) {
            $groups[] = ['key' => $key, 'methods' => array_map(static fn (string $method): int => $ids[$method], $methods)];
            $lines[] = ['key' => $key, 'methods' => $methods];
        }

        $dryRun = $this->dryRun($arguments);
        $work = static fn () => $form->order($groups);
        $dryRun ? $this->rehearse($work) : $work();

        return ['groups' => $lines, 'dry_run' => $dryRun];
    }

    // Groups

    /** @return array<string, mixed> */
    private function groups(): array
    {
        $form = $this->container->make(GroupForm::class);
        $layout = $form->layout();

        return [
            'groups' => array_map(fn (array $row): array => [
                ...$row,
                'methods' => array_map(static fn (PaymentMethod $method): string => $method->key, $layout[$row['key']]),
                'marks' => $this->marksOf($layout[$row['key']]),
            ], $form->rows()),
        ];
    }

    /**
     * What the enabled methods of a group show: once each, in the order of the page.
     *
     * @param  list<PaymentMethod>  $rows
     * @return list<string>
     */
    private function marksOf(array $rows): array
    {
        $types = $this->container->make(MethodTypes::class);
        $marks = $this->container->make(PaymentMarks::class);
        $shown = [];

        foreach ($rows as $row) {
            if ($row->enabled && $types->has($row->type)) {
                $shown = [...$shown, ...$marks->of(Method::of($row), $types->get($row->type))];
            }
        }

        return $marks->known($shown);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveGroup(array $arguments): array
    {
        $key = $arguments['key'] ?? null;
        $values = $arguments['values'] ?? null;
        $group = null;

        foreach ($this->container->make(MethodGroups::class)->all() as $one) {
            $group = $one->key === $key ? $one : $group;
        }

        if (! $group instanceof MethodGroup) {
            throw $this->unknownGroup($this->scalar($key));
        }

        if (! is_array($values)) {
            throw new ToolFailure('`values` is an object of field → value.');
        }

        Arguments::refuseUnknown($values, ['titles', 'descriptions', 'pictogram'], 'payments_groups_save');

        $input = [];

        foreach (['titles' => $group->titles, 'descriptions' => $group->descriptions] as $field => $kept) {
            if (array_key_exists($field, $values)) {
                $input[$field] = $this->edited($values[$field], $kept, $field);
            }
        }

        if (array_key_exists('pictogram', $values)) {
            $input['pictogram'] = $values['pictogram'];
        }

        $form = $this->container->make(GroupForm::class);
        $work = static fn (): array => $form->save($group->key, $input);
        $dryRun = $this->dryRun($arguments);

        return ['group' => $dryRun ? $this->rehearse($work) : $work(), 'dry_run' => $dryRun];
    }

    /**
     * The words of a group after an edit. The panel always sends every language; an agent names
     * one, and that must not wipe the others. An empty one is kept empty here: the form drops it.
     *
     * @param  array<string, string>  $kept
     * @return array<string, string>
     */
    private function edited(mixed $value, array $kept, string $field): array
    {
        if ($value === null) {
            return [];
        }

        $texts = $this->texts($value, $field);

        return [...$kept, ...(is_array($value) ? array_fill_keys(array_map('strval', array_keys($value)), '') : []), ...$texts];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorderGroups(array $arguments): array
    {
        $given = $arguments['groups'] ?? null;

        if (! is_array($given) || $given === [] || array_filter($given, 'is_string') !== $given) {
            throw new ToolFailure('`groups` is a list of group keys, first to last.');
        }

        $form = $this->container->make(GroupForm::class);
        $layout = $form->layout();
        $unknown = array_values(array_diff($given, array_keys($layout)));

        if ($unknown !== []) {
            throw $this->unknownGroup(implode(', ', $unknown));
        }

        $order = [];
        $ids = [];

        foreach (array_unique([...$given, ...array_keys($layout)]) as $key) {
            $order[$key] = array_map(static fn (PaymentMethod $row): string => $row->key, $layout[$key]);

            foreach ($layout[$key] as $row) {
                $ids[$row->key] = $row->id;
            }
        }

        return $this->writeOrder($form, $order, $ids, $arguments);
    }

    private function unknownGroup(string $key): ToolFailure
    {
        return new ToolFailure("Unknown group: {$key}. payments_groups_list names them; a group is added or removed in the site's config, not here.");
    }

    // The trial

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function available(array $arguments): array
    {
        $methods = $this->container->make(Methods::class);
        $context = isset($arguments['cart_id']) ? $this->cartContext($arguments['cart_id']) : $this->trialContext($arguments);
        $answers = [];

        foreach ($methods->explain($context) as $key => $availability) {
            $method = $methods->find($key);
            $option = $method !== null ? $methods->option($method, $context) : null;
            $answers[] = [
                'key' => $key,
                'title' => $option->title ?? $key,
                'group' => $option?->group,
                'apple_pay_only' => $option->applePayOnly ?? false,
                'available' => $availability->available,
                'reason' => $availability->reason,
                'reason_text' => $availability->message($context->locale),
            ];
        }

        return [
            'amount' => $context->payable,
            'amount_formatted' => $context->currency !== '' ? $this->money()->format($context->payable, $context->currency) : (string) $context->payable,
            'currency' => $context->currency,
            'delivery' => $context->shipping === null ? null : (array) $context->shipping,
            // The step would say «Choose delivery first»: some methods wait for it (§7.3).
            'waiting_for_delivery' => $methods->waitingForDelivery($context),
            'methods' => $answers,
            // The server cannot know the buyer's device: the page asks the browser (groups spec §4).
            'apple_pay' => in_array(true, array_column($answers, 'apple_pay_only'), true)
                ? 'A method marked `apple_pay_only` is shown only where the buyer\'s browser says Apple Pay works: `available` here is not the last word.'
                : null,
        ];
    }

    /** @param  array<string, mixed>  $arguments */
    private function trialContext(array $arguments): PaymentContext
    {
        $amount = $arguments['amount'] ?? null;

        if (! is_int($amount) || $amount < 0) {
            throw new ToolFailure('`amount` is «to pay» in minor units, a whole number; or name a cart with `cart_id`.');
        }

        $currency = is_string($arguments['currency'] ?? null) && $arguments['currency'] !== '' ? strtoupper($arguments['currency']) : $this->currency();
        $delivery = $arguments['delivery'] ?? null;
        $shipping = null;

        if ($delivery !== null) {
            if (! is_array($delivery) || ! is_string($delivery['method'] ?? null) || $delivery['method'] === '') {
                throw new ToolFailure('`delivery` is { "method": "<delivery method key>", "carrier": "store", "cash_on_delivery": false, "store_pickup": false }.');
            }

            $shipping = new ShippingChoice(
                $delivery['method'],
                is_string($delivery['carrier'] ?? null) && $delivery['carrier'] !== '' ? $delivery['carrier'] : 'store',
                (bool) ($delivery['cash_on_delivery'] ?? false),
                (bool) ($delivery['store_pickup'] ?? false),
            );
        }

        return new PaymentContext($amount, $currency, $this->locale(), $shipping, Purpose::Checkout);
    }

    private function cartContext(mixed $id): PaymentContext
    {
        $cart = is_int($id) || (is_string($id) && ctype_digit($id)) ? Cart::query()->find((int) $id) : null;

        if (! $cart instanceof Cart) {
            throw new ToolFailure("There is no cart {$this->scalar($id)}.");
        }

        $checkout = $this->container->make(CheckoutContexts::class)->of($cart);

        return $this->container->make(PaymentsCheckoutSlot::class)->paymentContext($checkout);
    }

    // Attempts

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function attempts(array $arguments): array
    {
        $page = max(1, (int) ($arguments['page'] ?? 1));
        $list = AttemptQuery::filter(Payment::query()->with('order:id,number'), $arguments)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, page: $page);

        return [
            'attempts' => $this->container->make(AttemptRows::class)->lines($list->getCollection()),
            'page' => $list->currentPage(),
            'pages' => $list->lastPage(),
            'total' => $list->total(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function show(array $arguments, ?Authenticatable $user): array
    {
        if (isset($arguments['order'])) {
            return $this->container->make(OrderPaymentCard::class)->card($this->order($arguments['order']), $user);
        }

        if (! isset($arguments['payment'])) {
            throw new ToolFailure('Name the attempt (`payment`) or the order (`order`).');
        }

        return $this->attemptCard($this->payment($arguments['payment']), $user);
    }

    /** @return array<string, mixed> an attempt and its events, as the panel's window of one */
    private function attemptCard(Payment $payment, ?Authenticatable $user): array
    {
        $rows = $this->container->make(AttemptRows::class);
        $payment->loadMissing('order:id,number');

        return [
            ...$rows->named([$rows->line($payment)], $user)[0],
            'events' => $this->lines(EventRows::query()->where('payment_id', $payment->id)->orderBy('id')->limit(200)->get()->all(), $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function check(array $arguments, ?Authenticatable $user): array
    {
        $payment = isset($arguments['payment']) ? $this->payment($arguments['payment']) : $this->unfinished($this->order($arguments['order'] ?? null));

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
            throw new ToolFailure("Payment {$payment->uuid} is {$payment->status->value}: there is nothing to ask the gateway. Only an attempt in progress or held is checked.");
        }

        $dryRun = $this->dryRun($arguments);

        try {
            $result = $this->container->make(Payments::class)->check($payment, self::SOURCE, wait: 5, dryRun: $dryRun);
        } catch (GatewayUnavailable) {
            throw new ToolFailure("The gateway did not answer about payment {$payment->uuid}; the attempt is as it was. The exchange journal has the request.");
        }

        if ($result === null) {
            throw new ToolFailure("Payment {$payment->uuid} is being checked by another request right now; read it again in a moment.");
        }

        return [
            ...$this->settled($result),
            'payment' => $this->attemptCard($payment->refresh(), $user),
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function capture(array $arguments, ?Authenticatable $user): array
    {
        $payment = $this->payment($arguments['payment'] ?? null);
        $typed = is_string($arguments['amount'] ?? null) ? trim($arguments['amount']) : '';

        try {
            $amount = $typed !== '' ? GatewayAmount::fromDecimal($typed, $payment->currency) : null;
        } catch (InvalidArgumentException) {
            throw new ToolFailure("\`amount\` is a decimal in {$payment->currency}, like \"120.50\".");
        }

        return $this->held($payment, $user, fn (bool $dryRun): SettleResult => $this->container->make(Payments::class)->capture($payment, $amount, self::SOURCE, $dryRun), $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function void(array $arguments, ?Authenticatable $user): array
    {
        $payment = $this->payment($arguments['payment'] ?? null);

        return $this->held($payment, $user, fn (bool $dryRun): SettleResult => $this->container->make(Payments::class)->void($payment, self::SOURCE, $dryRun), $arguments);
    }

    /**
     * A capture or a release, with the base's and the gateway's refusals in words.
     *
     * @param  Closure(bool): SettleResult  $action
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function held(Payment $payment, ?Authenticatable $user, Closure $action, array $arguments): array
    {
        $dryRun = $this->dryRun($arguments);

        try {
            $result = $action($dryRun);
        } catch (HoldRefused $refused) {
            throw new ToolFailure("Payment {$payment->uuid}: {$refused->getMessage()}.");
        } catch (GatewayRefused $refused) {
            throw new ToolFailure("The gateway refused to release the hold of payment {$payment->uuid}: {$refused->getMessage()}. The money is still held; the exchange journal has the answer.");
        } catch (GatewayUnavailable) {
            throw new ToolFailure("The gateway did not answer about payment {$payment->uuid}; the attempt is as it was. The exchange journal has the request.");
        }

        return [
            ...$this->settled($result),
            'payment' => $this->attemptCard($payment->refresh(), $user),
            'dry_run' => $dryRun,
        ];
    }

    /** @return array<string, mixed> */
    private function settled(SettleResult $result): array
    {
        return [
            'reported' => $result->reported?->value,
            'outcome' => $result->outcome->value,
            'status_before' => $result->before->value,
            'status_after' => $result->after->value,
            'changed' => $result->changed(),
            'problem' => $result->problem,
            'message' => $result->message,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function markPaid(array $arguments, ?Authenticatable $user): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $reference = is_string($arguments['reference'] ?? null) ? $arguments['reference'] : null;
        $note = is_string($arguments['note'] ?? null) ? $arguments['note'] : null;
        $paidAt = null;

        if (isset($arguments['paid_at'])) {
            $day = $this->scalar($arguments['paid_at']);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                throw new ToolFailure('`paid_at` is a day, YYYY-MM-DD.');
            }

            $paidAt = CarbonImmutable::parse($day)->setTimeFrom(CarbonImmutable::now());
        }

        if ($order->cancelled_at !== null || $order->paid_at !== null) {
            throw new ToolFailure("Order {$order->number}: ".(new MarkRefused($order->cancelled_at !== null ? MarkRefused::CANCELLED : MarkRefused::PAID))->getMessage());
        }

        $block = is_array($order->payment) ? $order->payment : [];
        $due = Balance::of($order)->due;

        if ($this->dryRun($arguments)) {
            // The checks of markPaid() read without the lock; nothing is written.
            return [
                'number' => $order->number,
                'milestone' => 'paid',
                'reference' => $reference,
                'paid_at' => ($paidAt ?? CarbonImmutable::now())->toIso8601String(),
                'attempt' => [
                    'status' => PaymentStatus::Paid->value,
                    'method_key' => $block['method'] ?? null,
                    ...$this->amount('amount', $due, (string) $order->currency),
                ],
                'journal' => 'internal/manual',
                'closes' => [
                    'awaiting_payment' => (bool) $order->awaiting_payment,
                    'payment_failed' => $order->payment_failed_at !== null,
                ],
                'events' => [OrderPaid::class],
                'dry_run' => true,
            ];
        }

        try {
            $payment = $this->container->make(Payments::class)->markPaid($order, self::SOURCE, $reference, $paidAt, $note);
        } catch (MarkRefused $refused) {
            throw new ToolFailure("Order {$order->number}: ".$refused->getMessage());
        }

        $order->refresh();

        return [
            'number' => $order->number,
            'milestone' => 'paid',
            'paid_at' => $order->paid_at?->toIso8601String(),
            'awaiting_payment' => (bool) $order->awaiting_payment,
            'attempt' => $this->attemptCard($payment, $user),
            'dry_run' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function switchMethod(array $arguments, ?Authenticatable $user): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $key = $this->scalar($arguments['method'] ?? '');
        $methods = $this->container->make(Methods::class);
        $method = $methods->find($key)
            ?? throw new ToolFailure("Method {$key} is not an enabled method. payments_methods_list names them.");
        $types = $this->container->make(MethodTypes::class);

        if (! $types->has($method->type)) {
            throw new ToolFailure("Method {$key}: its type {$method->type} is not installed.");
        }

        $type = $types->get($method->type);
        $balance = Balance::of($order);
        $unfinished = Payment::query()->where('order_id', $order->id)
            ->whereIn('status', [PaymentStatus::Created->value, PaymentStatus::Pending->value])
            ->pluck('uuid')->all();

        $refusal = match (true) {
            $order->cancelled_at !== null => BeginRefused::CANCELLED,
            $balance->due <= 0 => BeginRefused::NOTHING_DUE,
            Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Authorized->value)->exists() => BeginRefused::HELD,
            default => null,
        };

        if ($refusal !== null) {
            throw new ToolFailure("Order {$order->number}: ".(new BeginRefused($refusal))->getMessage());
        }

        if ($this->dryRun($arguments)) {
            $context = new PaymentContext($balance->due, (string) $order->currency, (string) $order->locale, null, Purpose::Order);

            return [
                'number' => $order->number,
                'block' => $type->snapshot($method, $context)->toArray(),
                // Closed first; one the gateway reports paid refuses the switch (§9.4).
                'closes' => $unfinished,
                'awaiting_payment' => $type->awaitsPayment(),
                'stops_awaiting' => (bool) $order->awaiting_payment && ! $type->awaitsPayment(),
                'new_attempt' => $type->kind() === Kind::Online,
                ...$this->amount('due', $balance->due, (string) $order->currency),
                'dry_run' => true,
            ];
        }

        try {
            $attempt = $this->container->make(Payments::class)->switchMethod($order, $method, self::SOURCE);
        } catch (BeginRefused $refused) {
            throw new ToolFailure("Order {$order->number}: ".$refused->getMessage());
        }

        $order->refresh();

        return [
            'number' => $order->number,
            'block' => $order->payment,
            'closed' => $unfinished,
            'awaiting_payment' => (bool) $order->awaiting_payment,
            'attempt' => $attempt !== null ? $this->attemptCard($attempt, $user) : null,
            // The buyer pays an online method by the order's page.
            'pay_url' => $attempt !== null ? ($this->container->make(PaymentLinks::class)->page($order) ?: null) : null,
            'dry_run' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function payLink(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);

        if ($order->cancelled_at !== null) {
            throw new ToolFailure("Order {$order->number} is cancelled: it is not paid any more.");
        }

        $due = Balance::of($order)->due;

        if ($due <= 0) {
            return ['number' => $order->number, 'url' => null, ...$this->amount('due', 0, (string) $order->currency), 'message' => "Order {$order->number} is paid"];
        }

        if (Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Authorized->value)->exists()) {
            throw new ToolFailure("Order {$order->number}: the money is held — capture it or let it go before the buyer pays again.");
        }

        $url = $this->container->make(PaymentLinks::class)->page($order);

        if ($url === '') {
            throw new ToolFailure('The site has no page of payment: the route of payments is not loaded.');
        }

        return ['number' => $order->number, 'url' => $url, ...$this->amount('due', $due, (string) $order->currency), 'message' => null];
    }

    /** @return array<string, mixed> */
    private function problems(): array
    {
        $rows = $this->container->make(AttemptRows::class);
        $open = Payment::query()->with('order:id,number')->whereNotNull('problem')->orderByDesc('id')->limit(200)->get()
            ->filter(static fn (Payment $payment): bool => ! isset(($payment->meta ?? [])['resolved_at']))
            ->values();
        $byKind = [];

        foreach ($open as $payment) {
            $byKind[(string) $payment->problem][] = $payment;
        }

        $stuck = Payment::query()->with('order:id,number')
            ->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '<', CarbonImmutable::now()->subMinutes(self::STUCK_MINUTES))
            ->orderBy('id')->limit(50)->get();

        $failed = Order::query()
            ->whereNotNull('payment_failed_at')->whereNull('paid_at')->whereNull('cancelled_at')->where('awaiting_payment', true)
            ->orderByDesc('id')->limit(50)->get(['id', 'number', 'payment_failed_at']);

        $form = $this->container->make(MethodForm::class);
        $similar = $form->similar();
        $keyless = PaymentMethod::query()->where('enabled', true)->orderBy('position')->get()
            ->map(static fn (PaymentMethod $method): array => $form->row($method, $similar))
            ->filter(static fn (array $row): bool => array_intersect($row['warnings'], ['no_credentials', 'credentials_unreadable', 'gateway_missing', 'type_missing']) !== [])
            ->map(static fn (array $row): array => ['key' => $row['key'], 'title' => $row['title'], 'warnings' => $row['warnings']])
            ->values()->all();

        $kinds = [];

        foreach ($byKind as $kind => $payments) {
            $kinds[] = [
                'problem' => $kind,
                'label' => (string) __('webx-payments::statuses.problems.'.$kind),
                'count' => count($payments),
                'attempts' => $rows->lines(collect($payments)),
            ];
        }

        return [
            'problems' => $kinds,
            'stuck' => ['minutes' => self::STUCK_MINUTES, 'attempts' => $rows->lines($stuck)],
            'failed_orders' => $failed->map(static fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'payment_failed_at' => $order->payment_failed_at?->toIso8601String(),
            ])->values()->all(),
            'methods_without_keys' => $keyless,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function resolve(array $arguments, ?Authenticatable $user): array
    {
        $payment = $this->payment($arguments['payment'] ?? null);
        $dryRun = $this->dryRun($arguments);

        // The panel offers it no more once resolved: the first note stays the answer.
        if (isset(($payment->meta ?? [])['resolved_at'])) {
            throw new ToolFailure("Payment {$payment->uuid}: the problem is resolved already.");
        }

        try {
            $this->container->make(Payments::class)->resolveProblem($payment, self::SOURCE, is_string($arguments['note'] ?? null) ? $arguments['note'] : null, $dryRun);
        } catch (ProblemRefused $refused) {
            throw new ToolFailure("Payment {$payment->uuid}: ".$refused->getMessage());
        }

        return ['payment' => $this->attemptCard($payment->refresh(), $user), 'dry_run' => $dryRun];
    }

    // The exchange journal — read only (decision 31)

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function exchange(array $arguments, ?Authenticatable $user): array
    {
        $page = ExchangeQuery::page($arguments);

        return ['events' => $this->lines($page['rows']->all(), $user), 'next' => $page['next']];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function exchangeEvent(array $arguments, ?Authenticatable $user): array
    {
        if (isset($arguments['event'])) {
            $id = $arguments['event'];
            $event = is_int($id) || (is_string($id) && ctype_digit($id)) ? PaymentGatewayEvent::query()->find((int) $id) : null;

            if (! $event instanceof PaymentGatewayEvent) {
                throw new ToolFailure("There is no event {$this->scalar($id)} in the exchange journal. payments_exchange_list finds them.");
            }

            return $this->names([$this->full($event)], $user)[0];
        }

        $trace = $arguments['trace'] ?? null;

        if (! is_string($trace) || $trace === '') {
            throw new ToolFailure('Name the event (`event`) or the trace (`trace`).');
        }

        $events = PaymentGatewayEvent::query()->where('trace', $trace)->orderBy('id')->limit(self::TRACE_MAX)->get();

        if ($events->isEmpty()) {
            throw new ToolFailure("There is no trace {$trace} in the exchange journal.");
        }

        return ['trace' => $trace, 'events' => $this->names($events->map(fn (PaymentGatewayEvent $event): array => $this->full($event))->all(), $user)];
    }

    /** @return array<string, mixed> an event with its headers and bodies, for the agent */
    private function full(PaymentGatewayEvent $event): array
    {
        return [
            ...EventRows::line($event),
            'url' => $event->url,
            'ip' => $event->ip,
            'event_key' => $event->event_key,
            'masked' => (object) ($event->masked ?? []),
            'request' => $this->half($event, 'request'),
            'response' => $this->half($event, 'response'),
        ];
    }

    /** @return array<string, mixed> */
    private function half(PaymentGatewayEvent $event, string $part): array
    {
        $body = EventRows::body($event, $part);
        $headers = $event->getAttribute($part.'_headers');
        $shown = $body === null ? null : substr($body, 0, self::BODY_SHOWN);
        $text = $shown !== null && mb_check_encoding($shown, 'UTF-8');

        // A cut in the middle of a character is not text any more: cut at the last whole one.
        if ($shown !== null && ! $text && strlen($body) > self::BODY_SHOWN && mb_check_encoding($body, 'UTF-8')) {
            $shown = mb_strcut($body, 0, self::BODY_SHOWN, 'UTF-8');
            $text = true;
        }

        return [
            'headers' => is_array($headers) ? (object) $headers : (object) [],
            'encoding' => $shown === null ? null : ($text ? 'utf-8' : 'base64'),
            'body' => $shown === null ? null : ($text ? $shown : base64_encode($shown)),
            'size' => $event->getAttribute($part.'_size'),
            'sha256' => $event->getAttribute($part.'_sha256'),
            'truncated' => (bool) $event->getAttribute($part.'_truncated'),
            'cut' => $body !== null && strlen($shown ?? '') < strlen($body),
        ];
    }

    /**
     * Lines of events with the names of their gateway, method and attempt, as the panel has them.
     *
     * @param  list<PaymentGatewayEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function lines(array $events, ?Authenticatable $user): array
    {
        return $this->names(array_map(static fn (PaymentGatewayEvent $event): array => EventRows::line($event), $events), $user);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function names(array $lines, ?Authenticatable $user): array
    {
        return $this->container->make(EventNames::class)->apply($lines, $user);
    }

    // Support

    /**
     * Run a change and turn every refusal of the doors it goes through into words for the agent.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function attempt(Closure $work): mixed
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }

    /**
     * The real write, rolled back: a dry run goes through every check the write has.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        $db = (new PaymentMethod)->getConnection();
        $db->beginTransaction();

        try {
            return $work();
        } finally {
            $db->rollBack();
        }
    }

    /** @param  array<string, mixed>  $arguments */
    private function dryRun(array $arguments): bool
    {
        return ($arguments[Tool::DRY_RUN] ?? false) === true;
    }

    /** An order by its number first, then by its id. */
    private function order(mixed $reference): Order
    {
        if (is_string($reference)) {
            $reference = trim($reference);
        }

        if ((is_string($reference) && $reference !== '') || is_int($reference)) {
            $found = Order::query()->where('number', (string) $reference)->first()
                ?? (is_int($reference) || ctype_digit($reference) ? Order::query()->find((int) $reference) : null);

            if ($found instanceof Order) {
                return $found;
            }

            throw new ToolFailure("There is no order {$reference}. orders_list finds one by its number, the buyer's phone, e-mail or name.");
        }

        throw new ToolFailure('`order` is the order\'s number or its id.');
    }

    /** An attempt by its uuid, or by its id. */
    private function payment(mixed $reference): Payment
    {
        $found = match (true) {
            is_int($reference) => Payment::query()->find($reference),
            is_string($reference) && ctype_digit($reference) => Payment::query()->find((int) $reference),
            is_string($reference) && $reference !== '' => Payment::query()->where('uuid', trim($reference))->first(),
            default => throw new ToolFailure('`payment` is the attempt\'s uuid or its id.'),
        };

        return $found instanceof Payment
            ? $found
            : throw new ToolFailure("There is no payment {$this->scalar($reference)}. payments_list finds them.");
    }

    /** The latest attempt of an order in progress or held — what «Check with the gateway» asks about. */
    private function unfinished(Order $order): Payment
    {
        return $this->container->make(OrderPaymentCard::class)->unfinished($order)
            ?? throw new ToolFailure("Order {$order->number} has no payment in progress or held: there is nothing to ask the gateway.");
    }

    /**
     * A translated field as the form takes it: a map of languages. A plain string is the default
     * language — an agent's request has none it chose.
     *
     * @return array<string, string>
     */
    private function texts(mixed $value, string $field): array
    {
        $locales = $this->container->make(Locales::class);

        if (is_string($value)) {
            return [$locales->defaultCode() => trim($value)];
        }

        if (! is_array($value)) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (! $locales->has((string) $locale)) {
                throw new ToolFailure("`{$field}` has a value in [{$locale}], which this site is not published in. It has: ".implode(', ', $locales->codes()).'.');
            }

            $texts[(string) $locale] = is_string($text) ? trim($text) : '';
        }

        return Words::clean($texts);
    }

    /** @return array<string, int|string> */
    private function amount(string $key, int $minor, string $currency): array
    {
        return [$key => $minor, $key.'_formatted' => $this->money()->format($minor, $currency)];
    }

    private function currency(): string
    {
        $source = $this->container->bound(CurrencySource::class) ? $this->container->make(CurrencySource::class)->currency() : null;

        return (string) ($source ?? $this->container->make('config')->get('webx-cart.currency', ''));
    }

    private function money(): Money
    {
        return $this->container->make(Money::class);
    }

    private function locale(): string
    {
        return $this->container->make(Locales::class)->content();
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
