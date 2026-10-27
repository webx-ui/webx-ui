<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Routing\RoutingServiceProvider;

abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    /** A token of the shape Telegram gives — made up, never a real bot's. */
    protected const TOKEN = '000000001:TEST-token-not-real-0000000000';

    protected const API = '/api/cms/notifications';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            NotificationsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from', ['address' => 'site@example.com', 'name' => 'Site']);
        $app['config']->set('queue.default', 'database');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-notifications.telegram.api', 'https://telegram.test');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(NotificationEvents::class)->register(new EventDefinition(
            key: 'shop.order',
            label: 'Order placed',
            module: 'shop',
            variables: [
                new Variable('number', 'Order number', example: '1024'),
                new Variable('total', 'Total', example: '10.00'),
                new Variable('note', 'Note', example: 'Leave at the door'),
                new Variable('field.*', 'A field by its name'),
                new Variable('items', 'Items', example: [['name' => 'Tea', 'qty' => '2', 'price' => '3.00']], fields: ['name' => 'Name', 'qty' => 'Quantity', 'price' => 'Price']),
                new Variable('customer_email', 'Customer e-mail', example: 'customer@example.com', address: 'email'),
                new Variable('reply_to', 'Reply to', example: 'customer@example.com', address: 'email'),
            ],
            sample: [
                'telegram' => ['template' => '<b>Order {{number}}</b> — {{total}}'],
                'email' => ['subject' => 'Order {{number}}', 'template' => "Order {{number}}\nTotal: {{total}}"],
            ],
            url: static fn (array $values): ?string => isset($values['number']) ? 'https://example.test/cms/orders/'.$values['number'] : null,
        ));
    }

    /**
     * A channel as the panel saves it: enabled, with a template in English.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function telegram(array $attributes = []): Channel
    {
        return Channel::query()->create([
            'name' => 'Orders in Telegram',
            'provider' => 'telegram',
            'event' => 'shop.order',
            'credentials' => ['token' => self::TOKEN],
            'recipients' => [['chat_id' => '100200300', 'label' => 'Managers']],
            'template' => ['en' => '<b>Order {{number}}</b>: {{note}}'],
            'enabled' => true,
            ...$attributes,
        ])->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function email(array $attributes = []): Channel
    {
        return Channel::query()->create([
            'name' => 'Orders by mail',
            'provider' => 'email',
            'event' => 'shop.order',
            'options' => ['mode' => 'site'],
            'recipients' => [['email' => 'manager@example.com']],
            'subject' => ['en' => 'Order {{number}}'],
            'template' => ['en' => "Order {{number}}\nNote: {{note}}"],
            'enabled' => true,
            ...$attributes,
        ])->refresh();
    }

    /**
     * Somebody the panel lets in: a super administrator, or one with these permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function admin(bool $super = true, array $permissions = []): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => "Admin {$count}",
            'email' => "admin-{$count}@example.com",
            'password' => 'correct-horse-battery',
            'is_super' => $super,
            'is_active' => true,
        ]);

        if (! $super) {
            $role = Role::query()->create(['slug' => "role-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
            $user->roles()->attach($role->getKey());
        }

        return $user;
    }
}
