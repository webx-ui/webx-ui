<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlRuleSource;
use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTargets;
use WebxUi\Seo\Tests\Fixtures\MapsEntities;

/**
 * An address saved by an editor remembers the entity behind it (§18.2), so renaming a page loses
 * nothing: the exact rule written for it keeps matching it at its new address.
 */
final class UrlBindingTest extends TestCase
{
    use MapsEntities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapEntities();
    }

    #[Test]
    public function an_exact_rule_on_an_entity_survives_a_new_slug(): void
    {
        $entity = $this->entity('noutbuki-apple');
        $rule = SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/noutbuki-apple', 'title' => ['ru' => 'Ноутбуки Apple']]);

        $this->assertSame($entity->id, $rule->entity_id);
        $this->assertNotNull($rule->entity_type);

        $this->rename($entity, 'apple-laptops');

        $source = app(UrlRuleSource::class);

        $this->assertSame($rule->id, $source->matching('/apple-laptops')?->id);
        $this->assertSame('Ноутбуки Apple', $source->forUrl('/apple-laptops', null, 'ru')?->title);
        // The old address is an alias now: the visitor is sent on, and the rule is not left on it.
        $this->assertNull($source->matching('/noutbuki-apple'));

        // The panel shows the address it matches now, with the saved one beside it.
        $shown = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/seo/urls/'.$rule->id)->assertOk();
        $shown->assertJsonPath('data.current_pattern', '/apple-laptops');
        $shown->assertJsonPath('data.pattern', '/noutbuki-apple');
    }

    #[Test]
    public function an_old_address_from_a_brief_binds_to_the_entity_it_belonged_to(): void
    {
        $entity = $this->entity('old-name');
        $this->rename($entity, 'new-name');

        $rule = SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/old-name', 'title' => ['ru' => 'Найдено']]);

        $this->assertSame($entity->id, $rule->entity_id);
        $this->assertSame($rule->id, app(UrlRuleSource::class)->matching('/new-name')?->id);
    }

    #[Test]
    public function an_address_behind_a_redirect_is_replaced_by_its_target_and_reported(): void
    {
        $entity = $this->entity('sale');
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old-sale', 'target' => '/sale']);

        $response = $this->actingAs($this->editor(), 'cms')->postJson('/api/cms/seo/urls', [
            'match_type' => 'exact',
            'pattern' => '/old-sale',
            'title' => ['ru' => 'Распродажа'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.pattern', '/sale')
            ->assertJsonPath('data.redirected_from', '/old-sale')
            ->assertJsonPath('data.entity_id', $entity->id);
    }

    #[Test]
    public function an_address_on_another_site_is_refused(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/seo/urls', ['match_type' => 'exact', 'pattern' => 'https://other.test/about'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pattern');

        // The site's own host, copied from the browser, is just the path.
        $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/seo/urls', ['match_type' => 'exact', 'pattern' => 'https://example.test/about'])
            ->assertCreated()
            ->assertJsonPath('data.pattern', '/about');

        $this->expectException(ForeignHost::class);
        app(UrlTargets::class)->resolve('//other.test/about');
    }

    #[Test]
    public function a_prefixed_address_binds_in_its_own_language(): void
    {
        $entity = $this->entity(['ru' => 'novosti', 'uk' => 'novyny']);
        $rule = SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/uk/novyny']);

        $this->assertSame($entity->id, $rule->entity_id);

        $this->rename($entity, 'novi', 'uk');

        $this->assertSame($rule->id, app(UrlRuleSource::class)->matching('/uk/novi')?->id);
        $this->assertNull(app(UrlRuleSource::class)->matching('/novosti'));
    }

    #[Test]
    public function masks_and_addresses_with_a_query_stay_unbound(): void
    {
        $this->entity('shoes');

        $mask = SeoUrl::query()->create(['match_type' => 'mask', 'pattern' => '/shoes/*']);
        $query = SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/shoes?page=2']);
        $unknown = SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/nobody-lives-here']);

        $this->assertNull($mask->entity_id);
        $this->assertNull($query->entity_id);
        $this->assertNull($unknown->entity_id);
    }
}
