<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SeoData;
use WebxUi\Settings\Settings;

final class ResolverTest extends TestCase
{
    #[Test]
    public function a_field_a_source_leaves_empty_comes_from_the_one_below_it(): void
    {
        // The decision this module is built around. Merging whole objects instead would mean
        // that the first rule an editor writes wipes out half the page's markup.
        $upper = SeoData::make(['title' => 'Shoes']);
        $lower = SeoData::make(['title' => 'Catalogue', 'description' => 'Everything we sell']);

        $merged = $upper->mergeOver($lower);

        $this->assertSame('Shoes', $merged->title);
        $this->assertSame('Everything we sell', $merged->description);
    }

    #[Test]
    public function structured_data_adds_up_instead_of_replacing(): void
    {
        $upper = SeoData::make(['jsonLd' => ['@type' => 'FAQPage']]);
        $lower = SeoData::make(['jsonLd' => [['@type' => 'Organization']]]);

        $this->assertSame(
            ['FAQPage', 'Organization'],
            array_column($upper->mergeOver($lower)->jsonLd, '@type'),
        );
    }

    #[Test]
    public function a_rule_written_for_an_address_wins_over_the_defaults(): void
    {
        $this->settings(['seo.org-name' => ['ru' => 'Acme'], 'general.project-name' => ['ru' => 'Магазин']]);

        SeoUrl::query()->create([
            'match_type' => 'exact',
            'pattern' => '/catalog',
            'title' => ['ru' => 'Каталог'],
        ]);

        $seo = $this->seo()->for('/catalog', null, 'ru');

        $this->assertSame('Каталог', $seo->title);
        // Filled in by the source below, which the rule said nothing about.
        $this->assertSame('Магазин', $seo->og['site_name'] ?? null);
        $this->assertSame(['Organization', 'WebSite'], array_column($seo->jsonLd, '@type'));
    }

    #[Test]
    public function the_more_specific_rule_wins_and_priority_breaks_the_tie(): void
    {
        SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/catalog/**', 'title' => ['ru' => 'Раздел']]);
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/catalog/shoes', 'title' => ['ru' => 'Обувь']]);

        $this->assertSame('Обувь', $this->seo()->for('/catalog/shoes', null, 'ru')->title);

        SeoUrl::query()->create([
            'match_type' => 'mask',
            'pattern' => '/catalog/*',
            'priority' => 10,
            'title' => ['ru' => 'Важнее'],
        ]);

        $this->assertSame('Важнее', $this->seo()->for('/catalog/hats', null, 'ru')->title);
    }

    #[Test]
    public function text_comes_back_in_the_language_that_was_asked_for(): void
    {
        SeoUrl::query()->create([
            'match_type' => 'exact',
            'pattern' => '/about',
            'title' => ['ru' => 'О нас', 'uk' => 'Про нас'],
        ]);

        $this->assertSame('О нас', $this->seo()->for('/about', null, 'ru')->title);
        $this->assertSame('Про нас', $this->seo()->for('/about', null, 'uk')->title);
    }

    #[Test]
    public function the_title_template_is_applied_and_an_empty_placeholder_takes_its_separator(): void
    {
        $this->settings([
            'seo.title-template' => '{title} — {site}',
            'general.project-name' => ['ru' => 'Магазин'],
        ]);

        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/about', 'title' => ['ru' => 'О нас']]);

        $this->assertSame('О нас — Магазин', $this->seo()->for('/about', null, 'ru')->title);

        $this->settings(['general.project-name' => ['ru' => '']]);
        $this->assertSame('О нас', $this->seo()->for('/about', null, 'ru')->title);
    }

    #[Test]
    public function a_rule_that_will_not_compile_is_skipped_rather_than_thrown(): void
    {
        SeoUrl::query()->create(['match_type' => 'regex', 'pattern' => '#^/catalog/(#', 'title' => ['ru' => 'Битое']]);
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/catalog/shoes', 'title' => ['ru' => 'Обувь']]);

        $this->assertSame('Обувь', $this->seo()->for('/catalog/shoes', null, 'ru')->title);
    }

    #[Test]
    public function switching_a_rule_off_stops_it_at_once(): void
    {
        $rule = SeoUrl::query()->create([
            'match_type' => 'exact',
            'pattern' => '/about',
            'title' => ['ru' => 'О нас'],
        ]);

        $this->assertSame('О нас', $this->seo()->for('/about', null, 'ru')->title);

        $rule->update(['is_active' => false]);

        $this->assertNull($this->seo()->for('/about', null, 'ru')->title);
    }

    #[Test]
    public function the_chain_says_where_every_part_came_from(): void
    {
        $this->settings(['seo.org-name' => ['ru' => 'Acme']]);
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/about', 'title' => ['ru' => 'О нас']]);

        $chain = $this->seo()->chain('/about', null, 'ru');

        $this->assertSame(['UrlRuleSource', 'DefaultsSource'], array_column($chain, 'source'));
        $this->assertSame('О нас', $chain[0]['data']['title']);
    }

    private function seo(): Seo
    {
        // A fresh resolver every time: the compiled list is a singleton, and these tests write
        // rules between reads on purpose.
        return app(Seo::class);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        app(Settings::class)->save($values);
    }
}
