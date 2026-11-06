<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Panel\MethodForm;

/**
 * The delivery rule of a method on a site without the delivery module (spec §13.3): both fields
 * are in the screen, the patch takes the list off and the keys stay as text. The site with the
 * delivery module — `MethodFormWithDeliveryTest`.
 */
final class MethodFormDeliveryTest extends PanelCase
{
    #[Test]
    public function the_keys_are_asked_for_as_text_right_after_the_mode(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);
        $root = $screens->tree(MethodForm::SCREEN);
        $keys = Tree::find($root, 'delivery_keys');

        $this->assertNull(Tree::find($root, 'delivery_methods'));
        $this->assertNotNull($keys);
        $this->assertSame(['wx-input', 'delivery_keys', ['when' => 'delivery_mode', 'in' => ['methods', 'except']], 500], [
            $keys['type'], $keys['name'], $keys['visible'], $keys['props']['maxlength'],
        ]);
        $this->assertSame(
            ['group_key', 'delivery_mode', 'delivery_keys', 'min_amount', 'max_amount'],
            array_column(Tree::find($root, 'rules')['children'], 'id'),
        );
    }

    #[Test]
    public function the_keys_are_saved_as_the_rule_of_the_method(): void
    {
        $this->signIn();
        $card = PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail();

        $this->putJson($this->api('methods/'.$card->id), ['values' => ['delivery_mode' => 'methods', 'delivery_keys' => 'store-pickup, our-courier']])->assertOk();

        $this->assertSame(['mode' => 'methods', 'methods' => ['store-pickup', 'our-courier']], $card->refresh()->delivery_rule);
    }
}
