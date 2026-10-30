<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;

/**
 * `orders.show` (spec §11.3): a described screen with the public ids neighbours patch —
 * `header-actions`, `tabs`, `summary` — and the fields the card saves, under the names the
 * service's errors use. A patch of `tabs` adds a tab, the way the delivery adds «Delivery».
 */
final class ScreenTest extends PanelTestCase
{
    #[Test]
    public function the_card_is_a_screen_with_the_ids_its_neighbours_patch(): void
    {
        $this->signIn(['orders.view']);
        $root = $this->getJson('/api/cms/screens/orders.show')->assertOk()->json('data.root');
        $ids = self::ids($root);

        foreach (['header-actions', 'tabs', 'summary', 'items', 'customer', 'history'] as $id) {
            $this->assertContains($id, $ids);
        }

        $fields = $this->app->make(ScreenRegistry::class)->fields('orders.show');
        $this->assertSame(
            ['customer.first_name', 'customer.last_name', 'customer.phone', 'customer.email', 'recipient.first_name', 'recipient.last_name', 'recipient.phone', 'comment', 'manager_note'],
            array_map(static fn (array $node): string => (string) $node['name'], $fields),
        );
    }

    #[Test]
    public function only_the_first_name_of_the_buyer_carries_an_asterisk(): void
    {
        $this->signIn(['orders.view']);
        $root = $this->getJson('/api/cms/screens/orders.show')->assertOk()->json('data.root');
        $flags = [];

        foreach (['buyer-first-name', 'buyer-last-name', 'buyer-phone', 'buyer-email', 'recipient-first-name', 'recipient-phone'] as $id) {
            $flags[$id] = self::find($root, $id)['required'] ?? false;
        }

        // A phone or an e-mail — either, so neither; a recipient emptied whole is the buyer.
        $this->assertSame([
            'buyer-first-name' => true,
            'buyer-last-name' => false,
            'buyer-phone' => false,
            'buyer-email' => false,
            'recipient-first-name' => false,
            'recipient-phone' => false,
        ], $flags);
        $this->assertNotNull(self::find($root, 'buyer-contacts-hint'));
    }

    #[Test]
    public function a_neighbour_adds_its_tab_with_a_patch(): void
    {
        $this->app->make(ScreenRegistry::class)->extend('orders.show', [[
            'op' => 'add',
            'target' => 'tabs',
            'position' => 'after:items',
            'node' => ['id' => 'delivery', 'type' => 'wx-tab', 'label' => 'Delivery', 'children' => [
                ['id' => 'delivery-card', 'type' => 'wx-card', 'children' => []],
            ]],
        ], [
            'op' => 'add',
            'target' => 'header-actions',
            'node' => ['id' => 'track', 'type' => 'wx-paragraph', 'label' => 'Track the parcel'],
        ]]);

        $this->signIn(['orders.view']);
        $root = $this->getJson('/api/cms/screens/orders.show')->assertOk()->json('data.root');
        $tabs = self::find($root, 'tabs');

        $this->assertSame(['items', 'delivery', 'customer', 'history'], array_column($tabs['children'], 'id'));
        $this->assertSame('Delivery', $tabs['children'][1]['label']);
        $this->assertSame(['track'], array_column(self::find($root, 'header-actions')['children'], 'id'));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<string>
     */
    private static function ids(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $ids[] = (string) $node['id'];
            $ids = [...$ids, ...self::ids($node['children'] ?? [])];
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private static function find(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }

            $inner = self::find($node['children'] ?? [], $id);

            if ($inner !== null) {
                return $inner;
            }
        }

        return null;
    }
}
