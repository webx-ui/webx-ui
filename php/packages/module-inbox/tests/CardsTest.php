<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * Choices drawn as cards (FORM_STEPS §5): `view: cards`, the columns and a choice's description
 * and picture are the look of a radio or a checkbox — the panel and `inbox_form_save` keep them,
 * the page draws them, and an answer is the same snapshot a list makes. Here without the media
 * library: a picture's key is kept and shows nothing ({@see CardsWithMediaTest} has the library).
 */
final class CardsTest extends TestCase
{
    #[Test]
    public function the_panel_keeps_the_look_of_cards_and_only_where_it_means_something(): void
    {
        $form = $this->form();
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'purpose',
                'type' => 'radio',
                'title' => ['en' => 'Purpose'],
                'options' => [
                    'view' => 'cards',
                    'columns' => 7,
                    'choices' => [
                        ['value' => 'viewing', 'label' => ['en' => 'Viewing'], 'description' => ['en' => ' See the flat ', 'ru' => ''], 'image' => ['path' => 'media/ab/viewing.svg', 'url' => '/storage/media/ab/viewing.svg', 'alt' => 'x']],
                        ['value' => 'question', 'label' => ['en' => 'Question'], 'description' => '', 'image' => null],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.options', [
                'choices' => [
                    ['value' => 'viewing', 'label' => ['en' => 'Viewing'], 'description' => ['en' => 'See the flat'], 'image' => ['path' => 'media/ab/viewing.svg']],
                    ['value' => 'question', 'label' => ['en' => 'Question']],
                ],
                'view' => 'cards',
                'columns' => 4,
            ]);

        $id = (int) $form->fields()->where('name', 'purpose')->value('id');

        // Back to a list: the columns go, what a choice carries stays for the way back.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api("fields/{$id}"), [
                'name' => 'purpose',
                'type' => 'radio',
                'title' => ['en' => 'Purpose'],
                'options' => ['view' => 'list', 'columns' => 2, 'choices' => Field::query()->findOrFail($id)->options['choices']],
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.options.view')
            ->assertJsonMissingPath('data.options.columns')
            ->assertJsonPath('data.options.choices.0.description', ['en' => 'See the flat']);

        // A select has no cards: neither the view nor the words of a card are kept on it.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api("fields/{$id}"), [
                'name' => 'purpose',
                'type' => 'select',
                'title' => ['en' => 'Purpose'],
                'options' => ['view' => 'cards', 'choices' => [['value' => 'viewing', 'label' => 'Viewing', 'description' => 'See the flat']]],
            ])
            ->assertOk()
            ->assertJsonPath('data.options', ['choices' => [['value' => 'viewing', 'label' => 'Viewing']]]);
    }

    #[Test]
    public function the_page_draws_cards_with_their_words_and_a_picture_the_library_cannot_give_is_none(): void
    {
        $this->cardsForm();

        $html = Blade::render('<x-webx-inbox::form slug="contact" />');

        $this->assertStringContainsString('webx-field webx-field--radio webx-field--cards', $html);
        $this->assertStringContainsString('<div class="webx-field__cards webx-field__cards--cols-2">', $html);
        $this->assertSame(2, preg_match_all('/<input type="radio" class="webx-field__check webx-field__card-check"[^>]*name="fields\[purpose\]"[^>]*\srequired/', $html));
        $this->assertStringContainsString('<span class="webx-field__card-title">Viewing</span>', $html);
        $this->assertStringContainsString('<span class="webx-field__card-text">See the flat with an agent</span>', $html);
        // No library on this site: the key is kept, and the card has no picture rather than a broken one.
        $this->assertStringNotContainsString('<img', $html);
        // Ticks as cards keep their own names.
        $this->assertSame(2, substr_count($html, 'name="fields[extras][]"'));
        $this->assertStringContainsString('webx-field--checkbox webx-field--cards', $html);
    }

    #[Test]
    public function the_page_shows_the_card_s_words_in_the_language_being_read(): void
    {
        $this->cardsForm();
        app()->setLocale('ru');

        $html = Blade::render('<x-webx-inbox::form slug="contact" />');

        $this->assertStringContainsString('<span class="webx-field__card-title">Просмотр</span>', $html);
        $this->assertStringContainsString('<span class="webx-field__card-text">Осмотр квартиры с агентом</span>', $html);
    }

    #[Test]
    public function an_answer_from_cards_is_the_snapshot_a_list_makes(): void
    {
        $this->cardsForm();

        $this->postJson($this->intake(), ['fields' => ['purpose' => 'viewing', 'extras' => ['parking']]])->assertOk();

        $submission = Submission::query()->sole();

        $this->assertSame('Viewing', $submission->value('purpose')?->value);
        $this->assertSame(['viewing'], $submission->value('purpose')->payload);
        $this->assertSame(['parking'], $submission->value('extras')?->payload);
        // The description and the picture are the look, not the answer.
        $this->assertStringNotContainsString('See the flat', (string) json_encode($submission->values->toArray()));

        $this->postJson($this->intake(), ['fields' => ['purpose' => 'tender']])->assertJsonValidationErrors(['fields.purpose']);
    }

    #[Test]
    public function a_form_without_cards_prints_none_of_them(): void
    {
        $this->form('contact', [
            ['name' => 'purpose', 'type' => FieldType::Radio, 'options' => ['choices' => [['value' => 'a', 'label' => 'A', 'description' => 'Kept for later']]]],
        ]);

        $html = Blade::render('<x-webx-inbox::form slug="contact" />');

        $this->assertStringNotContainsString('webx-field__card', $html);
        $this->assertStringNotContainsString('Kept for later', $html);
        $this->assertStringContainsString('class="webx-field__choice"', $html);
    }

    #[Test]
    public function inbox_form_save_takes_cards_and_dry_run_writes_nothing(): void
    {
        $fields = [
            ['type' => 'radio', 'name' => 'purpose', 'title' => 'Purpose', 'options' => [
                'view' => 'cards',
                'columns' => 3,
                'choices' => [
                    ['value' => 'viewing', 'label' => 'Viewing', 'description' => ['en' => 'See the flat'], 'image' => ['path' => 'media/ab/viewing.svg']],
                    ['value' => 'question', 'label' => 'Question', 'image' => ['id' => 62]],
                ],
            ]],
        ];

        $dry = $this->content($this->agent('form_save', ['slug' => 'contact', 'title' => 'Contact us', 'dry_run' => true, 'fields' => $fields])->assertOk());
        $this->assertSame(0, Form::query()->count());
        $this->assertSame([['name' => 'purpose', 'would' => 'add']], $dry['fields']);

        $content = $this->content($this->agent('form_save', ['slug' => 'contact', 'title' => 'Contact us', 'fields' => $fields])->assertOk());
        $options = $content['fields'][0]['options'];

        $this->assertSame('cards', $options['view']);
        $this->assertSame(3, $options['columns']);
        $this->assertSame(['path' => 'media/ab/viewing.svg'], $options['choices'][0]['image']);
        // Named by id on a site with no library: there is nothing to find it in.
        $this->assertArrayNotHasKey('image', $options['choices'][1]);

        $got = $this->content($this->agent('form_get', ['form' => 'contact'])->assertOk());
        $this->assertSame(['en' => 'See the flat'], $got['fields'][0]['options']['choices'][0]['description']);
    }

    private function cardsForm(): Form
    {
        return $this->form('contact', [
            ['name' => 'purpose', 'type' => FieldType::Radio, 'is_required' => true, 'options' => [
                'view' => 'cards',
                'columns' => 2,
                'choices' => [
                    ['value' => 'viewing', 'label' => ['en' => 'Viewing', 'ru' => 'Просмотр'], 'description' => ['en' => 'See the flat with an agent', 'ru' => 'Осмотр квартиры с агентом'], 'image' => ['path' => 'media/ab/viewing.svg']],
                    ['value' => 'question', 'label' => ['en' => 'Question', 'ru' => 'Вопрос']],
                ],
            ]],
            ['name' => 'extras', 'type' => FieldType::Checkbox, 'options' => [
                'view' => 'cards',
                'choices' => [
                    ['value' => 'parking', 'label' => 'Parking'],
                    ['value' => 'storage', 'label' => 'Storage'],
                ],
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        return WebxServer::tool(new RegistryTool($this->app->make(ToolRegistry::class)->tool('inbox_'.$tool)), $arguments);
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
