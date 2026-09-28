<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Banners\Places;

/**
 * `banners_layout()` — how the site's template lays a place out (§5.3): the package's options,
 * the site's, the place's and the argument, in that order.
 */
final class LayoutTest extends TestCase
{
    #[Test]
    public function the_package_the_config_the_place_and_the_argument_in_that_order(): void
    {
        $this->assertSame([...Places::OPTIONS, 'layout' => 'slider'], banners_layout('hero'));

        $promo = banners_layout('promo');
        $this->assertSame('single', $promo['layout']);
        $this->assertSame('3/1', $promo['ratio']);
        $this->assertSame('3/2', $promo['ratio_mobile']);
        $this->assertSame(6000, $promo['interval']);

        config()->set('webx-banners.options.interval', 4000);
        config()->set('webx-banners.places.hero.options', ['interval' => 3000, 'dots' => false]);

        $hero = banners_layout('hero');
        $this->assertSame(3000, $hero['interval'], 'the place over the site');
        $this->assertSame(false, $hero['dots']);
        $this->assertSame(4000, banners_layout('promo')['interval'], 'the site over the package');

        $this->assertSame('random', banners_layout('hero', 'random')['layout'], 'the argument over the place');
        $this->assertSame(3000, banners_layout('hero', 'random')['interval']);
    }

    #[Test]
    public function a_layout_that_is_not_one_is_the_places(): void
    {
        $this->assertSame('single', banners_layout('promo', 'carousel')['layout']);

        config()->set('webx-banners.places.hero.layout', 'mosaic');
        $this->assertSame('slider', banners_layout('hero')['layout'], 'an unknown layout of a place is the site\'s');

        config()->set('webx-banners.layout', 'random');
        $this->assertSame('random', banners_layout('hero')['layout']);
    }

    #[Test]
    public function an_unknown_place_gets_the_defaults_and_an_id_is_looked_up(): void
    {
        $this->assertSame([...Places::OPTIONS, 'layout' => 'slider'], banners_layout('nowhere'));
        $this->assertSame([...Places::OPTIONS, 'layout' => 'slider'], banners_layout());
        $this->assertSame([...Places::OPTIONS, 'layout' => 'slider'], banners_layout(999));

        $row = $this->app->make(Places::class)->row('promo');
        $this->assertNotNull($row);

        $this->assertSame('single', banners_layout((int) $row->getKey())['layout']);
    }

    #[Test]
    public function a_published_config_with_one_option_keeps_the_others(): void
    {
        // What `mergeConfigFrom` leaves after a site published the config and kept one line.
        config()->set('webx-banners.options', ['interval' => 9000]);

        $layout = banners_layout('hero');

        $this->assertSame(9000, $layout['interval']);
        $this->assertSame(768, $layout['breakpoint']);
        $this->assertTrue($layout['autoplay']);
        $this->assertSame(array_keys([...Places::OPTIONS, 'layout' => 'x']), array_keys($layout));
    }

    #[Test]
    public function an_option_the_package_does_not_know_goes_to_the_template(): void
    {
        config()->set('webx-banners.places.hero.options', ['theme' => 'dark']);

        $this->assertSame('dark', banners_layout('hero')['theme']);
    }
}
