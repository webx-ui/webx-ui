<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;

/** The card of a listing is the point `services.card` (THEMES §10.4). */
final class CardPointTest extends TestCase
{
    #[Test]
    public function the_module_prints_its_own_card_with_its_classes(): void
    {
        $this->service('crowns')->syncCategories([$this->category('implants')->getKey()]);

        foreach (['/services', '/services/implants'] as $path) {
            $page = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('<ul class="webx-services-cards">', $page, $path);
            $this->assertStringContainsString('<h3 class="webx-services-card__title">Crowns</h3>', $page, $path);
        }
    }

    /** No provider and no overridden listing: the theme's `views/parts/services/card.blade.php`. */
    #[Test]
    public function a_theme_answers_the_point_with_a_view_of_its_own(): void
    {
        $this->service('crowns')->syncCategories([$this->category('implants')->getKey()]);

        $theme = storage_path('framework/testing/theme-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($theme.'/parts/services');
        File::put($theme.'/parts/services/card.blade.php', '<li class="theme-card">{{ $service->title }}</li>');
        $this->beforeApplicationDestroyed(static function () use ($theme): void {
            File::deleteDirectory($theme);
        });
        View::addLocation($theme);

        foreach (['/services', '/services/implants'] as $path) {
            $page = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('<li class="theme-card">Crowns</li>', $page, $path);
            $this->assertStringNotContainsString('webx-services-card__title', $page, $path);
        }
    }
}
