<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Mcp\Tool;
use WebxUi\Seo\Faq\FaqImport;
use WebxUi\Seo\Faq\FaqSpreadsheet;
use WebxUi\Seo\Faq\PageFaq;
use WebxUi\Seo\Models\SeoFaqItem;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\SeoModule;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Tests\Fixtures\SeoEntity;

/**
 * Page FAQs (§18.5): questions on an exact rule, in the `FAQPage` of the page whether or not
 * the template prints them, one `FAQPage` beside `module-faq`, and a brief that comes in as a
 * table. The site speaks Russian by default and Ukrainian under `/uk`.
 */
final class FaqTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-seo.faq.enabled', true);
    }

    #[Test]
    public function the_rule_form_saves_the_questions_and_reads_them_back(): void
    {
        $id = $this->actingAs($this->editor(), 'cms')->postJson('/api/cms/seo/urls', [
            'match_type' => 'exact',
            'pattern' => '/delivery',
            'faq' => [
                ['question' => ['ru' => 'Сколько ждать?'], 'answer' => ['ru' => '<p>Два дня.</p>']],
                ['question' => ['ru' => 'Это бесплатно?'], 'answer' => ['ru' => 'Да.']],
                // Added and left: dropped, not refused.
                ['question' => ['ru' => ''], 'answer' => ['ru' => '']],
            ],
        ])->assertCreated()->assertJsonPath('data.faq_count', 2)->json('data.id');

        $data = $this->actingAs($this->editor(), 'cms')->getJson("/api/cms/seo/urls/{$id}")->assertOk()->json('data');

        $this->assertSame('Сколько ждать?', $data['faq'][0]['question']['ru']);
        // Plain text is escaped into a paragraph, so it cannot open a tag on the page.
        $this->assertSame('<p>Да.</p>', $data['faq'][1]['answer']['ru']);

        // The list carries the count and filters by it.
        SeoUrl::query()->create(['match_type' => 'exact', 'pattern' => '/plain']);
        $list = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/seo/urls?has_faq=1')->assertOk()->json('data');
        $this->assertSame(['/delivery'], array_column($list, 'pattern'));
        $this->assertSame(2, $list[0]['faq_count']);

        // A save that does not send the questions leaves them where they are.
        $this->actingAs($this->editor(), 'cms')->putJson("/api/cms/seo/urls/{$id}", [
            'match_type' => 'exact',
            'pattern' => '/delivery',
            'title' => ['ru' => 'Доставка'],
        ])->assertOk()->assertJsonPath('data.faq_count', 2);

        // Half a question is an error under its row.
        $this->actingAs($this->editor(), 'cms')->putJson("/api/cms/seo/urls/{$id}", [
            'match_type' => 'exact',
            'pattern' => '/delivery',
            'faq' => [['question' => ['ru' => 'Без ответа'], 'answer' => ['ru' => '']]],
        ])->assertStatus(422)->assertJsonValidationErrors(['faq.0.answer']);
    }

    #[Test]
    public function only_an_exact_rule_has_a_faq(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson('/api/cms/seo/urls', [
            'match_type' => 'mask',
            'pattern' => '/catalog/*',
            'faq' => [['question' => ['ru' => 'Вопрос?'], 'answer' => ['ru' => 'Ответ.']]],
        ])->assertStatus(422)->assertJsonValidationErrors(['match_type']);

        $rule = $this->ruleWithFaq('/delivery', [['Вопрос?', 'Ответ.']]);

        $this->actingAs($this->editor(), 'cms')->putJson("/api/cms/seo/urls/{$rule->id}", [
            'match_type' => 'mask',
            'pattern' => '/delivery/*',
        ])->assertStatus(422)->assertJsonValidationErrors(['match_type']);

        // The same refusal for an agent.
        $set = $this->tool('urls_set');
        $this->assertFalse($set(['id' => $rule->id, 'match_type' => 'mask', 'pattern' => '/delivery/*'])['ok']);

        // Emptied in the same save, the rule may change its kind.
        $this->actingAs($this->editor(), 'cms')->putJson("/api/cms/seo/urls/{$rule->id}", [
            'match_type' => 'mask',
            'pattern' => '/delivery/*',
            'faq' => [],
        ])->assertOk();

        $this->assertSame(0, SeoFaqItem::query()->count());
    }

    #[Test]
    public function the_faq_page_is_in_the_head_without_the_component_in_the_template(): void
    {
        $this->ruleWithFaq('/delivery', [['Сколько ждать?', '<p>Два <b>дня</b>.</p><p>Иногда три.</p>']]);

        $blocks = $this->blocksOf('/delivery');
        $faq = $this->faqPages($blocks);

        $this->assertCount(1, $faq);
        $this->assertSame('Сколько ждать?', $faq[0]['mainEntity'][0]['name']);
        // Without tags, as `module-faq` says it.
        $this->assertSame('Два дня. Иногда три.', $faq[0]['mainEntity'][0]['acceptedAnswer']['text']);

        // A question with no words in this language is not on the Ukrainian page.
        $this->assertSame([], $this->faqPages($this->blocksOf('/delivery', 'uk')));
    }

    #[Test]
    public function one_faq_page_with_module_faq_beside_it_and_a_repeated_question_once(): void
    {
        $this->ruleWithFaq('/delivery', [['Сколько ждать?', 'Два дня.'], ['Можно забрать самому?', 'Да.']]);

        // What `module-faq` puts for the questions a block on the page showed.
        app(Seo::class)->put(PageFaq::KEY, [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                ['@type' => 'Question', 'name' => 'Сколько  ждать?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Другое.']],
                ['@type' => 'Question', 'name' => 'Как оплатить?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Картой.']],
            ],
        ]);

        $faq = $this->faqPages($this->blocksOf('/delivery'));

        $this->assertCount(1, $faq);
        $this->assertSame(
            ['Сколько ждать?', 'Можно забрать самому?', 'Как оплатить?'],
            array_column($faq[0]['mainEntity'], 'name'),
        );
        $this->assertSame('Два дня.', $faq[0]['mainEntity'][0]['acceptedAnswer']['text']);
    }

    #[Test]
    public function a_rule_with_only_a_faq_does_not_wipe_the_title_of_the_entity(): void
    {
        Schema::create('seo_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        $entity = SeoEntity::query()->create(['name' => 'About']);
        $entity->saveSeo(['title' => ['ru' => 'О компании']]);

        $this->ruleWithFaq('/about', [['Кто вы?', 'Мы.']]);

        $data = app(Seo::class)->for('/about', $entity, 'ru');

        $this->assertSame('О компании', $data->title);
        $this->assertCount(1, $this->faqPages($data->jsonLd));
    }

    #[Test]
    public function the_component_prints_the_questions_as_details_and_nothing_without_them(): void
    {
        $this->ruleWithFaq('/delivery', [['Сколько ждать?', '<p>Два дня.</p>']]);

        $html = $this->render('/delivery');

        $this->assertStringContainsString('<details class="webx-faq__item">', $html);
        $this->assertStringContainsString('<summary class="webx-faq__question">Сколько ждать?</summary>', $html);
        $this->assertStringContainsString('<p>Два дня.</p>', $html);
        $this->assertStringContainsString('Частые вопросы', $html);

        $this->assertSame('', trim($this->render('/elsewhere')));
    }

    #[Test]
    public function quotes_and_angle_brackets_in_a_question_are_escaped_in_the_markup_and_the_json_ld(): void
    {
        $this->ruleWithFaq('/delivery', [['Что значит "<b>" и a < b?', 'Если a<b, то b>a.']]);

        $html = $this->render('/delivery');

        $this->assertStringContainsString('Что значит &quot;&lt;b&gt;&quot; и a &lt; b?', $html);
        $this->assertStringNotContainsString('<b>', $html);
        // A plain answer written by an import or an agent is text, not a tag.
        $this->assertStringContainsString('Если a&lt;b, то b&gt;a.', $html);

        $head = (string) app(Seo::class)->head(null, '/delivery', 'ru');

        // `<` as its unicode escape: a question cannot close the script tag it is printed in.
        $this->assertStringContainsString('\\'.'u003Cb\\'.'u003E', $head);
        $this->assertStringNotContainsString('"<b>"', $head);
        $this->assertSame(1, substr_count($head, '<script type="application/ld+json">{"@context":"https://schema.org","@type":"FAQPage"'));
    }

    #[Test]
    public function the_import_creates_a_rule_with_empty_meta_and_previews_first(): void
    {
        $csv = "address;question;answer\n/delivery;Сколько ждать?;Два дня.\n/delivery;Это бесплатно?;Да.\n/uk/pro-nas;Хто ви?;Ми.\n";

        $preview = $this->import($csv)->assertOk()->json('data');

        $this->assertFalse($preview['applied']);
        $this->assertSame(2, $preview['addresses']);
        $this->assertSame(3, $preview['questions']);
        $this->assertSame(2, $preview['created']);
        $this->assertSame(0, SeoUrl::query()->count());

        $this->import($csv, dryRun: false)->assertOk()->assertJsonPath('data.applied', true);

        $rule = SeoUrl::query()->where('pattern', '/delivery')->firstOrFail();
        $this->assertSame(UrlMatcher::EXACT, $rule->match_type);
        $this->assertNull($rule->title);
        $this->assertSame([], $rule->getTranslations('title'));
        $this->assertSame(['Сколько ждать?', 'Это бесплатно?'], $rule->faqItems->map(static fn (SeoFaqItem $item): string => $item->questionText('ru'))->all());

        // The address's own language: Ukrainian under `/uk`.
        $uk = SeoUrl::query()->where('pattern', '/uk/pro-nas')->firstOrFail();
        $this->assertSame('Хто ви?', $uk->faqItems->first()?->questionText('uk'));
    }

    #[Test]
    public function replace_swaps_an_addresss_questions_and_append_adds_to_them(): void
    {
        $this->ruleWithFaq('/delivery', [['Старый?', 'Да.']]);

        $this->import("address,question,answer\n/delivery,Новый?,Да.\n", dryRun: false)
            ->assertJsonPath('data.replaced', 1);
        $this->assertSame(['Новый?'], $this->questionsOf('/delivery'));

        $data = $this->import("address,question,answer\n/delivery,Новый?,Повтор.\n/delivery,Ещё?,Да.\n", dryRun: false, mode: FaqImport::APPEND)->json('data');

        $this->assertSame(1, $data['appended']);
        $this->assertSame('duplicate', $data['problems'][0]['code']);
        $this->assertSame(2, $data['problems'][0]['line']);
        $this->assertSame(['Новый?', 'Ещё?'], $this->questionsOf('/delivery'));
    }

    #[Test]
    public function a_bad_row_costs_that_row(): void
    {
        $data = $this->import(
            "address,question,answer\n/a,Вопрос?,\nhttps://other.test/x,Вопрос?,Ответ.\n/b,Вопрос?,Ответ.\n/b,вопрос? ,Повтор.\n",
            dryRun: false,
        )->json('data');

        $this->assertSame(['empty', 'foreign-host', 'duplicate'], array_column($data['problems'], 'code'));
        $this->assertSame([2, 3, 5], array_column($data['problems'], 'line'));
        $this->assertSame(1, $data['questions']);
        $this->assertSame(['Вопрос?'], $this->questionsOf('/b'));
    }

    #[Test]
    public function the_export_is_the_flat_file_the_import_reads(): void
    {
        $this->ruleWithFaq('/delivery', [['Сколько ждать?', '<p>Два дня.</p>']]);

        $path = $this->exported($this->actingAs($this->editor(), 'cms')->get('/api/cms/seo/faq/export?format=xlsx')->assertOk());
        $copy = $path.'.xlsx';
        copy($path, $copy);

        $rows = array_values(FaqSpreadsheet::read($copy, 'xlsx'));

        $this->assertSame([['address' => '/delivery', 'question' => 'Сколько ждать?', 'answer' => '<p>Два дня.</p>']], $rows);

        @unlink($copy);
    }

    #[Test]
    public function an_agent_reads_and_writes_a_page_faq_by_address(): void
    {
        $names = array_map(static fn (Tool $tool): string => $tool->name, app(SeoModule::class)->mcpTools());
        $this->assertSame(['faq_get', 'faq_set', 'faq_import'], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'faq_'))));

        $set = $this->tool('faq_set');

        $this->assertFalse($set(['url' => '/delivery', 'questions' => [['question' => 'Да?', 'answer' => '']]])['ok']);

        $preview = $set(['url' => '/delivery', 'questions' => [['question' => 'Сколько ждать?', 'answer' => 'Два дня.']], 'dry_run' => true]);
        $this->assertSame('created', $preview['rule']);
        $this->assertSame(0, SeoUrl::query()->count());

        $this->assertTrue($set(['url' => '/delivery', 'questions' => [['question' => ['ru' => 'Сколько ждать?', 'uk' => 'Скільки чекати?'], 'answer' => 'Два дня.']]])['applied']);

        $got = ($this->tool('faq_get'))(['url' => 'https://example.test/delivery']);
        $this->assertSame('Скільки чекати?', $got['questions'][0]['question']->uk);

        $rule = SeoUrl::query()->firstOrFail();
        $this->assertSame(1, ($this->tool('urls_get'))(['id' => $rule->id])['rule']['faq_count']);

        $import = ($this->tool('faq_import'))(['rows' => [['address' => '/delivery', 'question' => '', 'answer' => 'x']], 'dry_run' => true]);
        $this->assertSame(1, $import['problems'][0]['line']);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $questions
     */
    private function ruleWithFaq(string $pattern, array $questions): SeoUrl
    {
        $rule = SeoUrl::query()->create(['match_type' => UrlMatcher::EXACT, 'pattern' => $pattern]);

        app(PageFaq::class)->write($rule, array_map(
            static fn (array $question): array => ['question' => ['ru' => $question[0]], 'answer' => ['ru' => $question[1]]],
            $questions,
        ));

        return $rule;
    }

    /**
     * @return list<string>
     */
    private function questionsOf(string $pattern): array
    {
        $rule = SeoUrl::query()->where('pattern', $pattern)->firstOrFail();

        return $rule->faqItems->map(static fn (SeoFaqItem $item): string => $item->questionText('ru'))->all();
    }

    /**
     * The JSON-LD of the page as `<head>` prints it.
     *
     * @return list<array<string, mixed>>
     */
    private function blocksOf(string $url, string $locale = 'ru'): array
    {
        $head = (string) app(Seo::class)->head(null, $url, $locale);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $head, $matches);

        return array_map(static fn (string $json): array => (array) json_decode($json, true), $matches[1]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function faqPages(array $blocks): array
    {
        return array_values(array_filter($blocks, static fn (array $block): bool => ($block['@type'] ?? null) === 'FAQPage'));
    }

    private function render(string $url): string
    {
        app()->setLocale('ru');
        $this->app->instance('request', Request::create($url));

        return Blade::render('<x-webx-seo::faq />');
    }

    private function tool(string $name): callable
    {
        foreach (app(SeoModule::class)->mcpTools() as $tool) {
            if ($tool->name === $name) {
                return $tool->handler;
            }
        }

        $this->fail("No tool {$name}.");
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function exported(TestResponse $response): string
    {
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        return $file->getFile()->getPathname();
    }

    /**
     * @return TestResponse<Response>
     */
    private function import(string $csv, bool $dryRun = true, string $mode = FaqImport::REPLACE): TestResponse
    {
        return $this->actingAs($this->editor(), 'cms')->post('/api/cms/seo/faq/import', [
            'file' => UploadedFile::fake()->createWithContent('faq.csv', $csv),
            'mode' => $mode,
            'dry_run' => $dryRun ? '1' : '0',
        ], ['Accept' => 'application/json']);
    }
}
