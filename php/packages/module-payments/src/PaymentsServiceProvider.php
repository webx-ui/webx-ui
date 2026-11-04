<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Cache\CacheGroups;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Orders\Blocks\BlockDefinition;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Contracts\MarksPaidByHand;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Panel\SourceNames;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\Console\ExchangePruneCommand;
use WebxUi\Payments\Console\PollCommand;
use WebxUi\Payments\Console\SelectionsPruneCommand;
use WebxUi\Payments\Doctor\ExchangeSizeCheck;
use WebxUi\Payments\Doctor\OverriddenViewsCheck;
use WebxUi\Payments\Events\PaymentProblem;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeMasker;
use WebxUi\Payments\Exchange\InProcessHosts;
use WebxUi\Payments\Mcp\PaymentsModule;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Notifications\PaymentNotices;
use WebxUi\Payments\Online\Checks;
use WebxUi\Payments\Online\Closings;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Online\Handoffs;
use WebxUi\Payments\Online\Holds;
use WebxUi\Payments\Online\InternalEvents;
use WebxUi\Payments\Online\Notifications;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Online\PayPage;
use WebxUi\Payments\Online\Poll;
use WebxUi\Payments\Online\Returns;
use WebxUi\Payments\Online\Settlement;
use WebxUi\Payments\Online\StartRequests;
use WebxUi\Payments\Orders\PaymentsMarkPaid;
use WebxUi\Payments\Panel\AttemptRows;
use WebxUi\Payments\Panel\MethodForm;
use WebxUi\Payments\Panel\PaymentsHome;
use WebxUi\Payments\Panel\PaymentsSection;
use WebxUi\Payments\Rendering\Assets;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;
use WebxUi\Settings\Settings;

/**
 * Payments: the registry of method types with the shop's own two built in, the methods of the
 * site offered by the amount and the delivery, the checkout's step «payment», the order's block
 * `payment` with the bank details, a manager's «Mark as paid», and the journal of the exchange
 * with gateways (spec §5–§12, §20) with the words of `webx-payments`.
 *
 * The registry is bound in `register()`, so that a satellite adds its types from its own `boot()`
 * through `callAfterResolving`, whatever order the providers boot in.
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-payments.php', 'webx-payments');

        $this->app->singleton(ExchangeConnection::class);
        $this->app->singleton(ExchangeMasker::class);
        $this->app->singleton(ExchangeJournal::class);

        $this->app->singleton(MethodTypes::class);
        $this->app->singleton(Gateways::class);
        $this->app->singleton(InProcessHosts::class);
        $this->app->singleton(MethodCredentials::class);
        $this->app->singleton(GatewayCalls::class);
        $this->app->singleton(PaymentLinks::class);
        $this->app->singleton(StartRequests::class);
        $this->app->singleton(PayPage::class);
        // The card's «Mark as paid» is this module's mark: the attempt and the journal with the milestone.
        $this->app->bind(MarksPaidByHand::class, PaymentsMarkPaid::class);
        $this->app->singleton(Handoffs::class);
        $this->app->singleton(Settlement::class);
        $this->app->singleton(Checks::class);
        $this->app->singleton(InternalEvents::class);
        $this->app->singleton(Holds::class);
        $this->app->singleton(Closings::class);
        $this->app->singleton(Poll::class);
        $this->app->singleton(Notifications::class);
        $this->app->singleton(Returns::class);
        $this->app->singleton(Methods::class);
        $this->app->singleton(Payments::class);
        $this->app->singleton(PaymentsCheckoutSlot::class);
        // What one request read of a cart's choice is not the next one's.
        $this->app->scoped(Selections::class);
        // The flag «the script is on this page already» belongs to the response being built.
        $this->app->scoped(Assets::class);
        // The titles of the methods a list of attempts names are read once per request.
        $this->app->scoped(AttemptRows::class);
        $this->app->singleton(MethodForm::class);

        $this->app->afterResolving(MethodTypes::class, static function (MethodTypes $types): void {
            $types->register(OnReceipt::class);
            $types->register(BankTransfer::class);
        });

        // «Gateway A» (§21): only with WEBX_PAYMENTS_FAKE_GATEWAY and never in production.
        FakeGatewaySite::register($this->app);

        // The pay link and the bank details in the order's letters, and the manager's notice of a
        // problem (§11.5) — where `webx-ui/module-notifications` is installed.
        if (class_exists(NotificationEvents::class)) {
            $this->callAfterResolving(NotificationEvents::class, function (NotificationEvents $events): void {
                PaymentNotices::declare($events, $this->app);
            });
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-payments');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-payments');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/pay.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/fake.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/notify.php');

        RateLimiter::for('webx-payments-write', function (Request $request): Limit {
            $perMinute = $this->app->make('config')->get('webx-payments.throttle.write');

            return Limit::perMinute(is_numeric($perMinute) ? max(1, (int) $perMinute) : 30)->by((string) $request->ip());
        });

        // The enabled methods are read by every request of the payment step (§18).
        $groups = $this->app->make(CacheGroups::class);
        $groups->declare(Methods::GROUP, 'webx-payments::module.cache');
        $groups->bumpOn(Methods::GROUP, PaymentMethod::class);

        // The checkout's slot «payment» (§10; §22.3 of the orders spec).
        $this->callAfterResolving(CheckoutSlots::class, function (CheckoutSlots $slots): void {
            if ($slots->payment() === null) {
                $slots->register($this->app->make(PaymentsCheckoutSlot::class));
            }
        });

        // The order's block `payment` (§10.4): its line is the method's title, and under it, while
        // the order awaits payment, the bank details of a transfer.
        $this->callAfterResolving(OrderBlocks::class, static function (OrderBlocks $blocks): void {
            if (! $blocks->has('payment')) {
                $blocks->register(new BlockDefinition(
                    'payment',
                    'payments',
                    PaymentSnapshot::validate(...),
                    PaymentSnapshot::summary(...),
                    // Its own view is the tab «Payment»: «Items» does not repeat the block.
                    view: PaymentsSection::VIEW,
                    // Money held is paid for the buyer (§8.3): their pages do not say «waiting for payment».
                    held: static fn (array $block, Order $order): bool => $order->awaiting_payment
                        && Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Authorized->value)->exists(),
                    // What the buyer may do while the order waits for an online payment (§8.4).
                    prompt: static function (array $block, Order $order, string $locale): ?array {
                        $prompt = app(PayPage::class)->prompt($order);

                        return $prompt === null ? null : [
                            'title' => (string) trans('webx-payments::buyer.status.awaiting', [], $locale),
                            'html' => view('webx-payments::pay.prompt', ['prompt' => $prompt])->render(),
                        ];
                    },
                    // The same for «My orders»: its words, without the buttons.
                    awaiting: static fn (array $orders, string $locale): array => array_fill_keys(
                        app(PayPage::class)->promptable($orders),
                        (string) trans('webx-payments::buyer.status.awaiting', [], $locale),
                    ),
                    details: static function (array $block, Order $order, string $locale): ?string {
                        if (! $order->awaiting_payment || $order->paid_at !== null || $order->cancelled_at !== null) {
                            return null;
                        }

                        $lines = Instructions::of($block, (string) $order->number, $locale);

                        return $lines === null ? null : Blade::render('<x-webx-payments::instructions :lines="$lines" />', ['lines' => $lines]);
                    },
                ));
            }
        });

        if (class_exists(NotificationEvents::class)) {
            Event::listen(PaymentProblem::class, [PaymentNotices::class, 'problem']);
        }

        // The size of the journal and the templates a theme keeps from an older release in
        // `webx:doctor`, where the panel's frame is installed.
        if (class_exists(DoctorChecks::class)) {
            $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
                $checks->register(ExchangeSizeCheck::class);
                $checks->register(OverriddenViewsCheck::class);
            });
        }

        // The section of the panel and the order's tab (§13), where the panel's frame is installed.
        if (class_exists(ModuleRegistry::class)) {
            $this->registerPanel();
        }

        // The choices of carts the cart's own pruning took (§5).
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            // A lost notification, a closed tab, a hold about to end (§9.3).
            $schedule->command('webx:payments:poll')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('webx:payments:selections-prune')->dailyAt('03:40')->withoutOverlapping()->onOneServer();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ExchangePruneCommand::class, PollCommand::class, SelectionsPruneCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-payments.php' => config_path('webx-payments.php'),
        ], 'webx-payments-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-payments'),
        ], 'webx-payments-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-payments'),
        ], 'webx-payments-views');
    }

    /**
     * Three entries in the group «Payments», their routes, the described form of a method and the
     * tab «Payment» of the order's card.
     *
     * The menu group is added to the panel's config at boot rather than shipped as a default: a
     * site that published `webx-admin.php` has its own copy of the list.
     */
    private function registerPanel(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/panel.php');

        // The order's history names a line of `payment:<key>` by the method's title, not its key.
        $this->callAfterResolving(SourceNames::class, static function (SourceNames $names): void {
            $names->register('payment', static function (string $key): ?string {
                $method = PaymentMethod::query()->where('key', $key)->first(['title']);

                return $method !== null ? Words::pick($method->title, app()->getLocale()) : null;
            });
        });

        $config = $this->app->make('config');
        $groups = (array) $config->get('webx-admin.groups', []);

        if (! array_key_exists(PaymentsSection::GROUP, $groups)) {
            $config->set('webx-admin.groups', [
                ...$groups,
                PaymentsSection::GROUP => ['title' => 'webx-payments::panel.sections.group', 'icon' => 'sliders', 'order' => PaymentsSection::ORDER],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach (PaymentsSection::all() as $section) {
            // The first entry seeds the section's demo — the two built-in methods switched on — and
            // carries the agent's tools where the MCP server is installed (§16): payments does not
            // require it, and nothing loads the tools' classes otherwise.
            $modules->register(match (true) {
                $section->id() !== PaymentsSection::GROUP => $section,
                interface_exists(ProvidesMcpTools::class) => $this->app->make(PaymentsModule::class),
                default => $this->app->make(PaymentsHome::class),
            });
        }

        $screens = $this->app->make(ScreenRegistry::class);

        // The tab «Payments» of the site's settings: who hears of a problem (§11.5). A patch may
        // name a screen nobody registered yet — the registry applies it when the tree is built.
        if (class_exists(Settings::class) && class_exists(NotificationEvents::class)) {
            $screens->extend(Settings::SCREEN, __DIR__.'/../resources/screens/settings.json');
        }
        $screens->register(MethodForm::SCREEN, __DIR__.'/../resources/screens/method.json');

        // The delivery methods a rule names: the delivery module's list, or their keys as text
        // where it is not installed (§13.3). Read when the screen is asked for, not at boot.
        $screens->extend(MethodForm::SCREEN, fn (): array => ($options = $this->app->make(MethodForm::class)->deliveryOptions()) !== null
            ? [['op' => 'set', 'target' => 'delivery_methods', 'props' => ['options' => $options]]]
            : [
                ['op' => 'remove', 'target' => 'delivery_methods'],
                ['op' => 'add', 'target' => 'rules', 'position' => 'after:delivery_mode', 'node' => [
                    'id' => 'delivery_keys',
                    'type' => 'wx-input',
                    'name' => 'delivery_keys',
                    'label' => 'trans::webx-payments::panel.method.delivery-keys',
                    'help' => 'trans::webx-payments::panel.method.delivery-keys-help',
                    'visible' => ['when' => 'delivery_mode', 'in' => ['methods', 'except']],
                    'props' => ['maxlength' => 500],
                ]],
            ]);

        // The tab «Payment» of the order's card, before «Customer» — after «Delivery» where it is
        // (§13.4): the node reads the order's id from the card. A static `can`, not a closure: the
        // registry keeps the patched tree for every visitor.
        $screens->extend(OrdersModule::SCREEN, [[
            'op' => 'add',
            'target' => 'tabs',
            'position' => 'before:customer',
            'node' => [
                'id' => 'payment',
                'type' => 'wx-tab',
                'label' => 'trans::webx-payments::panel.order.tab',
                'can' => PaymentsSection::VIEW,
                'children' => [['id' => 'payment-order', 'type' => 'wx-payments-order']],
            ],
        ]]);
    }
}
