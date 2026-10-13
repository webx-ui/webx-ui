<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * The switch for the panel on the site (WEBX_UI_SITE_BAR.md, §3): on the person, like the theme.
 */
final class PanelSiteBarTest extends TestCase
{
    #[Test]
    public function the_bar_is_on_until_somebody_turns_it_off(): void
    {
        $this->actingAs($this->admin(), 'cms');

        $this->getJson('api/cms/auth/me')
            ->assertOk()
            ->assertJsonPath('data.siteBar', true);
    }

    #[Test]
    public function turning_it_off_is_stored_on_the_person(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'cms');

        $this->putJson('api/cms/auth/site-bar', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.siteBar', false);

        $this->assertFalse($admin->fresh()?->site_bar);

        $this->putJson('api/cms/auth/site-bar', ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.siteBar', true);
    }

    #[Test]
    public function a_request_that_says_nothing_is_refused(): void
    {
        $this->actingAs($this->admin(), 'cms');

        $this->putJson('api/cms/auth/site-bar', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('enabled');
    }

    #[Test]
    public function a_visitor_has_no_bar_to_switch(): void
    {
        $this->putJson('api/cms/auth/site-bar', ['enabled' => false])->assertUnauthorized();
    }
}
