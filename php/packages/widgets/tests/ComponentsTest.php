<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Widgets\Widgets;

/**
 * The markup of the components is the widget's public contract (spec §5): the classes and the
 * `data-*` the runtime reads, what a visitor without JavaScript sees, and the theme's door to
 * replace the view.
 */
class ComponentsTest extends TestCase
{
    #[Test]
    public function a_dialog_is_a_labelled_dialog_with_a_close_link_that_works_without_javascript(): void
    {
        $html = Blade::render('<x-webx-dialog id="callback" title="Call me back" class="is-wide"><p>Form</p></x-webx-dialog>');

        $this->assertStringContainsString('<dialog id="callback" aria-labelledby="callback-title" class="webx-dialog is-wide">', $html);
        $this->assertStringContainsString('<h2 class="webx-dialog__title" id="callback-title">Call me back</h2>', $html);
        $this->assertStringContainsString('<a class="webx-dialog__close" href="#" data-webx-dialog-close aria-label="Close">', $html);
        $this->assertStringContainsString('<div class="webx-dialog__body"><p>Form</p></div>', $html);
        $this->assertSame(['dialog'], app(Widgets::class)->claimed());
    }

    #[Test]
    public function a_dialog_without_a_title_has_no_dangling_label(): void
    {
        $html = Blade::render('<x-webx-dialog id="menu">Menu</x-webx-dialog>');

        $this->assertStringNotContainsString('aria-labelledby', $html);
        $this->assertStringNotContainsString('webx-dialog__title', $html);
    }

    #[Test]
    public function the_words_follow_the_locale_and_a_prop_overrides_them(): void
    {
        app()->setLocale('ru');
        $this->assertStringContainsString('aria-label="Закрыть"', Blade::render('<x-webx-dialog id="a">x</x-webx-dialog>'));
        $this->assertStringContainsString('aria-label="Hide"', Blade::render('<x-webx-dialog id="b" close="Hide">x</x-webx-dialog>'));
    }

    #[Test]
    public function tabs_without_javascript_are_headings_above_their_panels(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-webx-tabs label="Product">
                <x-webx-tabs.panel title="Delivery">Three days.</x-webx-tabs.panel>
                <x-webx-tabs.panel title="Returns" selected :level="4">Thirty days.</x-webx-tabs.panel>
            </x-webx-tabs>
            BLADE);

        $this->assertStringContainsString('data-webx-tabs-label="Product"', $html);
        $this->assertStringContainsString('<section data-webx-tab="" class="webx-tabs__panel">', $html);
        $this->assertStringContainsString('<h3 class="webx-tabs__title">Delivery</h3>', $html);
        $this->assertStringContainsString('<section data-webx-tab="" data-webx-tab-selected="" class="webx-tabs__panel">', $html);
        $this->assertStringContainsString('<h4 class="webx-tabs__title">Returns</h4>', $html);
    }
}
