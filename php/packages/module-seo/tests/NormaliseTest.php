<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Normalisation;
use WebxUi\Settings\Settings;

/**
 * One address per page, one 301 to it (audit spec §7): every part a setting, all of them
 * applied at once, before the redirects table.
 */
final class NormaliseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/about', fn (): string => 'about');
        // A route of its own, so the registry's fallback — which has its own idea of case — is
        // not what answers.
        Route::middleware('web')->get('/files/{name}', fn (string $name): string => $name);
    }

    #[Test]
    public function nothing_moves_until_a_part_is_turned_on(): void
    {
        $this->get('http://www.shop.example.com/about')->assertOk();
        $this->get('/files/Report.PDF/')->assertOk();
    }

    #[Test]
    public function every_difference_is_one_redirect(): void
    {
        $this->settings([
            Normalisation::HOST => 'bare',
            Normalisation::HTTPS => true,
            Normalisation::SLASHES => true,
            Normalisation::INDEX => true,
            Normalisation::TRAILING => 'strip',
            Normalisation::LOWERCASE => true,
        ]);

        $this->get('http://www.shop.example.com//About//index.html?utm=1')
            ->assertStatus(301)
            ->assertRedirect('https://shop.example.com/about?utm=1');

        $this->get('https://shop.example.com/about')->assertOk()->assertSee('about');
    }

    #[Test]
    public function files_keep_their_names_and_the_panel_its_addresses(): void
    {
        $this->settings([Normalisation::LOWERCASE => true, Normalisation::TRAILING => 'add']);

        $this->get('/files/Report.PDF')->assertOk()->assertSee('Report.PDF');
        $this->assertNotSame(301, $this->get('/'.trim((string) config('webx-admin.path'), '/').'/Pages')->getStatusCode());
        $this->get('/About')->assertRedirect('http://localhost/about/');
    }

    #[Test]
    public function the_redirects_table_sees_the_normalised_address(): void
    {
        $this->settings([Normalisation::LOWERCASE => true]);
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old', 'target' => '/about']);

        // Two hops for the visitor: first the case, then the table — each a 301 of its own,
        // and the table matched the address it was written for.
        $this->get('/OLD')->assertRedirect('http://localhost/old');
        $this->get('/old')->assertRedirect('/about');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        $this->app->make(Settings::class)->save($values);
    }
}
