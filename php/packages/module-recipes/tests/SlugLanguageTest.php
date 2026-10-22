<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Recipes\Models\Recipe;

/**
 * A new recipe's address out of its title is transliterated by the title's language: «Хіт» is
 * `khit` in Ukrainian and `hit` in Russian — never `xit`, the English reading of Cyrillic.
 */
final class SlugLanguageTest extends TestCase
{
    #[Test]
    public function a_recipe_takes_its_address_from_the_title_in_the_titles_own_language(): void
    {
        Locale::fromCode('uk', ['is_default' => true, 'sort' => 0])->save();
        Locale::fromCode('ru', ['sort' => 1])->save();
        $this->app->make(Locales::class)->forget();
        $editor = $this->editor();

        // The panel names a new one in the site's main language.
        $this->actingAs($editor, 'cms')->postJson('/api/cms/recipes', ['title' => 'Хіт'])->assertCreated();

        $this->assertSame('khit', Recipe::query()->latest('id')->firstOrFail()->getTranslation('slug', 'uk'));

        // An agent writes every language at once, and each is transliterated its own way.
        WebxServer::actingAs($editor, 'cms')
            ->tool(new RegistryTool($this->app->make(ToolRegistry::class)->tool('recipes_create')), [
                'title' => ['uk' => 'Хіт сезону', 'ru' => 'Хит сезона'],
            ])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $this->assertSame(['uk' => 'khit-sezonu', 'ru' => 'hit-sezona'], $json->etc()->toArray()['values']['slug']);
            });
    }
}
