<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Payments\Exchange\InProcessHosts;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;

/**
 * «Gateway A» on a development site and in tests (spec §21): the fake gateway with its three
 * types, its API served inside the process at `gateway-a.test`, and its page — GET /pay/fake/{uuid}
 * with the buttons of §21.2.
 *
 * Off unless `webx-payments.fake_gateway` is on (`WEBX_PAYMENTS_FAKE_GATEWAY=true`), and never in
 * `production`: a live site has no business with a gateway that moves no money. The switch is read
 * when a registry is first resolved, not at `register()`, so that a config set later in the boot
 * (a test's environment) still counts.
 */
final class FakeGatewaySite
{
    public const string PAGE = 'webx.payments.fake.page';

    public const string ACTION = 'webx.payments.fake.action';

    /** The methods a site gets for the three types, by type. */
    public const array METHODS = [
        'gateway-a.card' => 'gateway-a-card',
        'gateway-a.form' => 'gateway-a-form',
        'gateway-a.unsigned' => 'gateway-a-unsigned',
    ];

    public static function register(Container $app): void
    {
        $app->singleton(FakeGatewayServer::class);
        $app->singleton(FakeGatewayNotifier::class);

        $app->afterResolving(Gateways::class, static function (Gateways $gateways) use ($app): void {
            if (self::on($app)) {
                $gateways->register(FakeGateway::class);
            }
        });

        $app->afterResolving(MethodTypes::class, static function (MethodTypes $types) use ($app): void {
            if (self::on($app)) {
                $types->register(GatewayACard::class);
                $types->register(GatewayAForm::class);
                $types->register(GatewayAUnsigned::class);
            }
        });

        $app->afterResolving(InProcessHosts::class, static function (InProcessHosts $hosts) use ($app): void {
            if (self::on($app)) {
                $hosts->serve(FakeGatewayServer::HOST, $app->make(FakeGatewayServer::class));
            }
        });
    }

    public static function on(Container $app): bool
    {
        if ($app instanceof Application && $app->environment('production')) {
            return false;
        }

        return (bool) $app->make('config')->get('webx-payments.fake_gateway', false);
    }

    /**
     * The three methods of gateway A with its test key (§21.1) — switched on or off. A row the
     * shop already has keeps its own settings; nothing here runs in production.
     *
     * @return list<PaymentMethod>
     */
    public static function methods(bool $enabled = true): array
    {
        $made = [];
        $position = 10;

        foreach (self::METHODS as $type => $key) {
            $method = PaymentMethod::query()->firstOrNew(['key' => $key]);
            $method->forceFill([
                'type' => $type,
                'title' => $method->exists ? $method->title : ['en' => match ($type) {
                    'gateway-a.card' => 'Card (gateway A)',
                    'gateway-a.form' => 'Gateway A page',
                    default => 'Gateway A (unsigned)',
                }],
                'enabled' => $enabled,
                'position' => $method->exists ? $method->position : $position++,
                'credentials' => ['secret_key' => FakeGateway::SECRET],
            ])->save();
            $made[] = $method;
        }

        return $made;
    }
}
