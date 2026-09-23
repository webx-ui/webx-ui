<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Seo\TagIndexing;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * A tag page and the index (§12).
 *
 * This is the file the section was written for. A tag is out of the index by default, and a
 * rule in `seo_urls` for its address opens it — and the reason that cannot be a merge of fields
 * is the second test here: a rule that fills in a title and a description and leaves `robots`
 * empty would let our `noindex` stand underneath it. The editor sees a filled-in rule and a
 * page that is still excluded, and nothing anywhere says why.
 */
final class TagSeoTest extends TestCase
{
    #[Test]
    public function a_tag_page_carries_noindex_by_default(): void
    {
        $this->tag('belts');

        $response = $this->get('/blog/tag/belts');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    #[Test]
    public function a_rule_without_robots_still_opens_the_page(): void
    {
        $this->tag('belts');
        $this->rule('/blog/tag/belts', ['title' => ['en' => 'Belts'], 'description' => ['en' => 'Everything about belts']]);

        $response = $this->get('/blog/tag/belts');

        $response->assertOk();
        $response->assertSee('<title>Belts</title>', false);
        $response->assertSee('<meta name="description" content="Everything about belts">', false);
        // The flag on the tag is still set. What decided was that a rule matches at all.
        $response->assertDontSee('noindex', false);
    }

    #[Test]
    public function turning_the_rule_off_brings_the_noindex_back(): void
    {
        $this->tag('belts');
        $rule = $this->rule('/blog/tag/belts', ['title' => ['en' => 'Belts']]);

        $this->get('/blog/tag/belts')->assertDontSee('noindex', false);

        $rule->update(['is_active' => false]);

        $this->get('/blog/tag/belts')->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    #[Test]
    public function a_rule_for_another_address_changes_nothing(): void
    {
        $this->tag('belts');
        $this->rule('/blog/tag/filters', ['title' => ['en' => 'Filters']]);

        $this->get('/blog/tag/belts')->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    #[Test]
    public function clearing_the_flag_opens_the_page_without_a_rule(): void
    {
        $this->tag('belts', noindex: false);

        $this->get('/blog/tag/belts')->assertOk()->assertDontSee('noindex', false);
    }

    #[Test]
    public function the_panel_is_told_the_same_thing_in_three_states(): void
    {
        $indexing = $this->app->make(TagIndexing::class);

        $open = $this->tag('open-tag', noindex: false);
        $closed = $this->tag('closed-tag');
        $byRule = $this->tag('by-rule');

        $this->rule('/blog/tag/by-rule', ['title' => ['en' => 'By rule']]);

        // Three answers and not two, so that the editor who wrote the rule is not looking at a
        // row that says `noindex` and disagreeing with it.
        $this->assertSame(Tag::INDEXING_OPEN, $indexing->state($open));
        $this->assertSame(Tag::INDEXING_NOINDEX, $indexing->state($closed));
        $this->assertSame(Tag::INDEXING_RULE, $indexing->state($byRule));
    }

    #[Test]
    public function the_tag_names_itself_in_the_title_when_nothing_else_does(): void
    {
        $this->tag('belts');

        $this->get('/blog/tag/belts')->assertSee('<title>Belts</title>', false);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function rule(string $pattern, array $fields): SeoUrl
    {
        /** @var SeoUrl $rule */
        $rule = SeoUrl::query()->create([
            'match_type' => UrlMatcher::EXACT,
            'pattern' => $pattern,
            'is_active' => true,
            ...$fields,
        ]);

        return $rule;
    }
}
