<?php

declare(strict_types=1);

namespace WebxUi\Payments\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Payments\Contracts\CancelsPayments;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Contracts\CompletesApproval;
use WebxUi\Payments\DeliveryRule;
use WebxUi\Payments\Exchange\Direction;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Panel\MethodForm;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\SettleResult;

/**
 * What an agent reads before it touches the payments (§16): the types the registry has and what
 * each can do, the rules of delivery, the statuses of an attempt with where each may go (§8.1), the
 * problems, the words of the exchange journal, and the address each online method takes the
 * gateway's notifications at. From the registries and the enums — nothing typed by hand here, so
 * the catalogue says what the site has.
 */
final readonly class PaymentsResources
{
    /** Where an attempt may go from each status (§8.1): `paid` is stronger than anything unfinished. */
    public const array TRANSITIONS = [
        'created' => ['pending', 'failed', 'cancelled'],
        'pending' => ['authorized', 'paid', 'failed', 'cancelled', 'expired'],
        'authorized' => ['paid', 'cancelled', 'expired', 'failed'],
        'paid' => [],
        'failed' => ['paid'],
        'cancelled' => ['paid'],
        'expired' => ['paid'],
    ];

    /** The rules of delivery (§7.3), for the agent. */
    public const array RULES = [
        DeliveryRule::ANY => 'Offered with every delivery.',
        DeliveryRule::ON_HAND => 'Offered when the carrier collects cash on handover, or the buyer collects at the shop\'s own point; without a delivery at all — as agreed.',
        DeliveryRule::METHODS => 'Offered only with the delivery methods named in `methods`.',
        DeliveryRule::EXCEPT => 'Offered with every delivery method except those named in `methods`.',
    ];

    public function __construct(private Container $container) {}

    /** @return list<McpResource> */
    public function all(): array
    {
        return [
            new McpResource(
                'payments://catalog',
                'Payments catalog',
                'The types of payment methods (key, gateway, kind, whether the order awaits payment, whether the gateway '
                .'can cancel, hold and capture, complete an approval), the rules of delivery, the statuses of an attempt '
                .'and where each may go, the kinds of problems, the words of the exchange journal (directions, kinds, '
                .'decisions), and the address each online method takes the gateway\'s notifications at. Read it before '
                .'creating a method or reading the journal.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function catalog(): array
    {
        $types = $this->container->make(MethodTypes::class);
        $gateways = $this->container->make(Gateways::class);
        $links = $this->container->make(PaymentLinks::class);
        $locale = $this->container->make(Locales::class)->content();
        $titles = array_column($this->container->make(MethodForm::class)->types(), 'title', 'key');
        $rows = [];

        foreach ($types->all() as $type) {
            $gateway = $type instanceof ChargesOnline && $gateways->has($type->provider()) ? $gateways->get($type->provider()) : null;
            $rows[] = [
                'key' => $type->key(),
                'title' => $titles[$type->key()] ?? $type->key(),
                'gateway' => $gateway?->key(),
                'gateway_title' => $gateway?->title($locale),
                'kind' => $type->kind()->value,
                'awaits_payment' => $type->awaitsPayment(),
                'currencies' => $type->currencies(),
                'cancels' => $type instanceof CancelsPayments,
                'holds' => $type instanceof CapturesPayments,
                'captures_partially' => $type instanceof CapturesPayments && $type->capturesPartially(),
                'completes_approval' => $type instanceof CompletesApproval,
                // A gateway whose notifications are not believed: the base asks its API (§9.2).
                'trusts_notifications' => $type instanceof ChargesOnline ? $type->trustsNotifications() : null,
            ];
        }

        $notify = [];

        foreach (PaymentMethod::query()->orderBy('position')->get(['key', 'type']) as $method) {
            if ($types->has($method->type) && $types->get($method->type) instanceof ChargesOnline) {
                $notify[] = ['method' => $method->key, 'url' => $links->notifyUrl($method->key)];
            }
        }

        $words = static fn (string $group, array $keys): array => array_map(
            static fn (string $key): array => ['key' => $key, 'label' => (string) __("webx-payments::{$group}.{$key}")],
            $keys,
        );

        return [
            'types' => $rows,
            'delivery_rules' => array_map(static fn (string $mode, string $does): array => ['mode' => $mode, 'does' => $does], array_keys(self::RULES), self::RULES),
            'statuses' => array_map(static fn (PaymentStatus $status): array => [
                'status' => $status->value,
                'label' => (string) __('webx-payments::statuses.statuses.'.$status->value),
                'goes_to' => self::TRANSITIONS[$status->value],
            ], PaymentStatus::cases()),
            'problems' => $words('statuses.problems', [
                SettleResult::DUPLICATE, SettleResult::AMOUNT_MISMATCH, SettleResult::ORDER_CANCELLED,
                SettleResult::HOLD_EXPIRING, SettleResult::CAPTURE_FAILED,
            ]),
            'exchange' => [
                'directions' => array_map(static fn (Direction $one): string => $one->value, Direction::cases()),
                'kinds' => array_map(static fn (ExchangeKind $one): string => $one->value, ExchangeKind::cases()),
                'outcomes' => array_map(static fn (Outcome $one): string => $one->value, Outcome::cases()),
                'read_only' => 'Nothing changes or removes an event: no tool, no route. Bodies are behind payments.exchange.',
            ],
            'notify_urls' => $notify,
        ];
    }
}
