<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Mcp;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\Panel\CarrierRows;
use WebxUi\Delivery\Panel\DeliverySection;
use WebxUi\Delivery\Settings\SettingOption;
use WebxUi\Delivery\Settings\SettingOptions;
use WebxUi\Delivery\Settings\SettingsRejected;
use WebxUi\Delivery\Shipping\OrderDeliveryChange;
use WebxUi\Delivery\Shipping\Shipments;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Shipping\WaybillRefused;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\ManualSync;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\SyncAlreadyRunning;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Orders\Panel\OrdersModule;

/**
 * The carriers, their sync and the parcels for an agent (§15). The state of a carrier is
 * {@see CarrierRows}, the screen's; «Run» is {@see ManualSync}, the button's; a waybill is
 * {@see Shipments}, the order tab's — and a parcel is there only where `module-orders` is.
 *
 * **A carrier's keys never pass through here** (§13.2): an answer says of each key whether it is
 * set, and no tool takes one — they are typed in the panel.
 */
final class CarrierTools
{
    use ToolSupport;

    public function __construct(private readonly Container $container) {}

    /** @return list<Tool> */
    public function all(): array
    {
        $carrier = ['type' => 'string', 'description' => 'A carrier\'s key, as delivery_carriers_list gives it.'];
        $directory = ['type' => 'string', 'enum' => array_map(static fn (Directory $one): string => $one->value, Directory::cases())];

        $tools = [
            Tool::read(
                'carriers_list',
                'The carriers of the site: key, title, on or off, countries, the method types they bring with what each '
                .'can do (price, waybill, tracking), their settings (sender and defaults — nothing secret) and their '
                .'keys as set or not. A field with `options: carrier` takes a value from the carrier\'s account — '
                .'delivery_carriers_options lists them; `_labels` holds the label of each chosen value, `missing` '
                .'the chosen values the account no longer has (from the options last asked for). A key is never shown '
                .'and never taken here: it is typed in the panel.',
                fn (): array => $this->carriers(),
            ),

            Tool::mutating(
                'carriers_update',
                'Switch a carrier on or off, or change its settings by name (the fields delivery_carriers_list gives). '
                .'A field with options from the carrier\'s account takes only a value its list has '
                .'(delivery_carriers_options), checked with the carrier as the panel checks it — a dry run checks too. '
                .'Keys are not set here. A carrier switched off offers no method and runs no sync.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->updateCarrier($arguments)),
                ['properties' => [
                    'carrier' => $carrier,
                    'enabled' => ['type' => 'boolean'],
                    'settings' => ['type' => 'object', 'description' => 'Setting → value.'],
                ], 'required' => ['carrier']],
            ),

            Tool::read(
                'carriers_options',
                'The options of a carrier\'s settings field from its own account — senders, contact persons, points: '
                .'value (what delivery_carriers_update takes), label and hint, at most 50. `values` — the values of the '
                .'fields it depends on (`depends` in delivery_carriers_list); `q` — what to search for, in a field '
                .'with `search`; `fresh` — past the ten-minute cache. Asked with the stored key, which is never shown. '
                .'A carrier without options from its account has no field here.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->options($arguments)),
                ['properties' => [
                    'carrier' => $carrier,
                    'field' => ['type' => 'string', 'description' => 'A settings field whose `options` is `carrier`.'],
                    'values' => ['type' => 'object', 'description' => 'Field → value of what this field depends on.'],
                    'q' => ['type' => 'string'],
                    'fresh' => ['type' => 'boolean'],
                ], 'required' => ['carrier', 'field']],
                // The options hold the account's contacts and phones: whoever keeps the settings (§5.5).
                permission: DeliverySection::MANAGE,
            ),

            Tool::read(
                'sync_status',
                'The directories of each carrier (localities, points, streets): the last success, a run going on, a pass '
                .'held at the threshold (how many it would remove), stale or not, the cron, how many records, the '
                .'counters and remarks of the last run, and how many localities wait in the binding queue.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->status($arguments)),
                ['properties' => ['carrier' => $carrier]],
            ),

            Tool::mutating(
                'sync_run',
                'Run a carrier\'s sync now, through the queue: from the localities it passes every directory in order '
                .'(points and streets stand on the localities); from points or streets — that one alone. No `directory` '
                .'— the carrier\'s first. A dry run says what would run, and whether a run is going on already.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->run($arguments)),
                ['properties' => ['carrier' => $carrier, 'directory' => $directory], 'required' => ['carrier']],
            ),

            Tool::mutating(
                'sync_confirm',
                'Confirm the removal a held pass stopped at (§7.5): the latest run of the directory removes what the '
                .'carrier no longer has. A dry run lists what would go — the first 50 and how many.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->confirm($arguments)),
                ['properties' => ['carrier' => $carrier, 'directory' => $directory], 'required' => ['carrier', 'directory']],
            ),
        ];

        if ($this->orders()) {
            $tools = [...$tools, ...$this->shipmentTools()];
        }

        return Arguments::strictAll($tools, 'delivery_');
    }

    /** @return list<Tool> */
    private function shipmentTools(): array
    {
        $order = ['type' => 'integer', 'description' => 'The order\'s id.'];

        return [
            Tool::read(
                'shipments_list',
                'Parcels, the latest first, twenty-five a page: order, carrier, waybill number, status, who pays, cost. '
                .'Filters: an order, a carrier, a status, on the way longer than `in_transit_days` days, `returned` '
                .'(refused, coming back or back).',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->shipments($arguments)),
                ['properties' => [
                    'order' => $order,
                    'carrier' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => array_map(static fn (ShipmentStatus $one): string => $one->value, ShipmentStatus::cases())],
                    'in_transit_days' => ['type' => 'integer'],
                    'returned' => ['type' => 'boolean'],
                    'page' => ['type' => 'integer'],
                ]],
                permission: [DeliverySection::VIEW, DeliverySection::MANAGE, DeliverySection::SHIP],
            ),

            Tool::mutating(
                'shipments_create',
                'Make the waybill of an order at its carrier — one at a time; a second after the first is cancelled. '
                .'Who pays is what the order\'s delivery says (§9.3). A dry run answers the request the carrier would '
                .'get, with `form` — the carrier\'s own fields of the waybill and whether it takes cash on delivery — and asks '
                .'the carrier nothing.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->createShipment($arguments)),
                ['properties' => [
                    'order' => $order,
                    'recipient_first_name' => ['type' => 'string'],
                    'recipient_last_name' => ['type' => 'string'],
                    'recipient_phone' => ['type' => 'string'],
                    'declared_value' => ['type' => 'integer', 'description' => 'Minor units; the order\'s total by default.'],
                    'cod' => ['type' => 'integer', 'description' => 'Cash on delivery, minor units; refused where the type takes none (`form.cod` of a dry run).'],
                    'extra' => ['type' => 'object', 'description' => 'The carrier\'s own fields of the waybill by name (`form.fields` of a dry run): weight, seats, sizes, description.'],
                ], 'required' => ['order']],
                permission: DeliverySection::SHIP,
            ),

            Tool::mutating(
                'shipments_track',
                'Ask the carriers where the parcels of an order (`order`) or a list of parcels (`shipments`) are now, '
                .'whatever their next time. A final parcel is not asked again. A dry run says which would be asked.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->track($arguments)),
                ['properties' => [
                    'order' => $order,
                    'shipments' => ['type' => 'array', 'items' => ['type' => 'integer']],
                ]],
                permission: DeliverySection::SHIP,
            ),
            Tool::mutating(
                'order_change',
                'Put the delivery of an order right — the buyer mistyped the street, chose the wrong pick-up point, '
                .'or one the carrier will not take the parcel to: the town (`locality`, an id), the method (an id or a '
                .'key), and the `point` or the `address` the method asks for, checked as the checkout checks them. '
                .'Writes a new delivery block, its price in place of the old one, and a history line. The total and '
                .'the balance are counted again when the price differs. Refused while a waybill stands (cancel it '
                .'first). A dry run answers the new block and «was → will be» of the sum, and writes nothing.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->changeOrder($arguments)),
                ['properties' => [
                    'order' => $order,
                    'locality' => ['type' => 'integer', 'description' => 'The town\'s id: delivery_localities_search.'],
                    'method' => ['type' => ['integer', 'string'], 'description' => 'The method\'s id or key.'],
                    'point' => ['type' => 'integer', 'description' => 'The point\'s id, for a method that asks for a point: delivery_points_search.'],
                    'address' => ['type' => 'object', 'description' => 'For a courier: street (or street_id of a carrier\'s directory), house, apartment, postcode, note.', 'properties' => [
                        'street' => ['type' => 'string'],
                        'street_id' => ['type' => 'string'],
                        'house' => ['type' => 'string'],
                        'apartment' => ['type' => 'string'],
                        'postcode' => ['type' => 'string'],
                        'note' => ['type' => 'string'],
                    ]],
                    'reason' => ['type' => 'string', 'description' => 'Why — for the managers, never shown to the buyer.'],
                ], 'required' => ['order', 'locality', 'method']],
                permission: OrdersModule::MANAGE,
            ),
        ];
    }

    // Carriers

    /** @return array<string, mixed> */
    private function carriers(): array
    {
        $locale = $this->locale();
        $rows = DeliveryCarrier::query()->get()->keyBy('key');
        $types = $this->container->make(MethodTypes::class);
        $list = [];

        foreach ($this->container->make(Carriers::class)->all() as $carrier) {
            if ($carrier->key() === Carriers::STORE) {
                continue;
            }

            $row = $rows->get($carrier->key());
            $list[] = [
                'key' => $carrier->key(),
                'title' => $carrier->title($locale),
                'enabled' => (bool) ($row->enabled ?? false),
                'countries' => $carrier->countries(),
                'types' => array_map(static fn ($type): array => [
                    'key' => $type->key(),
                    'asks' => $type->asks()->value,
                    'point_kinds' => $type->pointKinds(),
                    'waybills' => $type instanceof CreatesWaybills,
                    'tracking' => $type instanceof TracksShipments,
                ], $types->ofCarrier($carrier->key())),
                'settings' => [
                    'fields' => $carrier->settings(),
                    'values' => $row->settings ?? [],
                    'missing' => (object) $this->container->make(CarrierRows::class)->missing($carrier),
                ],
                'credentials' => self::credentials($carrier, $row),
            ];
        }

        return ['carriers' => $list];
    }

    /**
     * Each key of the carrier as `api_key: set` or `api_key: not set` — the value never.
     *
     * @return list<string>
     */
    public static function credentials(Carrier $carrier, ?DeliveryCarrier $row): array
    {
        $stored = $row->credentials ?? [];

        return array_values(array_map(static function (array $field) use ($stored): string {
            $name = (string) ($field['name'] ?? '');
            $set = $name !== '' && is_string($stored[$name] ?? null) && $stored[$name] !== '';

            return $name.': '.($set ? 'set' : 'not set');
        }, $carrier->credentials()));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateCarrier(array $arguments): array
    {
        $carrier = $this->carrier($arguments['carrier'] ?? null);
        $settings = $arguments['settings'] ?? null;

        if ($settings !== null) {
            if (! is_array($settings)) {
                throw new ToolFailure('`settings` is an object of setting → value.');
            }

            $names = array_values(array_filter(array_column($carrier->settings(), 'name'), 'is_string'));
            Arguments::refuseUnknown($settings, $names, 'the settings of '.$carrier->key(), 'setting');
        }

        $work = function () use ($carrier, $arguments, $settings): array {
            $row = DeliveryCarrier::query()->firstOrNew(['key' => $carrier->key()]);

            if (array_key_exists('enabled', $arguments)) {
                $row->enabled = (bool) $arguments['enabled'];
            }

            if (is_array($settings)) {
                $before = $row->settings ?? [];

                try {
                    // As the panel: a value from the carrier's account only if the account has it.
                    $row->settings = $this->container->make(SettingOptions::class)
                        ->check($carrier, $before, [...$before, ...$settings], $row->credentials ?? []);
                } catch (SettingsRejected $rejected) {
                    throw new ToolFailure($rejected->getMessage());
                }
            }

            $row->save();

            return [
                'key' => $carrier->key(),
                'enabled' => (bool) $row->enabled,
                'settings' => $row->settings ?? [],
                'credentials' => self::credentials($carrier, $row),
            ];
        };

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'carrier' => $this->rehearse($work)];
        }

        return ['updated' => true, 'carrier' => $work()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function options(array $arguments): array
    {
        $carrier = $this->carrier($arguments['carrier'] ?? null);
        $field = $arguments['field'] ?? null;
        $values = $arguments['values'] ?? [];

        if (! is_string($field) || $field === '') {
            throw new ToolFailure('`field` is the name of a settings field.');
        }

        if (! is_array($values)) {
            throw new ToolFailure('`values` is an object of field → value.');
        }

        try {
            $options = $this->container->make(SettingOptions::class)->options(
                $carrier,
                $field,
                $values,
                DeliveryCarrier::query()->find($carrier->key())->credentials ?? [],
                is_string($arguments['q'] ?? null) ? $arguments['q'] : null,
                ($arguments['fresh'] ?? false) === true,
            );
        } catch (CarrierUnavailable $failure) {
            throw new ToolFailure(SettingOptions::unavailable($failure));
        }

        return [
            'carrier' => $carrier->key(),
            'field' => $field,
            'options' => array_map(static fn (SettingOption $option): array => $option->toArray(), $options),
        ];
    }

    // Sync

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function status(array $arguments): array
    {
        $rows = $this->container->make(CarrierRows::class);
        $locale = $this->locale();

        if (isset($arguments['carrier'])) {
            return ['carriers' => [$rows->summary($this->carrier($arguments['carrier']), $locale)]];
        }

        $list = [];

        foreach ($this->container->make(Carriers::class)->all() as $carrier) {
            if ($carrier->key() !== Carriers::STORE) {
                $list[] = $rows->summary($carrier, $locale);
            }
        }

        return ['carriers' => $list];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function run(array $arguments): array
    {
        $carrier = $this->carrier($arguments['carrier'] ?? null)->key();
        $directory = $this->directory($arguments['directory'] ?? null);
        $manual = $this->container->make(ManualSync::class);

        try {
            $plan = $manual->plan($carrier, $directory);
        } catch (SyncAlreadyRunning $running) {
            if ($this->dryRun($arguments)) {
                return ['dry_run' => true, 'would_run' => [], 'running' => CarrierRows::run($running->run)];
            }

            throw new ToolFailure($running->getMessage().' delivery_sync_status says how far it is.');
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_run' => $plan, 'running' => null];
        }

        $manual->start($carrier, $plan, Trigger::Mcp);

        return ['queued' => $plan, 'note' => 'The run goes through the queue; delivery_sync_status shows it.'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function confirm(array $arguments): array
    {
        $carrier = $this->carrier($arguments['carrier'] ?? null)->key();
        $directory = $this->directory($arguments['directory'] ?? null) ?? throw new ToolFailure('`directory` is localities, points or streets.');
        $sync = $this->container->make(Synchronizer::class);
        $run = $this->container->make(Directories::class)->latest($carrier, $directory);

        if ($run === null || $run->status !== RunStatus::Held) {
            throw new ToolFailure("The latest {$directory->value} run of {$carrier} is not held: there is nothing to confirm.");
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'run' => $run->id, 'would_remove' => $sync->vanishing($run)];
        }

        return ['confirmed' => true, 'run' => CarrierRows::run($sync->confirm($run)->refresh())];
    }

    private function carrier(mixed $key): Carrier
    {
        $carriers = $this->container->make(Carriers::class);

        if (! is_string($key) || $key === Carriers::STORE || ! $carriers->has($key)) {
            throw new ToolFailure(sprintf(
                'Carrier %s does not exist. The site has: %s.',
                is_string($key) ? $key : '?',
                implode(', ', array_diff($carriers->keys(), [Carriers::STORE])) ?: 'none but the shop itself',
            ));
        }

        return $carriers->get($key);
    }

    private function directory(mixed $value): ?Directory
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Directory::tryFrom((string) $value) ?? throw new ToolFailure('`directory` is localities, points or streets.');
    }

    // Shipments

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function shipments(array $arguments): array
    {
        $locale = $this->locale();
        $query = DeliveryShipment::query();

        if (isset($arguments['order'])) {
            $query->where('order_id', $this->int($arguments['order'], 'order', 1));
        }

        if (is_string($arguments['carrier'] ?? null) && $arguments['carrier'] !== '') {
            $query->where('carrier', $arguments['carrier']);
        }

        if (is_string($arguments['status'] ?? null) && $arguments['status'] !== '') {
            $query->where('status', (ShipmentStatus::tryFrom($arguments['status']) ?? throw new ToolFailure('Unknown status.'))->value);
        }

        if (isset($arguments['in_transit_days'])) {
            $query->where('final', false)
                ->where('status', '!=', ShipmentStatus::Cancelled->value)
                ->where('created_at', '<', Carbon::now()->subDays($this->int($arguments['in_transit_days'], 'in_transit_days')));
        }

        if (($arguments['returned'] ?? false) === true) {
            $query->whereIn('status', [ShipmentStatus::Refused->value, ShipmentStatus::Returning->value, ShipmentStatus::Returned->value]);
        }

        $page = $query->orderByDesc('id')->paginate(25, ['*'], 'page', $this->int($arguments['page'] ?? 1, 'page', 1));

        return [
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'total' => $page->total(),
            'shipments' => $page->getCollection()->map(static fn (DeliveryShipment $shipment): array => [
                'id' => $shipment->id,
                'order_id' => $shipment->order_id,
                'carrier' => $shipment->carrier,
                'method' => $shipment->method_key,
                'number' => $shipment->number,
                'status' => $shipment->status->value,
                'status_label' => $shipment->status->label($locale),
                'status_at' => $shipment->status_at?->toIso8601String(),
                'final' => $shipment->final,
                'payer' => $shipment->payer,
                'cost' => $shipment->cost,
                'created_at' => $shipment->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createShipment(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $changes = [];

        foreach (['recipient_first_name', 'recipient_last_name', 'recipient_phone'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $changes[$field] = is_string($arguments[$field]) ? mb_substr(trim($arguments[$field]), 0, 190) : null;
            }
        }

        foreach (['declared_value', 'cod'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $changes[$field] = $this->int($arguments[$field], $field, 0, 2000000000);
            }
        }

        if (array_key_exists('extra', $arguments)) {
            $changes['extra'] = is_array($arguments['extra']) ? $arguments['extra'] : throw new ToolFailure('`extra` is an object of field → value.');
        }

        $shipments = $this->container->make(Shipments::class);

        try {
            if ($this->dryRun($arguments)) {
                return ['dry_run' => true, 'request' => $shipments->request($order, $changes)->toArray(), 'form' => $shipments->describe($order)];
            }

            $shipment = $shipments->create($order, $changes, 'mcp');
        } catch (WaybillRefused $refused) {
            throw new ToolFailure($refused->getMessage());
        }

        return ['created' => true, 'shipment' => [
            'id' => $shipment->id,
            'number' => $shipment->number,
            'carrier' => $shipment->carrier,
            'status' => $shipment->status->value,
            'payer' => $shipment->payer,
            'cost' => $shipment->cost,
        ]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function track(array $arguments): array
    {
        $query = DeliveryShipment::query();

        if (isset($arguments['order'])) {
            $query->where('order_id', $this->order($arguments['order'])->id);
        } elseif (is_array($arguments['shipments'] ?? null) && $arguments['shipments'] !== []) {
            $query->whereKey(array_map(fn (mixed $id): int => $this->int($id, 'shipments', 1), $arguments['shipments']));
        } else {
            throw new ToolFailure('Name an `order` or a list of `shipments`.');
        }

        $list = $query->orderBy('id')->limit(Shipments::BATCH)->get();
        $asked = $list->filter(static fn (DeliveryShipment $shipment): bool => ! $shipment->final)->modelKeys();

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_ask' => array_values($asked), 'final' => array_values(array_diff($list->modelKeys(), $asked))];
        }

        try {
            $changed = $this->container->make(Shipments::class)->track($list);
        } catch (DeliveryException $failed) {
            throw new ToolFailure($failed->getMessage());
        }

        return [
            'asked' => array_values($asked),
            'changed' => $changed,
            'statuses' => DeliveryShipment::query()->whereKey($list->modelKeys())->orderBy('id')->get()
                ->map(static fn (DeliveryShipment $shipment): array => ['id' => $shipment->id, 'number' => $shipment->number, 'status' => $shipment->status->value])
                ->values()->all(),
        ];
    }

    /** The parcels are the orders': without module-orders there is no table of them, and no tool. */
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function changeOrder(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $input = array_intersect_key($arguments, array_flip(['locality', 'method', 'point', 'address']));
        $reason = is_string($arguments['reason'] ?? null) ? $arguments['reason'] : null;
        $dryRun = $this->dryRun($arguments);
        $done = $this->container->make(OrderDeliveryChange::class)->change($order, $input, 'mcp', $reason, $dryRun);
        $result = $done['result'];

        return [
            ...($dryRun ? ['dry_run' => true] : ['changed' => true]),
            'delivery' => $done['snapshot'],
            'summary_from' => $result->summaryFrom,
            'summary_to' => $result->summaryTo,
            'delivery_amount_from' => $result->amountFrom,
            'delivery_amount_to' => $result->amountTo,
            'total_from' => $result->before['total'],
            'total_to' => $result->after['total'],
            'total_difference' => $result->after['total'] - $result->before['total'],
            'balance' => $result->toArray()['balance'],
            'currency' => $order->currency,
        ];
    }

    private function orders(): bool
    {
        return class_exists(OrdersServiceProvider::class)
            && $this->container instanceof Application
            && $this->container->providerIsLoaded(OrdersServiceProvider::class);
    }

    private function order(mixed $id): Order
    {
        return Order::query()->find($this->int($id, 'order', 1))
            ?? throw new ToolFailure("Order {$id} does not exist.");
    }
}
