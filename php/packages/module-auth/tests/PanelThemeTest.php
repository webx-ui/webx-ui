<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;

final class PanelThemeTest extends TestCase
{
    #[Test]
    public function nobody_starts_with_a_theme_chosen_for_them(): void
    {
        $this->actingAs($this->admin(), 'cms');

        $this->getJson('api/cms/auth/me')
            ->assertOk()
            ->assertJsonPath('data.theme', null);
    }

    #[Test]
    public function choosing_a_theme_stores_it_on_the_person(): void
    {
        // On the person rather than in the browser: somebody who works dark at night on a
        // laptop should find the panel dark in the morning at a desk.
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/theme', ['theme' => 'dark'])
            ->assertOk()
            ->assertJsonPath('data.theme', 'dark');

        $this->assertSame('dark', $admin->fresh()?->theme);
    }

    #[Test]
    public function following_the_machine_is_a_choice_and_is_stored_as_one(): void
    {
        // Null is not "unanswered": it is somebody saying the panel should follow whatever
        // machine they are at, and that has to travel between their machines like any other
        // choice — so it has to overwrite a theme they picked before.
        $admin = $this->admin();
        $admin->forceFill(['theme' => 'dark'])->save();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/theme', ['theme' => null])
            ->assertOk()
            ->assertJsonPath('data.theme', null);

        $this->assertNull($admin->fresh()?->theme);
    }

    #[Test]
    public function a_theme_nobody_has_is_refused(): void
    {
        $this->actingAs($this->admin(), 'cms');

        $this->putJson('api/cms/auth/theme', ['theme' => 'sepia'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');
    }

    #[Test]
    public function a_request_that_says_nothing_is_a_mistake_rather_than_a_reset(): void
    {
        $this->actingAs($this->admin(), 'cms');

        $this->putJson('api/cms/auth/theme', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme');
    }

    #[Test]
    public function a_visitor_has_no_theme_to_change(): void
    {
        $this->putJson('api/cms/auth/theme', ['theme' => 'dark'])->assertUnauthorized();
    }
}
