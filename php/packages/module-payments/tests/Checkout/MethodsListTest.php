<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\AddResult;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Tests\Checkout\UsesFakeSlots;
use WebxUi\Orders\Tests\Checkout\VisitsCheckout;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentGroup;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Models\PaymentSelection;
use WebxUi\Payments\Rendering\Assets;
use WebxUi\Payments\Selections;
use WebxUi\Payments\Tests\Fakes\MarkedType;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The list group → method on the payment step (groups spec §6): what it prints for each shape of a
 * site, and the rule of §6.4 for a form that sends both radios. What the eye and the keyboard get
 * is the browser's to say; here is what the markup promises them.
 */
final class MethodsListTest extends PaymentsCheckoutCase
{
    use UsesFakeSlots;
    use VisitsCheckout;

    private AddResult $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableBuiltIns();
        $this->installSlots(shipping: true, payment: false);
        $this->app->make(MethodTypes::class)->register(new MarkedType);
        // A pick-up: everything is on offer, the two marked methods with «on receipt» in its group.
        $this->shipping->choose('store-pickup', new ShippingLine(ShippingLine::FREE), 'Pick up', 'store', pickup: true);
    }

    #[Test]
    public function a_group_of_several_is_a_head_over_its_methods_and_a_group_of_one_is_one_row(): void
    {
        $this->marked('card', 5, ['visa', 'mastercard']);
        $this->marked('wallet', 6, ['google-pay']);
        PaymentGroup::query()->create(['key' => 'on_receipt', 'description' => ['en' => 'Whatever suits you']]);
        $page = $this->step();

        $heads = $page->query('//input[@name="group"]');
        $this->assertSame(['on_receipt', 'transfer'], $this->values($heads));

        $several = $this->one($page, '//*[@data-group="on_receipt"]');
        $this->assertSame('Pay on receipt', $this->text($page, './/*[contains(@class, "__group-head")]//*[contains(@class, "__row-title")]', $several));
        $this->assertSame('Whatever suits you', $this->text($page, './/*[contains(@class, "__group-head")]//*[contains(@class, "__row-note")]', $several));
        $this->assertSame(['card', 'wallet', OnReceipt::METHOD], $this->values($page->query('.//input[@name="method"]', $several)));
        $this->assertSame(1, $page->query('.//fieldset[@role="radiogroup"]/legend', $several)->length);

        // The head shows the marks of all its methods, once each, and every method its own.
        $this->assertSame(['visa', 'mastercard', 'google-pay'], $this->marks($page, './/*[contains(@class, "__group-head")]//li', $several));
        $this->assertSame(['visa', 'mastercard', 'google-pay'], $this->marks($page, './/*[contains(@class, "__option")]//li', $several));

        // The row of one method speaks for the method, and only the pictogram is the group's.
        $one = $this->one($page, '//*[@data-group="transfer"]');
        $this->assertSame('Bank transfer', $this->text($page, './/*[contains(@class, "__row-title")]', $one));
        $this->assertSame(BankTransfer::METHOD, $this->one($page, './/input[@name="group"]', $one)->getAttribute('data-method'));
        $this->assertSame(1, $page->query('.//*[contains(@class, "__pictogram")]', $one)->length);
        $this->assertSame(0, $page->query('.//input[@name="method"]', $one)->length);
    }

    #[Test]
    public function at_first_nothing_is_chosen_and_then_the_group_and_its_method_both_say_so(): void
    {
        $this->marked('card', 5);
        $page = $this->step();

        $this->assertSame(0, $page->query('//input[@checked]')->length);
        $this->assertSame(0, $page->query('//*[contains(@class, "is-chosen")]')->length);

        $this->app->make(Selections::class)->put($this->cart->cart->id, OnReceipt::METHOD);
        $page = $this->step();

        $this->assertSame(['on_receipt', OnReceipt::METHOD], $this->values($page->query('//input[@checked]')));
        $this->assertSame(1, $page->query('//*[@data-group="on_receipt" and contains(@class, "is-chosen")]')->length);
        $this->assertSame(1, $page->query('//*[contains(@class, "__option") and contains(@class, "is-chosen")]')->length);
    }

    #[Test]
    public function the_method_of_a_row_of_one_is_chosen_by_the_radio_of_its_group(): void
    {
        $this->context();
        $this->app->make(Selections::class)->put($this->cart->cart->id, BankTransfer::METHOD);
        $page = $this->step();

        $this->assertSame(['transfer'], $this->values($page->query('//input[@checked]')));
    }

    #[Test]
    public function one_group_on_the_site_has_no_heads(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->firstOrFail()->update(['enabled' => false]);
        $this->marked('card', 5, ['visa']);
        $page = $this->step();

        $this->assertSame(0, $page->query('//input[@name="group"]')->length);
        $this->assertSame(0, $page->query('//*[contains(@class, "__pictogram")]')->length);
        $this->assertSame(1, $page->query('//*[contains(@class, "__groups") and contains(@class, "is-flat")]')->length);
        $this->assertSame(['card', OnReceipt::METHOD], $this->values($page->query('//input[@name="method"]')));
        // A row keeps its marks on a flat list too.
        $this->assertSame(['visa'], $this->marks($page, '//*[contains(@class, "__option")]//li'));
    }

    #[Test]
    public function a_site_without_groups_is_the_flat_list_it_always_was(): void
    {
        $this->app['config']->set('webx-payments.groups', []);
        $page = $this->step();

        $this->assertSame(0, $page->query('//input[@name="group"]')->length);
        $this->assertSame([OnReceipt::METHOD, BankTransfer::METHOD], $this->values($page->query('//input[@name="method"]')));
    }

    #[Test]
    public function one_method_is_one_line_chosen_and_written_on_load(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->firstOrFail()->update(['enabled' => false]);
        $page = $this->step();

        $radio = $this->one($page, '//input[@name="method"]');
        $this->assertTrue($radio->hasAttribute('checked'));
        $this->assertTrue($radio->hasAttribute('data-autosave'));
        $this->assertSame(1, $page->query('//*[contains(@class, "is-single")]')->length);

        $this->app->make(Selections::class)->put($this->cart->cart->id, OnReceipt::METHOD);
        $this->assertFalse($this->one($this->step(), '//input[@name="method"]')->hasAttribute('data-autosave'));
    }

    #[Test]
    public function only_a_method_of_apple_pay_alone_says_so_on_its_row(): void
    {
        $this->marked('apple', 5, ['apple-pay']);
        $this->marked('card', 6, ['visa', 'apple-pay']);
        $this->marked('google', 7, ['google-pay']);
        $page = $this->step();

        $rows = $page->query('//*[@data-apple-pay-only]');
        $this->assertSame(1, $rows->length);
        $this->assertSame('apple', $this->one($page, './/input', $this->element($rows->item(0)))->getAttribute('value'));
    }

    #[Test]
    public function the_row_of_a_group_of_one_says_so_on_its_head(): void
    {
        $this->marked('apple', 5, ['apple-pay']);
        PaymentMethod::query()->where('key', 'apple')->firstOrFail()->update(['group_key' => 'online']);
        $page = $this->step();

        $head = $this->one($page, '//*[@data-apple-pay-only]');
        $this->assertStringContainsString('webx-payments__group-head', $head->getAttribute('class'));
        $this->assertSame('apple', $this->one($page, './/input', $head)->getAttribute('data-method'));
    }

    #[Test]
    public function the_row_of_a_flat_list_says_so_too(): void
    {
        config()->set('webx-payments.groups', []);
        $this->marked('apple', 5, ['apple-pay']);

        $this->assertSame('apple', $this->one($this->step(), '//label[@data-apple-pay-only]//input')->getAttribute('value'));
    }

    #[Test]
    public function the_server_knows_no_device_and_keeps_the_method_on_offer(): void
    {
        $this->marked('apple', 5, ['apple-pay']);
        $this->context();

        $this->visit($this->cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => 'apple'], json: true)->assertOk()->assertJsonPath('selected', 'apple');
    }

    #[Test]
    public function a_choice_is_dropped_and_the_step_is_not_ready_as_on_first_opening(): void
    {
        $this->marked('apple', 5, ['apple-pay']);
        $this->context();
        $this->visit($this->cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => 'apple'], json: true)->assertOk();

        $this->visit($this->cart, '/api/payments/selection', ['_method' => 'DELETE'], json: true)
            ->assertOk()
            ->assertJsonPath('selected', null)
            ->assertJsonPath('summary', null);

        $this->assertSame(0, PaymentSelection::query()->where('cart_id', $this->cart->cart->id)->count());
        $this->assertSame(0, $this->step()->query('//input[@checked]')->length);
    }

    #[Test]
    public function dropping_a_choice_with_nothing_chosen_changes_nothing(): void
    {
        $this->context();

        $this->visit($this->cart, '/api/payments/selection', ['_method' => 'DELETE'], json: true)->assertOk()->assertJsonPath('selected', null);
    }

    #[Test]
    public function without_a_cart_there_is_nothing_to_drop(): void
    {
        $this->deleteJson(route('webx-payments.selection.destroy'))->assertNotFound();
    }

    #[Test]
    public function a_method_of_the_group_is_taken_and_any_other_means_the_groups_first(): void
    {
        $this->marked('card', 5);
        $this->marked('wallet', 6);
        $cart = $this->cart();
        $write = fn (array $fields) => $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', ...$fields]);
        $chosen = static fn (): ?string => PaymentSelection::query()->where('cart_id', $cart->cart->id)->value('method_key');

        // The radio of the group left behind travels with the form: the group decides.
        $write(['group' => 'transfer', 'method' => 'card'])->assertRedirect();
        $this->assertSame(BankTransfer::METHOD, $chosen());

        $write(['group' => 'on_receipt', 'method' => 'wallet'])->assertRedirect();
        $this->assertSame('wallet', $chosen());

        // A head without a method under it: the first by the manager's order.
        $write(['group' => 'on_receipt'])->assertRedirect();
        $this->assertSame('card', $chosen());

        // The script sends the method alone.
        $write(['method' => OnReceipt::METHOD])->assertRedirect();
        $this->assertSame(OnReceipt::METHOD, $chosen());
    }

    #[Test]
    public function a_group_with_nothing_on_offer_is_refused_as_a_method_gone(): void
    {
        $cart = $this->cart();

        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'group' => 'nowhere', 'method' => OnReceipt::METHOD], json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', 'The payment method is no longer available, choose another one');
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'group' => ['on_receipt']], json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', 'Choose a payment method');
        $this->assertSame(0, PaymentSelection::query()->count());
    }

    #[Test]
    public function picked_reads_the_pair_without_a_request(): void
    {
        $methods = $this->app->make(Methods::class);
        $this->marked('card', 5);
        $options = $this->app->make(PaymentsCheckoutSlot::class)->state($this->context())->options;

        $this->assertSame('card', $methods->picked($options, null, 'card')?->key());
        $this->assertSame('card', $methods->picked($options, 'on_receipt', null)?->key());
        $this->assertNull($methods->picked($options, null, null));
        $this->assertNull($methods->picked($options, null, 'nope'));
        $this->assertNull($methods->picked([], 'on_receipt', 'card'));
    }

    #[Test]
    public function the_api_says_the_groups_beside_the_methods(): void
    {
        $this->marked('card', 5, ['visa']);

        $this->visit($this->cart(), '/api/payments/methods', json: true)
            ->assertOk()
            ->assertJsonPath('groups.0', [
                'key' => 'on_receipt',
                'title' => 'Pay on receipt',
                'description' => null,
                'pictogram' => 'cash',
                'methods' => ['card', OnReceipt::METHOD],
                'marks' => ['visa'],
            ])
            ->assertJsonPath('groups.1.key', 'transfer');
    }

    #[Test]
    public function the_lists_script_is_printed_once_and_served_for_good(): void
    {
        $this->assertSame(1, $this->step()->query('//script[contains(@src, "methods.js")]')->length);

        $assets = $this->app->make(Assets::class);
        $this->assertNull($assets->methods());

        $this->get('/webx/payments/methods.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8')
            ->assertHeader('ETag', '"'.Assets::version(Assets::METHODS).'"');
    }

    #[Test]
    public function the_list_on_a_page_without_the_step_brings_its_script_itself(): void
    {
        $this->app->forgetScopedInstances();
        $groups = $this->app->make(Methods::class)->grouped($this->app->make(PaymentsCheckoutSlot::class)->state($this->context())->options);

        $html = Blade::render('<x-webx-payments::methods :groups="$groups" legend="Methods" />', ['groups' => $groups]);

        $this->assertSame(1, substr_count($html, '/methods.js'));
    }

    /** @param  list<string>  $marks */
    private function marked(string $key, int $position, array $marks = []): void
    {
        PaymentMethod::query()->create(['key' => $key, 'type' => MarkedType::KEY, 'title' => ['en' => $key], 'enabled' => true, 'position' => $position, 'delivery_rule' => ['mode' => 'any'], 'settings' => ['marks' => $marks]]);
    }

    private function context(): CheckoutContext
    {
        return $this->checkoutOf($this->cart ??= $this->cart(), self::pickup());
    }

    /** The step as drawn for this cart, fresh: the flag «the script is printed» is per response. */
    private function step(): DOMXPath
    {
        $this->app->forgetScopedInstances();
        $html = $this->app->make(PaymentsCheckoutSlot::class)->render($this->context())->toHtml();

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    private function element(?DOMNode $node): DOMElement
    {
        $this->assertInstanceOf(DOMElement::class, $node);

        return $node;
    }

    private function one(DOMXPath $page, string $path, ?DOMElement $in = null): DOMElement
    {
        $node = $page->query($path, $in)->item(0);
        $this->assertInstanceOf(DOMElement::class, $node, $path);

        return $node;
    }

    private function text(DOMXPath $page, string $path, DOMElement $in): string
    {
        return trim($this->one($page, $path, $in)->textContent);
    }

    /**
     * @param  iterable<DOMNode>  $inputs
     * @return list<string>
     */
    private function values(iterable $inputs): array
    {
        $values = [];

        foreach ($inputs as $input) {
            $this->assertInstanceOf(DOMElement::class, $input);
            $values[] = $input->getAttribute('value');
        }

        return $values;
    }

    /** @return list<string> */
    private function marks(DOMXPath $page, string $path, ?DOMElement $in = null): array
    {
        $marks = [];

        foreach ($page->query($path, $in) as $item) {
            $this->assertInstanceOf(DOMElement::class, $item);
            $marks[] = $item->getAttribute('data-mark');
        }

        return $marks;
    }
}
