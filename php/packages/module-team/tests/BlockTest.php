<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Pages\Models\Page;
use WebxUi\Team\Models\Member;

/**
 * The block type the module offers (§5.5): installed once and never over the site's own, drawn in
 * each of its three layouts, "who does this" on a service's page, and a page that goes on
 * answering when the module is gone.
 */
final class BlockTest extends TestCase
{
    #[Test]
    public function the_offered_type_is_installed_and_published(): void
    {
        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('Team', $block->title);
        $this->assertSame('Offered by team', $block->publishedVersion?->comment);
        $this->assertSame(
            ['title', 'team', 'layout', 'columns', 'autoplay', 'hide_text'],
            array_column((array) $block->publishedVersion->schema, 'id'),
        );
        $this->assertSame('wx-collection', $block->publishedVersion?->schema[1]['type'] ?? null);
    }

    #[Test]
    public function a_type_the_site_already_has_by_that_slug_is_never_touched(): void
    {
        $own = Block::query()->create(['slug' => 'team', 'title' => 'Our people']);
        $own->saveVersion(['template' => '<div data-wx-block="team">Ours</div>']);
        $own->publish();
        $version = $own->refresh()->published_version_id;

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['team']])
            ->expectsOutputToContain('left alone')
            ->assertSuccessful();

        $own->refresh();

        $this->assertSame('Our people', $own->title);
        $this->assertSame($version, $own->published_version_id);
    }

    /**
     * The offered type is published only if it draws on its own sample — so each layout is drawn
     * on it here, the way `webx:blocks:offered` would, with people to show and without.
     */
    #[Test]
    #[DataProvider('layouts')]
    public function every_layout_draws_on_the_sample(string $layout, string $expected): void
    {
        $block = $this->installBlock()->load('publishedVersion');
        $sample = (array) $block->publishedVersion?->sample;

        $this->assertStringContainsString('data-team-layout="grid"', $this->render([$this->node($sample)]), 'nobody is on the site yet');

        $this->member('Anna Petrova', attributes: ['job_title' => ['en' => 'Orthodontist']]);
        $this->member('Boris');

        $html = $this->render([$this->node(['layout' => $layout, 'columns' => 2, 'autoplay' => true] + $sample)]);

        $this->assertStringContainsString('data-team-layout="'.$layout.'"', $html);
        $this->assertStringContainsString($expected, $html);
        $this->assertStringContainsString('<h2 class="b-team__title">Our team</h2>', $html);
        $this->assertMatchesRegularExpression('~<article class="b-team__card" id="member-\d+">~', $html);
        $this->assertStringContainsString('<span class="b-team__initials" aria-hidden="true">AP</span>', $html);
        $this->assertStringContainsString('<h3 class="b-team__name">Anna Petrova</h3>', $html);
        $this->assertStringContainsString('<p class="b-team__job">Orthodontist</p>', $html);
        $this->assertStringContainsString('<p class="b-team__text">Anna Petrova works here.</p>', $html);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function layouts(): array
    {
        return [
            'grid' => ['grid', 'style="--team-columns: 2"'],
            'slider' => ['slider', '<div class="b-team__nav" hidden>'],
            'list' => ['list', 'data-team-autoplay="on"'],
        ];
    }

    #[Test]
    public function the_block_holds_its_own_defaults_for_what_the_editor_never_touched(): void
    {
        $this->installBlock();
        $this->member('Anna');

        $html = $this->render([$this->node()]);

        $this->assertStringContainsString('data-team-layout="grid"', $html);
        $this->assertStringContainsString('style="--team-columns: 4"', $html);
        $this->assertStringContainsString('data-team-autoplay="off"', $html);
        $this->assertStringContainsString('Anna works here.', $html, 'an untouched switch — off — shows the text, and an untouched collection is everybody');
        $this->assertStringNotContainsString('b-team__nav', $html, 'the arrows are the slider\'s');

        $this->assertStringNotContainsString('Anna works here.', $this->render([$this->node(['hide_text' => true])]));
    }

    #[Test]
    public function the_text_keeps_its_lines_and_a_photo_stands_where_the_initials_would(): void
    {
        $this->installBlock();
        $this->picture();
        $this->member('Anna', attributes: [
            'text' => ['en' => "First line.\n<b>Second</b> line."],
            'photo' => ['path' => 'media/ab/cd/anna.jpg'],
        ]);

        $html = $this->render([$this->node()]);

        $this->assertStringContainsString("First line.<br />\n&lt;b&gt;Second&lt;/b&gt; line.", $html);
        $this->assertMatchesRegularExpression('~<img class="b-team__photo" src="[^"]+" alt="Anna" width="400" height="400" loading="lazy">~', $html);
        $this->assertStringNotContainsString('b-team__initials', $html);
    }

    #[Test]
    public function a_social_link_is_an_icon_named_for_the_reader_and_an_unknown_one_is_its_name(): void
    {
        $this->installBlock();

        $socials = [];

        foreach (['facebook', 'instagram', 'linkedin', 'x', 'telegram', 'youtube', 'tiktok', 'vk'] as $network) {
            $socials[] = ['network' => $network, 'url' => "https://{$network}.example/anna"];
        }

        $this->member('Anna Petrova', attributes: ['socials' => $socials]);
        config()->set('webx-team.networks', [...(array) config('webx-team.networks'), 'vk' => 'VK']);

        $html = $this->render([$this->node()]);

        $this->assertStringContainsString(
            '<a class="b-team__social" href="https://instagram.example/anna" rel="noopener" target="_blank" aria-label="Instagram: Anna Petrova">',
            $html,
        );
        $this->assertSame(7, substr_count($html, '<svg class="b-team__icon"'), 'an icon for each of the seven networks of the default config');
        $this->assertStringContainsString('<span class="b-team__network">VK</span>', $html, 'a network the site added is printed as its name');
    }

    #[Test]
    public function on_a_services_page_it_shows_who_provides_that_service(): void
    {
        $this->installBlock();

        $implants = $this->service('implants');
        $crowns = $this->service('crowns');
        $this->member('Anna')->syncRelated(Member::SERVICES, 'service', [$implants->id]);
        $this->member('Boris')->syncRelated(Member::SERVICES, 'service', [$crowns->id]);

        foreach ([$implants, $crowns] as $service) {
            $service->blocks = [[
                'key' => 'k1',
                'type' => 'team',
                'values' => [
                    'layout' => 'list',
                    'team' => ['related' => ['type' => 'service', 'ids' => [], 'current' => true]],
                ],
            ]];
            $service->save();
            $service->publish();
        }

        $html = (string) $this->get('/services/implants')->assertOk()->getContent();

        $this->assertStringContainsString('<h3 class="b-team__name">Anna</h3>', $html);
        $this->assertStringNotContainsString('<h3 class="b-team__name">Boris</h3>', $html);
        $this->assertStringContainsString('<ul class="b-team__services">', $html);
        $this->assertStringContainsString('<a href="http://localhost/services/implants">Implants</a>', $html);
    }

    #[Test]
    public function the_page_carries_no_person_markup(): void
    {
        $this->installBlock();
        $this->member('Anna');

        $this->get($this->page([$this->node()]))
            ->assertOk()
            ->assertSee('Anna works here.', false)
            ->assertDontSee('"Person"', false);
    }

    #[Test]
    public function without_the_module_the_block_is_empty_and_the_page_still_answers(): void
    {
        $this->installBlock();
        $this->member('Anna');
        $url = $this->page([$this->node()]);

        $this->get($url)->assertOk()->assertSee('Anna works here.', false);

        $this->app->make(CollectionSources::class)->forget();

        $this->get($url)
            ->assertOk()
            ->assertSee('<ul class="b-team__track">', false)
            ->assertDontSee('Anna works here.', false);
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     */
    private function render(array $blocks): string
    {
        return (string) $this->app->make(Renderer::class)->render($blocks);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function node(array $values = []): array
    {
        static $count = 0;
        $count++;

        return ['key' => "k{$count}", 'type' => 'team', 'values' => $values];
    }

    /**
     * A published page with these blocks, at its address.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(array $blocks): string
    {
        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => 'Team', 'slug' => 'team']);
        $page->appendTo($home);
        $page->blocks = $blocks;
        $page->save();
        $page->publish();

        return '/team';
    }
}
