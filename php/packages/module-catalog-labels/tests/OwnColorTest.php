<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Dictionaries\Dictionary;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogLabels\Catalog\LabelsColumn;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A label in a colour of its own besides the six tones: `color` = `#rrggbb`, the text on it chosen
 * or worked out by contrast, through every door — the form, an agent — and out on the card and in
 * the panel's list.
 */
final class OwnColorTest extends TestCase
{
    #[Test]
    public function the_text_is_black_or_white_by_the_contrast_of_wcag(): void
    {
        $this->assertSame('#000000', Dictionary::contrastText('#ffffff'));
        $this->assertSame('#ffffff', Dictionary::contrastText('#000000'));
        $this->assertSame('#000000', Dictionary::contrastText('#ffeb3b'));
        $this->assertSame('#ffffff', Dictionary::contrastText('#1a237e'));
        $this->assertSame('#ffffff', Dictionary::contrastText('#d32f2f'));
        // Mid grey: black has 5.3:1 against it, white 3.9:1.
        $this->assertSame('#000000', Dictionary::contrastText('#808080'));
    }

    #[Test]
    public function the_form_keeps_a_colour_of_its_own_and_the_text_on_it(): void
    {
        $label = $this->label('Hit');
        $editor = $this->editor();

        $values = $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$label->id}"), [
            'values' => ['color' => '#C0FFEE'],
        ])->assertOk()->json('data.values');

        $this->assertSame('#c0ffee', $values['color']);
        $this->assertNull($values['text_color']);
        $this->assertSame(['background' => '#c0ffee', 'text' => '#000000'], $label->refresh()->ownColors());

        $values = $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$label->id}"), [
            'values' => ['text_color' => '#336699'],
        ])->assertOk()->json('data.values');

        $this->assertSame('#336699', $values['text_color']);
        $this->assertSame(['background' => '#c0ffee', 'text' => '#336699'], $label->refresh()->ownColors());

        // Back to a tone: the site paints both, and the text colour goes with the own colour.
        $values = $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$label->id}"), [
            'values' => ['color' => 'danger'],
        ])->assertOk()->json('data.values');

        $this->assertSame('danger', $values['color']);
        $this->assertNull($values['text_color']);
        $this->assertNull($label->refresh()->ownColors());
    }

    #[Test]
    public function a_colour_that_is_neither_a_tone_nor_rrggbb_is_refused(): void
    {
        $label = $this->label('Hit');
        $editor = $this->editor();

        foreach (['red', '#c0ffee80', '#abc', 'c0ffee'] as $color) {
            $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$label->id}"), ['values' => ['color' => $color]])
                ->assertStatus(422)->assertJsonValidationErrors('color');
        }

        $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$label->id}"), [
            'values' => ['color' => '#c0ffee', 'text_color' => '#fff'],
        ])->assertStatus(422)->assertJsonValidationErrors('text_color');

        $this->assertSame('neutral', $label->refresh()->color);
    }

    #[Test]
    public function the_model_refuses_what_the_screen_does_not_see(): void
    {
        $this->expectException(ValidationException::class);

        $this->label('Hit', ['color' => '#c0ffee', 'text_color' => 'white']);
    }

    #[Test]
    public function the_card_paints_the_badge_through_variables_and_a_tone_through_its_class(): void
    {
        $own = $this->label('Hit', ['color' => '#1a237e']);
        $chosen = $this->label('Fresh', ['color' => '#ffeb3b', 'text_color' => '#336699']);
        $tone = $this->label('Sale', ['color' => 'danger']);
        $product = $this->product('One', $this->category('laptops'), $own, $chosen, $tone);

        $parts = $this->app->make(StorefrontParts::class);
        $parts->prepare(new Collection([$product]));
        $html = $parts->render('catalog.card.badges', ['product' => $product]);

        $this->assertStringContainsString('class="wx-catalog-badge wx-catalog-badge--custom" style="--webx-label-bg: #1a237e; --webx-label-fg: #ffffff"', $html);
        $this->assertStringContainsString('style="--webx-label-bg: #ffeb3b; --webx-label-fg: #336699"', $html);
        $this->assertStringContainsString('class="wx-catalog-badge wx-catalog-badge--danger"', $html);
        $this->assertStringNotContainsString('wx-catalog-badge--#', $html);
    }

    #[Test]
    public function the_panels_list_of_products_gets_the_colour_and_the_text_on_it(): void
    {
        $own = $this->label('Hit', ['color' => '#1a237e']);
        $tone = $this->label('Sale', ['color' => 'danger']);
        $product = $this->product('One', $this->category('laptops'), $own, $tone);

        $values = (new LabelsColumn)->values(new Collection([$product]));

        $this->assertSame([
            ['id' => $own->id, 'name' => 'Hit', 'code' => 'hit', 'color' => '#1a237e', 'text' => '#ffffff'],
            ['id' => $tone->id, 'name' => 'Sale', 'code' => 'sale', 'color' => 'danger', 'text' => null],
        ], $values[$product->id]);
    }

    #[Test]
    public function an_agent_creates_reads_and_changes_a_label_in_a_colour_of_its_own(): void
    {
        $tools = $this->app->make(ToolRegistry::class);
        $this->assertStringContainsString('#rrggbb', $tools->tool('catalog_labels_create')->tool->description);
        $this->assertStringContainsString('text_color', $tools->tool('catalog_labels_update')->tool->description);

        $created = $this->content($this->agent('catalog_labels_create', [
            'title' => 'Hit',
            'values' => ['color' => '#D32F2F', 'is_visible' => false],
        ]));

        $this->assertSame('#d32f2f', $created['values']['color']);
        $this->assertNull($created['values']['text_color']);
        $this->assertFalse($created['values']['is_visible']);

        $id = (int) $created['label']['id'];
        $read = $this->content($this->agent('catalog_labels_get', ['label' => $id]));
        $this->assertSame('#d32f2f', $read['values']['color']);

        $updated = $this->content($this->agent('catalog_labels_update', ['label' => $id, 'values' => ['text_color' => '#FFEB3B']]));
        $this->assertSame('#ffeb3b', $updated['values']['text_color']);
    }

    #[Test]
    public function an_agents_label_with_a_refused_colour_is_not_made_at_all(): void
    {
        $this->agent('catalog_labels_create', ['title' => 'Hit', 'values' => ['color' => 'crimson']])
            ->assertHasErrors();
        $this->agent('catalog_labels_create', ['title' => 'Hit', 'values' => ['colour' => '#d32f2f']])
            ->assertHasErrors(['catalog_labels_create has no field [colour]']);

        $this->assertSame(0, Label::withTrashed()->count());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
