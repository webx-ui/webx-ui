<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Purchasables;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Tests\Fakes\FakeShirts;

/**
 * «New order» in the panel (§29.2, stage 22): the products found through the cart's providers,
 * the totals counted live before the contacts, and the order placed by the service with
 * `source = panel` — the list finds it by that source and the card says where it came from.
 */
final class NewOrderTest extends PanelTestCase
{
    private const array CUSTOMER = ['first_name' => 'Alex', 'last_name' => 'Doe', 'phone' => '+800 1234 5678', 'email' => 'alex@example.test'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Purchasables::class)->register('test.shirt', FakeShirts::class);
    }

    /** @return list<array<string, mixed>> two products, the second with its size */
    private static function lines(string $size = 'm'): array
    {
        return [
            ['type' => 'test.product', 'ref' => 1, 'qty' => 2],
            ['type' => 'test.shirt', 'ref' => 1, 'qty' => 1, 'options' => ['size' => $size]],
        ];
    }

    #[Test]
    public function it_finds_what_can_be_put_in_a_line_with_the_choices_of_a_variant(): void
    {
        $this->signIn();

        $found = $this->getJson($this->api('new/products').'?search=shi')->assertOk()->json('data');

        // `test.product` cannot be searched: it is not asked, the shirt is.
        $this->assertCount(1, $found);
        $this->assertSame(['test.shirt', 1, 'Shirt', 'SHIRT-1', 'Shirts', true], [$found[0]['type'], $found[0]['ref'], $found[0]['name'], $found[0]['sku'], $found[0]['hint'], $found[0]['available']]);
        $this->assertSame($this->money(2000), $found[0]['price_formatted']);
        $this->assertSame(['key' => 'size', 'label' => 'Size'], array_intersect_key($found[0]['options'][0], ['key' => 1, 'label' => 1]));
        $this->assertSame(['value' => 'm', 'label' => 'M'], $found[0]['options'][0]['values'][1]);
        $this->assertSame([], $this->getJson($this->api('new/products').'?search=chair')->assertOk()->json('data'));

        $meta = $this->getJson($this->api('new'))->assertOk()->json('data');
        $this->assertSame('en', $meta['locale']);
        $this->assertContains('en', array_column($meta['locales'], 'code'));
    }

    #[Test]
    public function the_totals_are_counted_before_the_contacts_and_nothing_stays(): void
    {
        $this->signIn();

        $answer = $this->postJson($this->api('new/preview'), ['lines' => self::lines()])->assertOk()->json('data');

        $this->assertTrue($answer['orderable']);
        $this->assertNull($answer['message']);
        $this->assertSame(2 * 4999 + 2000, $answer['totals']['payable']);
        $this->assertSame('Size: M', $answer['totals']['lines'][1]['options_label']);
        $this->assertSame(0, Cart::query()->count());

        // A variant not chosen is refused under its line, as the order would be.
        $this->postJson($this->api('new/preview'), ['lines' => self::lines('xl')])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.options']);
        // A price of the manager's own is not a field at all (§18).
        $this->postJson($this->api('new/preview'), ['lines' => [['type' => 'test.product', 'ref' => 1, 'price' => 1]]])
            ->assertStatus(422);
        $this->assertDatabaseCount('carts', 0);
    }

    #[Test]
    public function the_manager_places_an_order_of_two_products_one_with_a_variant(): void
    {
        $this->listenFor(OrderPlaced::class);
        $manager = $this->signIn();
        $body = [
            'lines' => self::lines(),
            'customer' => self::CUSTOMER,
            'recipient' => ['first_name' => '', 'last_name' => '', 'phone' => ''],
            'comment' => 'Taken by phone',
            'key' => 'form-1',
        ];

        $created = $this->postJson($this->api('new'), $body)->assertCreated()->json('data');
        $order = Order::query()->findOrFail((int) $created['id']);

        $this->assertSame($order->number, $created['number']);
        $this->assertSame(2 * 4999 + 2000, $order->payable);
        $this->assertNull($order->recipient);
        $this->assertSame('Taken by phone', $order->comment);
        $this->assertSame([['Item A', 2, null], ['Shirt', 1, 'Size: M']], OrderItem::query()->where('order_id', $order->id)->orderBy('position')->get()
            ->map(static fn (OrderItem $item): array => [$item->name['en'], $item->qty, $item->options_label])->all());

        $placed = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Placed->value)->sole();
        $this->assertSame(['panel', (int) $manager->id], [$placed->source, $placed->admin_id]);
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(1, $this->times(OrderPlaced::class));

        // A second press of the same form is the first order.
        $this->assertSame($created['id'], $this->postJson($this->api('new'), $body)->assertCreated()->json('data.id'));
        $this->assertSame(1, Order::query()->count());

        // The card says where it came from; the list finds it by that source.
        $this->assertSame('panel', $this->getJson($this->api($order->id))->assertOk()->json('data.order.milestones.placed.source'));
        $checkout = $this->order();
        $rows = $this->getJson($this->api().'?source=panel')->assertOk()->json('data');
        $this->assertSame([$order->id], array_column($rows, 'id'));
        $this->assertSame('panel', $rows[0]['source']);
        $this->assertSame([$checkout->id], array_column($this->getJson($this->api().'?source=checkout')->json('data'), 'id'));
    }

    #[Test]
    public function the_contacts_are_checked_when_it_is_placed_and_nothing_is_left_of_a_refusal(): void
    {
        $this->signIn();

        $this->postJson($this->api('new'), ['lines' => self::lines(), 'customer' => ['first_name' => '', 'phone' => 'call me']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer.first_name', 'customer.phone']);
        $this->postJson($this->api('new'), ['lines' => [], 'customer' => self::CUSTOMER])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
        $this->postJson($this->api('new'), ['lines' => self::lines(), 'customer' => self::CUSTOMER, 'locale' => 'xx'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);

        $this->assertSame([0, 0], [Order::query()->count(), Cart::query()->count()]);
    }

    #[Test]
    public function a_refusal_names_the_fields_in_words_of_the_panel_language(): void
    {
        $this->signIn();
        $said = [
            'en' => ['Enter a phone or an e-mail.', 'Fill in «First name».', '«Last name» is too long: at most 190 characters.'],
            'ru' => ['Укажите телефон или e-mail.', 'Заполните поле «Имя».', 'Поле «Фамилия» слишком длинное: не больше 190 символов.'],
            'uk' => ['Вкажіть телефон або e-mail.', "Заповніть поле «Ім'я».", 'Поле «Прізвище» задовге: не більше 190 символів.'],
        ];

        foreach ($said as $locale => [$either, $first, $long]) {
            $response = $this->withHeader('X-Webx-Locale', $locale)
                ->postJson($this->api('new'), ['lines' => self::lines(), 'customer' => ['first_name' => '', 'last_name' => str_repeat('x', 191)]])
                ->assertStatus(422);

            // Under both fields of the pair and in the toast: the first refusal, in words.
            $this->assertSame([$either], $response->json('errors')['customer.phone'], $locale);
            $this->assertSame([$either], $response->json('errors')['customer.email'], $locale);
            $this->assertSame([$first], $response->json('errors')['customer.first_name'], $locale);
            $this->assertSame([$long], $response->json('errors')['customer.last_name'], $locale);
            $words = implode(' ', [(string) $response->json('message'), ...array_merge(...array_values($response->json('errors')))]);
            $this->assertStringNotContainsString('customer.', $words, $locale);
            $this->assertStringNotContainsString('validation.', $words, $locale);
        }
    }

    #[Test]
    public function without_managing_orders_there_is_no_new_order(): void
    {
        $this->signIn(['orders.view', 'orders.refund']);

        $this->getJson($this->api('new'))->assertForbidden();
        $this->getJson($this->api('new/products').'?search=shirt')->assertForbidden();
        $this->postJson($this->api('new/preview'), ['lines' => self::lines()])->assertForbidden();
        $this->postJson($this->api('new'), ['lines' => self::lines(), 'customer' => self::CUSTOMER])->assertForbidden();
        $this->assertSame(0, Order::query()->count());
    }
}
