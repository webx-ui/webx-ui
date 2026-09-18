<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Blocks\Models\BlockBundle;
use WebxUi\Blocks\Preview\PreviewToken;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Blocks\Tests\Fixtures\RoutedPage;
use WebxUi\Routing\Reserved;

final class PreviewTest extends TestCase
{
    #[Test]
    public function the_link_opens_the_draft_of_an_unpublished_page_and_nothing_else_does(): void
    {
        $this->publish('hero', '<h1>{{ $title }}</h1>');

        $page = RoutedPage::query()->create(['slug' => 'about', 'title' => 'About']);
        $page->saveDraft(['title' => 'About us', 'blocks' => [$this->node('hero', ['title' => 'Draft hero'], 'k1')]]);

        $this->assertFalse($page->isPublished());

        // The real address is a 404 until the page is published: the handler's rule, not ours.
        $this->get('/about')->assertNotFound();

        $this->get('/_preview/page/'.$page->getKey())->assertForbidden();
        $this->get('/_preview/page/'.$page->getKey().'?token=nonsense')->assertForbidden();

        $url = Preview::url($page, adminId: 7);
        $this->assertStringContainsString('/_preview/page/'.$page->getKey().'?token=', $url);

        $response = $this->get($url);
        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('<title>About us</title>', false);
        $response->assertSee('<!--wx:k1--><h1>Draft hero</h1><!--/wx:k1-->', false);
        $response->assertSee('data-preview="7"', false);

        // The `Resolution` is the page's own: its canonical address, its entity.
        $response->assertSee('data-path="about"', false);
        $response->assertSee('data-entity="'.$page->getKey().'"', false);

        // And the row is untouched: the preview rendered a copy.
        $stored = RoutedPage::query()->findOrFail($page->getKey());
        $this->assertSame('About', $stored->title);
        $this->assertNull($stored->blocks);
    }

    #[Test]
    public function a_token_opens_one_page_for_a_while(): void
    {
        $one = RoutedPage::query()->create(['slug' => 'one', 'title' => 'One']);
        $two = RoutedPage::query()->create(['slug' => 'two', 'title' => 'Two']);

        $token = (string) parse_url(Preview::url($one), PHP_URL_QUERY);
        parse_str($token, $query);
        $token = (string) $query['token'];

        $this->get('/_preview/page/'.$two->getKey().'?token='.$token)->assertForbidden();
        $this->get('/_preview/page/'.$one->getKey().'?token='.$token)->assertOk();

        $signer = $this->app->make(PreviewToken::class);
        $this->assertNotNull($signer->verify($token, 'page', (string) $one->getKey()));
        $this->assertNull($signer->verify($token, 'page', (string) $one->getKey(), now: time() + 61 * 60), 'an hour is the default');
        $this->assertNull($signer->verify($token, 'article', (string) $one->getKey()));

        // A tampered payload fails the signature, whatever it says.
        [$payload] = explode('.', $token, 2);
        $forged = rtrim(strtr(base64_encode(json_encode(['page', (string) $two->getKey(), null, PHP_INT_MAX])), '+/', '-_'), '=');
        $this->assertNull($signer->verify($forged.'.'.substr($token, strlen($payload) + 1), 'page', (string) $two->getKey()));
    }

    #[Test]
    public function the_draft_of_a_block_type_is_seen_under_the_token_only(): void
    {
        $hero = $this->publish('hero', '<h1 class="v1">{{ $title }}</h1>', [], ['styles' => '.v1{}']);
        $hero->saveVersion(['template' => '<h1 class="v2">{{ $title }}</h1>', 'styles' => '.v2{}']);

        $page = RoutedPage::query()->create(['slug' => 'about', 'title' => 'About']);
        $page->saveDraft(['blocks' => [$this->node('hero', ['title' => 'Hi'])]]);
        $page->publish();

        // The live address prints the published version, and its bundle.
        $live = $this->get('/about');
        $live->assertOk();
        $live->assertSee('<h1 class="v1">Hi</h1>', false);
        $live->assertDontSee('<!--wx:', false);
        $live->assertHeaderMissing('X-Robots-Tag');

        // The preview prints the draft version — and a different bundle, because the hash
        // names the version.
        $preview = $this->get(Preview::url($page));
        $preview->assertOk();
        $preview->assertSee('<h1 class="v2">Hi</h1>', false);

        preg_match_all('#/blocks/([a-f0-9]{16})\.css#', $live->getContent().$preview->getContent(), $hashes);
        $this->assertCount(2, $hashes[1]);
        $this->assertNotSame($hashes[1][0], $hashes[1][1]);
        $this->assertStringContainsString('.v1{}', BlockBundle::query()->findOrFail($hashes[1][0])->css);
        $this->assertStringContainsString('.v2{}', BlockBundle::query()->findOrFail($hashes[1][1])->css);

        // A request after a preview is not a preview: the renderer forgets between responses.
        $this->get('/about')->assertSee('<h1 class="v1">Hi</h1>', false);
    }

    #[Test]
    public function a_page_without_an_address_yet_still_previews(): void
    {
        $this->publish('text', '<p>{{ $body }}</p>');

        // A type without `HasUrl`: the registry holds no row, and there is no formatter to ask.
        $page = Page::query()->create(['title' => null]);
        $page->saveDraft(['title' => 'Untitled', 'blocks' => [$this->node('text', ['body' => 'First words'])]]);

        $response = $this->get(Preview::url($page));
        $response->assertOk();
        $this->assertStringContainsString('/_preview/note/', Preview::url($page));
        $response->assertSee('<p>First words</p>', false);
        $response->assertSee('data-path=""', false);
        $response->assertSee('data-entity="'.$page->getKey().'"', false);
    }

    #[Test]
    public function the_preview_answers_in_the_language_the_site_answers_in(): void
    {
        // The site's language is decided by a middleware on the route that answers for a page,
        // and a preview that runs without it shows the application's default instead: an
        // editor was being shown their Russian page in English, and everything localized in it
        // — the words of a block, the fields of a form — came out in the wrong language.
        config(['webx-localization.locales' => [
            ['code' => 'ru', 'default' => true],
            ['code' => 'en'],
        ]]);
        app()->setLocale('en');

        $this->publish('greet', '<p>{{ $title }}</p>', [], [
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'localized' => true]],
        ]);

        $page = RoutedPage::query()->create(['slug' => 'about', 'title' => 'About']);
        $page->saveDraft(['title' => 'About', 'blocks' => [
            $this->node('greet', ['title' => ['en' => 'Hello', 'ru' => 'Привет']], 'k1'),
        ]]);

        $this->get(Preview::url($page))->assertOk()->assertSee('<p>Привет</p>', false);
    }

    #[Test]
    public function an_unknown_type_or_id_is_a_404_even_with_a_token(): void
    {
        $page = RoutedPage::query()->create(['slug' => 'about', 'title' => 'About']);
        $signer = $this->app->make(PreviewToken::class);

        $this->get('/_preview/page/999?token='.$signer->make('page', '999', null, time() + 60))->assertNotFound();
        $this->get('/_preview/nothing/'.$page->getKey().'?token='.$signer->make('nothing', (string) $page->getKey(), null, time() + 60))->assertNotFound();
    }

    #[Test]
    public function the_preview_prefix_is_closed_to_the_registry_and_the_route_survives_caching(): void
    {
        $reserved = $this->app->make(Reserved::class);

        $this->assertTrue($reserved->taken('_preview'));
        $this->assertTrue($reserved->taken('_preview/page/1'));
        $this->assertFalse($reserved->taken('preview'));

        // A closure in the route would refuse `route:cache`, which every deploy runs.
        $route = Route::getRoutes()->getByName('webx.blocks.preview');
        $this->assertNotNull($route);
        (clone $route)->prepareForSerialization();
        $this->assertSame(['web', 'webx.locale'], $route->middleware());
    }
}
