<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\Money;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Orders\Tests\PlaceTestCase;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The section in the panel: the module with the administrators of `module-auth`, so the routes
 * stand behind the panel's guard and a permission means what it means on a site.
 */
abstract class PanelTestCase extends PlaceTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            CartServiceProvider::class,
            OrdersServiceProvider::class,
        ];
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function manager(array $permissions = ['orders.view', 'orders.manage', 'orders.refund']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => "Manager {$count}",
            'email' => "manager-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "manager-{$count}", 'name' => 'Manager', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    /** @param  list<string>|null  $permissions */
    protected function signIn(?array $permissions = null): CmsUser
    {
        $user = $permissions === null ? $this->manager() : $this->manager($permissions);
        $this->actingAs($user, 'cms');

        return $user;
    }

    protected function api(string|int $path = ''): string
    {
        return rtrim('/api/cms/orders/'.$path, '/');
    }

    /**
     * An order of one line, or of the lines asked, placed the way the checkout places it.
     *
     * @param  array<string, mixed>  $with
     * @param  list<array<string, mixed>>|null  $items
     */
    protected function order(array $with = [], ?array $items = null): Order
    {
        return $this->place($this->cart($items ?? [['type' => 'test.product', 'ref' => 1]]), $with);
    }

    /** An amount as the panel writes it, in the currency of the tests. */
    protected function money(int $minor): string
    {
        return $this->app->make(Money::class)->format($minor, 'EUR');
    }

    /** @var array<class-string, int> how many of each event were heard */
    private array $heard = [];

    /**
     * Count the events of a class as they are dispatched after the commit; {@see times()} reads it.
     *
     * @param  class-string  $event
     */
    protected function listenFor(string $event): void
    {
        $this->heard[$event] = 0;
        Event::listen($event, function () use ($event): void {
            $this->heard[$event]++;
        });
    }

    /** @param  class-string  $event */
    protected function times(string $event): int
    {
        return $this->heard[$event] ?? 0;
    }

    /**
     * The row of a list with this value under this key.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected static function pick(array $rows, string $key, mixed $value): array
    {
        foreach ($rows as $row) {
            if (($row[$key] ?? null) === $value) {
                return $row;
            }
        }

        throw new \RuntimeException("No row with {$key} = ".json_encode($value).'.');
    }

    /** An internal status of the default set by its English name. */
    protected function internal(string $name): OrderStatus
    {
        return OrderStatus::query()->get()->first(static fn (OrderStatus $status): bool => $status->getTranslation('name', 'en') === $name)
            ?? throw new \RuntimeException("No status «{$name}».");
    }
}
