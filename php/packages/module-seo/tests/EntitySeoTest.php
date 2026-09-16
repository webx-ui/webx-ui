<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Tests\Fixtures\SeoEntity;
use WebxUi\Settings\Settings;

/**
 * What an entity says about itself, and where it stands between the rule written for an address
 * and the defaults written for the site.
 *
 * The order is the whole point of the priority, and it is the one thing that cannot be read off
 * a single source: three of them have to answer the same page at once.
 */
final class EntitySeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seo_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });
    }

    #[Test]
    public function what_the_entity_says_beats_the_defaults(): void
    {
        app(Settings::class)->save([
            'general.project-name' => ['ru' => 'Акме'],
            'seo.org-name' => ['ru' => 'Акме'],
        ]);

        $entity = $this->entity(['title' => ['ru' => 'О компании'], 'description' => ['ru' => 'Кто мы']]);

        $data = app(Seo::class)->for('/about', $entity, 'ru');

        $this->assertSame('О компании', $data->title);
        $this->assertSame('Кто мы', $data->description);
        // And the defaults still reach the page through the fields the entity left alone: the
        // merge is per field, so a card with two lines in it does not take the site's
        // structured data away.
        $this->assertSame('Акме', $data->og['site_name'] ?? null);
        $this->assertNotSame([], $data->jsonLd);
    }

    #[Test]
    public function a_rule_for_the_address_beats_what_the_entity_says(): void
    {
        // The rule was written *because* a page was wrong, which is the whole argument for it
        // standing above the entity's own fields.
        $this->rule('/about', ['title' => ['ru' => 'Правило']]);

        $entity = $this->entity(['title' => ['ru' => 'Сущность'], 'description' => ['ru' => 'Кто мы']]);

        $data = app(Seo::class)->for('/about', $entity, 'ru');

        $this->assertSame('Правило', $data->title);
        // Only the field the rule filled in. A rule that says one thing must not silence
        // everything the page knows about itself.
        $this->assertSame('Кто мы', $data->description);
    }

    #[Test]
    public function an_entity_nobody_wrote_anything_for_does_not_take_part(): void
    {
        app(Settings::class)->save(['seo.title-template' => ['ru' => '{title} — {site}']]);

        $this->rule('/about', ['title' => ['ru' => 'Правило']]);

        $entity = $this->entity([]);

        $this->assertSame([], $entity->seoValue());
        $this->assertNull($entity->seoData('ru'));
        $this->assertSame('Правило', app(Seo::class)->for('/about', $entity, 'ru')->title);
    }

    #[Test]
    public function the_card_is_stored_as_the_field_type_says_and_read_back_whole(): void
    {
        $entity = $this->entity([]);

        $stored = app(FieldTypes::class)->get('wx-seo')?->store([
            'title' => ['ru' => '  О компании  ', 'uk' => '', 'de' => 'Über uns'],
            'canonical' => '',
            'robots' => 'noindex, nofollow',
            'json_ld' => '{"@type":"FAQPage"}',
        ], ['type' => 'wx-seo']);

        $entity->saveSeo($stored);

        $value = $entity->seoValue();

        // Trimmed, blanks dropped, and a language the site does not publish in is not a
        // language — it would otherwise sit in the column forever.
        $this->assertSame(['ru' => 'О компании'], $value['title']);
        $this->assertArrayNotHasKey('canonical', $value);
        $this->assertSame('noindex, nofollow', $value['robots']);
        $this->assertSame([['@type' => 'FAQPage']], $value['json_ld']);
    }

    #[Test]
    public function emptying_the_card_removes_the_row_rather_than_keeping_a_blank_one(): void
    {
        $entity = $this->entity(['title' => ['ru' => 'О компании']]);

        $this->assertSame(1, SeoMeta::query()->count());

        $entity->saveSeo([]);

        // "Nothing written here" and "everything written here is blank" are the same thing to
        // an editor and different things to the merge — the defaults reach the page only for
        // the first one.
        $this->assertSame(0, SeoMeta::query()->count());
        $this->assertNull($entity->seoData('ru'));
    }

    #[Test]
    public function the_card_goes_when_the_entity_really_goes(): void
    {
        $entity = $this->entity(['title' => ['ru' => 'О компании']]);

        $entity->delete();

        $this->assertSame(0, SeoMeta::query()->count());
    }

    #[Test]
    public function a_screen_can_carry_the_card_and_what_it_holds_is_checked(): void
    {
        app(ScreenRegistry::class)->register('test.form', [
            ['id' => 'seo', 'type' => 'wx-seo', 'name' => 'seo'],
        ]);

        $values = app(ScreenValues::class);

        $stored = $values->validate('test.form', ['seo' => ['title' => ['ru' => 'О компании']]]);
        $this->assertSame(['ru' => 'О компании'], $stored['seo']['title']);

        $this->expectException(ValidationException::class);
        $values->validate('test.form', ['seo' => ['json_ld' => 'not json at all']]);
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function entity(array $seo): SeoEntity
    {
        $entity = SeoEntity::query()->create(['name' => 'About']);

        if ($seo !== []) {
            $entity->saveSeo($seo);
        }

        return $entity;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function rule(string $pattern, array $fields): void
    {
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => $pattern] + $fields);
    }
}
