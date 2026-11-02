<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Delivery\Benefits;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\Countries;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Panel\MethodForm;
use WebxUi\Delivery\PointKinds;
use WebxUi\Delivery\Sync\Directories;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Geography\LocalityKind;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it touches the delivery (§15): the types of methods the registry
 * has and what each can do, the kinds of points, the carriers with their directories, the
 * countries the site delivers to, the privilege `shipping.paid_by_store`, and the rules of the
 * removal with the threshold. All from the registries and the settings — nothing typed by hand
 * here, so the catalogue says what the site has, not what it had when this was written.
 */
final class DeliveryResources
{
    public function __construct(private readonly Container $container) {}

    /** @return list<McpResource> */
    public function all(): array
    {
        return [
            new McpResource(
                'delivery://catalog',
                'Delivery catalog',
                'The types of delivery methods (key, carrier, what the buyer gives — a point or an address —, kinds of '
                .'points, whether they price, make waybills, track), the kinds of points, the carriers and their '
                .'directories, the countries the site delivers to, the privilege shipping.paid_by_store and the rules '
                .'of the sync\'s removal. Read it before creating a method or running a sync.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function catalog(): array
    {
        $types = $this->container->make(MethodTypes::class);
        $carriers = $this->container->make(Carriers::class);
        $directories = $this->container->make(Directories::class);
        $form = $this->container->make(MethodForm::class);
        $config = $this->container->make(Config::class);
        $locale = $this->container->make(Locales::class)->content();
        $titles = array_column($form->types(), 'title', 'key');

        $carrierRows = [];

        foreach ($carriers->all() as $carrier) {
            $carrierRows[] = [
                'key' => $carrier->key(),
                'title' => $carrier->title($locale),
                'enabled' => $carrier->key() === Carriers::STORE || $directories->enabled($carrier->key()),
                'countries' => $carrier->countries(),
                'directories' => $carrier instanceof SyncsDirectories
                    ? array_map(static fn ($schedule): array => [
                        'directory' => $schedule->directory->value,
                        'cron' => $directories->cron($carrier->key(), $schedule),
                    ], array_values($directories->schedules($carrier->key())))
                    : [],
            ];
        }

        $share = $config->get('webx-delivery.sync.max_removal_share', Synchronizer::DEFAULT_MAX_REMOVAL_SHARE);

        return [
            'types' => array_map(static fn ($type): array => [
                'key' => $type->key(),
                'title' => $titles[$type->key()] ?? $type->key(),
                'carrier' => $type->carrier(),
                'asks' => $type->asks()->value,
                'point_kinds' => $type->pointKinds(),
                'countries' => $type->countries(),
                // Every type prices — fixed, free, by the carrier's rates or not here (§9.1).
                'quotes' => true,
                'waybills' => $type instanceof CreatesWaybills,
                'tracking' => $type instanceof TracksShipments,
            ], $types->all()),
            'point_kinds' => $this->container->make(PointKinds::class)->all($locale),
            'locality_kinds' => LocalityKind::values(),
            'carriers' => $carrierRows,
            'countries' => $this->container->make(Countries::class)->active($locale),
            'privileges' => [[
                'key' => Benefits::PAID_BY_STORE,
                'does' => 'A cart with this privilege pays nothing for delivery: every method that honours it '
                    .'(honours_paid_by_store) is priced «Free» with its price crossed out, and the shop pays the '
                    .'carrier. A method that does not honour it keeps its price.',
            ]],
            'removal' => [
                'rule' => 'A complete pass of a directory removes what the carrier did not return. Removed, not '
                    .'deleted: carts and orders keep naming them. A pass that would remove more than the threshold '
                    .'of what is in force is held — nothing goes until delivery_sync_confirm. A pass cut short '
                    .'removes nothing.',
                'threshold_share' => is_numeric($share) ? (float) $share : Synchronizer::DEFAULT_MAX_REMOVAL_SHARE,
            ],
            'reasons' => DeliveryTools::REASONS,
        ];
    }
}
