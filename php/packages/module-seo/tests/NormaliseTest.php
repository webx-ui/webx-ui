<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
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
        $this->settings([Normalisation::LOWERCASE => true, Normalisation::HOST => 'bare', Normalisation::HTTPS => true]);

        $this->get('https://shop.example.com/files/Report.PDF')->assertOk()->assertSee('Report.PDF');
        $panel = '/'.trim((string) config('webx-admin.path'), '/');
        $this->assertNotSame(301, $this->get('https://shop.example.com'.$panel.'/Pages')->getStatusCode());
        // The panel keeps its path, not the mirror nor plain http: one place to be signed in.
        $this->get('http://www.shop.example.com'.$panel.'/Pages')->assertRedirect('https://shop.example.com'.$panel.'/Pages');
        // An address the registry answers is spelled the registry's way, in the same 301.
        $this->get('http://www.shop.example.com/Nowhere-Here')->assertRedirect('https://shop.example.com/nowhere-here');
    }

    /**
     * The tab decides, and the resolver agrees with it: whatever the settings, an address takes at
     * most one 301, and where it lands answers (here 404, nothing holds it) without another.
     */
    #[Test]
    public function every_combination_is_at_most_one_redirect_and_never_a_loop(): void
    {
        $keep = [Normalisation::SLASHES => false, Normalisation::LOWERCASE => false, Normalisation::TRAILING => null];
        $all = [Normalisation::HOST => 'bare', Normalisation::HTTPS => true, Normalisation::SLASHES => true, Normalisation::INDEX => true, Normalisation::LOWERCASE => true, Normalisation::TRAILING => 'strip'];

        foreach ([
            // Kept as it is: the resolver does not impose its own spelling against the tab.
            [$keep, 'https://shop.example.com/Some-Page/', 'https://shop.example.com/Some-Page/'],
            [$keep + [Normalisation::HOST => 'bare', Normalisation::HTTPS => true], 'http://www.shop.example.com//Some-Page/', 'https://shop.example.com//Some-Page/'],
            // Everything on, every difference at once.
            [$all, 'http://www.shop.example.com//Some-Page//index.php?x=1', 'https://shop.example.com/some-page?x=1'],
            // The mirror and https on, case and slash left to what the registry always did.
            [[Normalisation::HOST => 'bare', Normalisation::HTTPS => true], 'http://www.shop.example.com/Some-Page/', 'https://shop.example.com/some-page'],
            // «With a slash», saved before it was withdrawn, is read as «keep»: no loop.
            [[Normalisation::TRAILING => 'add', Normalisation::LOWERCASE => false], 'https://shop.example.com/some-page/', 'https://shop.example.com/some-page/'],
            [[Normalisation::TRAILING => 'add', Normalisation::LOWERCASE => false], 'https://shop.example.com/some-page', 'https://shop.example.com/some-page'],
        ] as [$settings, $from, $to]) {
            $this->settings($settings);
            $hops = 0;
            $at = $from;

            while (($response = $this->visit($at))->isRedirect()) {
                $at = (string) $response->headers->get('Location');
                $this->assertLessThan(2, ++$hops, "{$from} is a chain: ".json_encode($settings));
            }

            $this->assertSame($to, $at, json_encode($settings));
            $this->assertSame(404, $response->getStatusCode());
        }
    }

    #[Test]
    public function the_front_controller_in_the_middle_of_an_address_goes_in_the_same_301(): void
    {
        $this->assertFalse($this->visit('https://shop.example.com/index.php/about')->isRedirect());

        $this->settings([Normalisation::INDEX => true]);

        $this->assertSame('https://shop.example.com/about?x=1', $this->visit('https://shop.example.com/index.php/about?x=1')->headers->get('Location'));
        $this->assertSame('https://shop.example.com/', $this->visit('https://shop.example.com/index.php')->headers->get('Location'));
        // A page whose own name starts the same is not the front controller.
        $this->assertFalse($this->visit('https://shop.example.com/index.phpx/about')->isRedirect());
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
     * Straight through the kernel: the test client trims the slash at the end of every address it
     * is given, and the slash at the end is half of what is being tested here.
     */
    private function visit(string $url): Response
    {
        return $this->app->make(Kernel::class)->handle(Request::create($url));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        $this->app->make(Settings::class)->save($values);
    }
}
