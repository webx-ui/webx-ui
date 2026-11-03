<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Testing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Schema;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryShipment;

/**
 * «Carrier A» on a development site (§18): the fake carrier with its three types — branch, locker,
 * courier — so that a site without a single real satellite can walk the guide of a carrier
 * through the panel: run the sync, watch the carrier's towns bind to the towns made by hand, pick
 * a branch or a street in the checkout, make a waybill.
 *
 * Off unless `webx-delivery.fake_carrier` is on (`WEBX_DELIVERY_FAKE_CARRIER=true`): a site in
 * production has no business with a carrier that delivers nothing. The directories are small
 * and fixed — twelve towns, thirty-six points, a hundred and twenty streets — and every waybill
 * is numbered after the carrier A parcels the site already has, since a site, unlike a test,
 * keeps them between requests.
 */
final class FakeCarrierSite
{
    /** The sizes of the directories on a site: enough for every case of the checkout, quick to sync. */
    public const int LOCALITIES = 12;

    public const int POINTS = 36;

    public const int STREETS = 120;

    /**
     * Carrier A and its types join the registries when they are first resolved, if the site has
     * the switch on by then — read at that moment and not at `register()`, so that a config set
     * later in the boot (a test's environment) still counts.
     */
    public static function register(Container $app): void
    {
        $on = static fn (): bool => (bool) $app->make('config')->get('webx-delivery.fake_carrier', false);

        $app->afterResolving(Carriers::class, static function (Carriers $carriers) use ($app, $on): void {
            if (! $on()) {
                return;
            }

            if (! $app->bound(FakeParcels::class)) {
                $app->singleton(FakeParcels::class, static fn (): FakeParcels => (new FakeParcels)->from(
                    Schema::hasTable('delivery_shipments')
                        ? DeliveryShipment::query()->where('carrier', FakeCarrier::KEY)->count()
                        : 0,
                ));
            }

            $carriers->register(new FakeCarrier(localities: self::LOCALITIES, points: self::POINTS, streets: self::STREETS));
        });

        $app->afterResolving(MethodTypes::class, static function (MethodTypes $types) use ($on): void {
            if ($on()) {
                $types->register(CarrierABranch::class);
                $types->register(CarrierALocker::class);
                $types->register(CarrierACourier::class);
            }
        });
    }
}
