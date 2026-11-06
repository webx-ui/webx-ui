<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Cache\CacheServiceProvider;
use WebxUi\Delivery\DeliveryServiceProvider;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Geography\GeographyServiceProvider;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Panel\MethodForm;

/**
 * The delivery rule of a method on a site with the delivery module (spec §13.3): the patch fills
 * the list with the site's methods and takes the text field off.
 */
final class MethodFormWithDeliveryTest extends PanelCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), CacheServiceProvider::class, GeographyServiceProvider::class, DeliveryServiceProvider::class];
    }

    #[Test]
    public function the_methods_are_a_list_right_after_the_mode(): void
    {
        DeliveryMethod::query()->create(['key' => 'our-courier', 'type' => 'store.courier', 'titles' => ['en' => 'Our courier'], 'enabled' => true]);

        $root = $this->app->make(ScreenRegistry::class)->tree(MethodForm::SCREEN);
        $methods = Tree::find($root, 'delivery_methods');

        $this->assertNull(Tree::find($root, 'delivery_keys'));
        $this->assertNotNull($methods);
        $this->assertContains(['value' => 'our-courier', 'label' => 'Our courier'], $methods['props']['options']);
        $this->assertSame(
            ['group_key', 'delivery_mode', 'delivery_methods', 'min_amount', 'max_amount'],
            array_column(Tree::find($root, 'rules')['children'], 'id'),
        );
    }

    #[Test]
    public function the_ticked_methods_are_saved_as_the_rule_of_the_method(): void
    {
        $this->signIn();
        $card = PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail();

        $this->putJson($this->api('methods/'.$card->id), ['values' => ['delivery_mode' => 'except', 'delivery_methods' => ['our-courier']]])->assertOk();

        $this->assertSame(['mode' => 'except', 'methods' => ['our-courier']], $card->refresh()->delivery_rule);
    }
}
