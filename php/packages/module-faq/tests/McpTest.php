<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The FAQ by its other doors (§4.7): the same list, the same screen, the same order code.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function both_sections_offer_their_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['faq_list', 'faq_get', 'faq_create', 'faq_update', 'faq_delete', 'faq_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('faq')),
        );

        $this->assertSame(
            ['faq_categories_list', 'faq_categories_create', 'faq_categories_update', 'faq_categories_delete', 'faq_categories_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('faq-categories')),
        );

        $this->assertSame(['faq.view', 'faq.manage'], $registry->tool('faq_list')->permissions());
        $this->assertSame(['faq.manage'], $registry->tool('faq_reorder')->permissions());
        $this->assertSame(['faq.categories.manage'], $registry->tool('faq_categories_create')->permissions());

        $this->assertContains('faq://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function categories_without_an_address_promise_no_slug(): void
    {
        $create = $this->app->make(ToolRegistry::class)->tool('faq_categories_create');

        $this->assertArrayNotHasKey('slug', $create->tool->inputSchema['properties'] ?? []);
        $this->assertStringNotContainsString('address', $create->tool->description);

        $this->category('Payment');
        $listed = $this->content($this->agent('faq_categories_list'));

        $this->assertArrayNotHasKey('slug', $listed['categories'][0]);
        $this->assertArrayNotHasKey('paths', $listed['categories'][0]);

        // Visible from birth, and said so: the column's default is read back, not guessed.
        $created = $this->content($this->agent('faq_categories_create', ['title' => ['en' => 'Delivery']]));
        $this->assertTrue($created['category']['is_visible']);

        // Named by its title, having no slug to be named by.
        $this->agent('faq_categories_update', ['category' => 'payment', 'values' => ['is_visible' => false]])->assertOk();
        $this->assertFalse(FaqCategory::query()->firstOrFail()->is_visible);
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['faq.view']);

        $this->agent('faq_list', [], $reader)->assertOk();
        $this->agent('faq_create', ['question' => 'Nope?'], $reader)->assertHasErrors(['[faq.manage]']);
    }

    #[Test]
    public function the_list_says_where_a_reader_sees_each_question(): void
    {
        $this->question('Both?', 'Оба?');
        $this->question('English only?');
        $this->question('Hidden?', 'Скрыт?', published: false);

        $rows = $this->content($this->agent('faq_list'))['questions'];

        $this->assertSame(['en', 'ru'], $rows[0]['visible_in']);
        $this->assertSame(['en'], $rows[1]['visible_in']);
        $this->assertSame([], $rows[2]['visible_in']);
        $this->assertSame(['en', 'ru'], $rows[2]['written_in']);
    }

    #[Test]
    public function create_writes_through_the_screen_and_gets_an_anchor(): void
    {
        $payment = $this->category('Payment');

        $created = $this->content($this->agent('faq_create', [
            'question' => ['en' => 'How do I pay?', 'ru' => 'Как оплатить?'],
            'answer' => '<p>By card.</p>',
            'categories' => [$payment->id],
        ]));

        $this->assertSame('how-do-i-pay', $created['question']['anchor']);
        $this->assertFalse($created['values']['published']);
        $this->assertSame([$payment->id], $created['values']['categories']);
        // A plain string is the default language, not the language of the request.
        $this->assertSame(['en' => '<p>By card.</p>'], $created['values']['answer']);

        $this->agent('faq_update', ['question' => 'how-do-i-pay', 'values' => ['published' => true, 'answer' => ['ru' => '<p>Картой.</p>']]])->assertOk();

        $question = Question::query()->firstOrFail();
        $this->assertTrue($question->visibleIn('ru'));
        $this->assertSame('how-do-i-pay', $question->anchor);
    }

    #[Test]
    public function a_refused_create_leaves_no_question_behind(): void
    {
        // A field of the project refusing its value: the question named in the same call is not
        // left behind without it, as the bare service was on the release of services.
        Screens::extend('faq.form', [['op' => 'add', 'target' => 'project-fields', 'node' => [
            'id' => 'weight', 'type' => 'wx-input-number', 'name' => 'weight', 'label' => 'Weight', 'props' => ['min' => 10],
        ]]]);

        $this->agent('faq_create', ['question' => 'Half written?', 'values' => ['weight' => 5]])->assertHasErrors(['weight']);

        $this->assertSame(0, Question::withTrashed()->count());
    }

    #[Test]
    public function an_unknown_category_is_refused_rather_than_dropped(): void
    {
        $this->agent('faq_create', ['question' => 'Filed?', 'categories' => [999]])->assertHasErrors(['No such category']);

        $this->assertSame(0, Question::withTrashed()->count());
    }

    #[Test]
    public function reordering_a_category_is_its_own_order_and_refuses_a_stranger(): void
    {
        $payment = $this->category('Payment');
        $first = $this->question('First?', categories: [$payment]);
        $second = $this->question('Second?', categories: [$payment]);
        $stranger = $this->question('Stranger?');

        $this->agent('faq_reorder', ['questions' => [$stranger->id], 'category' => $payment->id])->assertHasErrors(['Not in this category']);

        $listed = $this->content($this->agent('faq_reorder', ['questions' => [$second->anchor, $first->id], 'category' => 'Payment']));

        $this->assertSame([$second->id, $first->id], array_column($listed['questions'], 'id'));
        // The whole list keeps its own order.
        $this->assertSame(
            [$first->id, $second->id, $stranger->id],
            array_column($this->content($this->agent('faq_list'))['questions'], 'id'),
        );
    }

    #[Test]
    public function delete_puts_it_in_the_bin_with_its_anchor(): void
    {
        $question = $this->question('Gone?');

        $this->agent('faq_delete', ['question' => $question->anchor, 'dry_run' => true])->assertOk();
        $this->assertFalse($question->refresh()->trashed());

        $this->agent('faq_delete', ['question' => $question->anchor])->assertOk();

        $this->assertTrue($question->refresh()->trashed());
        $this->assertSame([$question->id], array_column($this->content($this->agent('faq_list', ['trashed' => true]))['questions'], 'id'));
    }

    #[Test]
    public function the_catalogue_lists_categories_with_their_questions_and_the_rest_at_the_end(): void
    {
        $payment = $this->category('Payment');
        $hidden = $this->category('Internal', visible: false);
        $both = $this->question('Both?', 'Оба?', categories: [$payment, $hidden]);
        $draft = $this->question('Draft?', published: false, categories: [$payment]);
        $loose = $this->question('Loose?');

        $catalog = ($this->resource('faq://catalog')->handler)();

        $this->assertSame(['Payment', 'Internal'], array_column($catalog['categories'], 'title'));
        $this->assertFalse($catalog['categories'][1]['visible']);
        $this->assertSame([$both->id, $draft->id], array_column($catalog['categories'][0]['questions'], 'id'));
        $this->assertSame([$both->id], array_column($catalog['categories'][1]['questions'], 'id'));
        $this->assertFalse($catalog['categories'][0]['questions'][1]['published']);
        $this->assertSame([], $catalog['categories'][0]['questions'][1]['visible_in']);
        $this->assertSame(['en', 'ru'], $catalog['categories'][0]['questions'][0]['visible_in']);
        $this->assertSame([$loose->id], array_column($catalog['uncategorised'], 'id'));
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
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
