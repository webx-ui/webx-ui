<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * Each fixture key is defined from its winning level down, and its value names the level, the
 * way the views of ViewOrderTest do.
 */
class ThemeLinesTest extends TestCase
{
    #[Test]
    public function a_word_is_looked_up_site_then_local_then_default(): void
    {
        $this->assertSame('site', __('theme::theme.site'));
        $this->assertSame('local', __('theme::theme.local'));
        $this->assertSame('default', __('theme::theme.default'));
    }

    #[Test]
    public function a_layer_above_overrides_a_nested_key_and_keeps_its_siblings(): void
    {
        $this->assertSame(['a' => 'default', 'b' => 'local'], __('theme::theme.nested'));
    }

    #[Test]
    public function a_language_missing_from_the_layers_above_comes_from_below(): void
    {
        $this->app->setLocale('pl');

        $this->assertSame('domyślny', __('theme::theme.default'));
        // And a key no layer has in Polish falls back to the fallback language, as any word does.
        $this->assertSame('local', __('theme::theme.local'));
    }

    #[Test]
    public function other_namespaces_are_left_to_laravel(): void
    {
        $this->assertSame('The :attribute field is required.', __('validation.required'));
    }
}
