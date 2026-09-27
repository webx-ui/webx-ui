<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Panel\OutletForm;

/**
 * The outlet's form through the real write path (§4.6, §4.14): the repeater on the screen, the
 * table in the database, matched by id.
 */
final class FormTest extends TestCase
{
    /**
     * The question §4.6 asks first: does a row's id make it through `ScreenValues`? It does —
     * `wx-input-number` with `visible: false` names it for the repeater, so `store()` keeps it,
     * and a number is what its type takes. `wx-input` would not do: its rules want a string.
     */
    #[Test]
    public function the_id_of_a_row_survives_the_screen_both_ways(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('First')]);
        $values = $this->app->make(OutletForm::class)->values($outlet);
        $id = $values['articles'][0]['id'];

        $this->assertIsInt($id);

        $stored = $this->app->make(ScreenValues::class)->validate(Outlet::SCREEN, ['articles' => $values['articles']]);

        $this->assertSame($id, $stored['articles'][0]['id']);
        $this->assertSame(['en' => 'First'], $stored['articles'][0]['title']);
    }

    #[Test]
    public function rows_update_create_delete_and_keep_their_order(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('One'), $this->row('Two'), $this->row('Three')]);
        [$one, $two, $three] = $outlet->articles()->get()->all();

        $this->save($outlet, ['articles' => [
            ['id' => $three->id, 'title' => ['en' => 'Three, renamed']] + $this->row('x'),
            $this->row('New'),
            ['id' => $one->id] + $this->row('One'),
        ]]);

        $this->assertSame(['Three, renamed', 'New', 'One'], $this->titles($outlet));
        $this->assertSame($three->id, $outlet->articles()->first()?->id, 'the row with an id is the same article');
        $this->assertNull(Article::query()->find($two->id), 'the row that went is the article that went');
        $this->assertSame([1, 2, 3], $outlet->articles()->pluck('position')->all());
    }

    #[Test]
    public function the_id_of_another_outlets_article_is_refused_and_nothing_is_written(): void
    {
        $other = $this->outlet('Vogue', [$this->row('Theirs')]);
        $outlet = $this->outlet('Tatler', [$this->row('Ours')]);
        $theirs = $other->articles()->firstOrFail();

        $this->assertRefused(['articles'], fn () => $this->save($outlet, [
            'title' => ['en' => 'Renamed'],
            'articles' => [['id' => $theirs->id] + $this->row('Stolen')],
        ]));

        $this->assertSame(['Ours'], $this->titles($outlet->refresh()));
        $this->assertSame('Tatler', $outlet->getTranslation('title', 'en'));
        $this->assertSame(['Theirs'], $this->titles($other));
    }

    #[Test]
    public function a_refusal_half_way_leaves_no_rows_behind(): void
    {
        $count = Outlet::query()->count();

        // The last row is refused by the registry, after the outlet and two articles were written.
        $this->outlet('Taken', [$this->row('A')]);

        $this->assertRefused(['slug'], fn () => $this->outlet('Taken again', [$this->row('B'), $this->row('C')], values: [
            'slug' => ['en' => 'taken'],
        ]));

        $this->assertSame($count + 1, Outlet::query()->count());
        $this->assertSame(1, Article::query()->count());
    }

    #[Test]
    public function an_error_in_a_translated_field_of_a_row_is_named_by_row_field_and_language(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('Fine')]);

        $errors = $this->errors(fn () => $this->save($outlet, ['articles' => [
            $this->row('Fine'),
            $this->row('x', ['title' => ['en' => str_repeat('a', 501)]]),
        ]]));

        $this->assertArrayHasKey('articles.1.title.en', $errors);

        // And through the panel's door, in the answer the form reads.
        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($outlet->id), ['values' => ['articles' => [$this->row('', ['title' => ['en' => '', 'ru' => '']])]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['articles.0.title.en']);
    }

    #[Test]
    public function a_link_is_http_and_an_article_leads_somewhere(): void
    {
        $outlet = $this->outlet('Tatler');

        $this->assertArrayHasKey('articles.0.url', $this->errors(fn () => $this->save($outlet, ['articles' => [
            $this->row('Script', ['url' => 'javascript:alert(1)']),
        ]])));

        $this->assertArrayHasKey('articles.0.url', $this->errors(fn () => $this->save($outlet, ['articles' => [
            ['title' => ['en' => 'Nowhere']],
        ]])));

        $this->assertArrayHasKey('website_url', $this->errors(fn () => $this->save($outlet, ['website_url' => 'ftp://tatler.example'])));
    }

    #[Test]
    public function a_file_is_a_pdf_and_a_kind_is_one_of_the_config(): void
    {
        $outlet = $this->outlet('Tatler');
        $picture = $this->file('media/ab/cd/logo.png', 'image/png');
        $pdf = $this->file();

        $this->assertArrayHasKey('articles.0.file', $this->errors(fn () => $this->save($outlet, ['articles' => [
            ['title' => ['en' => 'Scan'], 'file' => ['path' => $picture->path]],
        ]])));

        $this->assertArrayHasKey('articles.0.kind', $this->errors(fn () => $this->save($outlet, ['articles' => [
            $this->row('Kind', ['kind' => 'gossip']),
        ]])));

        $this->save($outlet, ['articles' => [['title' => ['en' => 'Scan'], 'file' => ['path' => $pdf->path], 'kind' => 'interview']]]);

        $article = $outlet->articles()->firstOrFail();
        $this->assertSame(['path' => $pdf->path], $article->file);
        $this->assertSame('interview', $article->kind);
        $this->assertStringContainsString('/scan.pdf', (string) $article->target());
    }

    #[Test]
    public function a_kind_taken_out_of_the_config_is_no_kind_on_reading(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('Old', ['kind' => 'mention'])]);

        config()->set('webx-press.kinds', ['interview']);

        $this->assertNull($outlet->articles()->firstOrFail()->kindKey());
    }

    #[Test]
    public function a_language_with_articles_and_no_slug_takes_the_one_there_is(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('Both', ['title' => ['en' => 'Both', 'ru' => 'Обе']])]);

        $this->assertSame(['en' => 'tatler', 'ru' => 'tatler'], $outlet->getTranslations('slug'));
        $this->assertStringEndsWith('/ru/press/tatler', $outlet->url('ru'));
    }

    #[Test]
    public function the_form_answers_with_the_shape_the_panel_reads(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('One', ['published_on' => '2023-08-12', 'date_precision' => 'month'])]);

        $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api($outlet->id))
            ->assertOk()
            ->assertJsonPath('data.outlet.id', $outlet->id)
            ->assertJsonPath('data.outlet.title', 'Tatler')
            ->assertJsonPath('data.outlet.url', url('press/tatler'))
            ->assertJsonPath('data.prefix', 'press')
            ->assertJsonPath('data.values.title', ['en' => 'Tatler'])
            ->assertJsonPath('data.values.articles.0.title', ['en' => 'One'])
            ->assertJsonPath('data.values.articles.0.published_on', '2023-08-12')
            ->assertJsonPath('data.values.articles.0.date_precision', 'month')
            ->assertJsonPath('data.values.articles.0.is_hidden', false);

        $created = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['values' => ['title' => ['en' => 'Vogue'], 'articles' => [$this->row('A')]]])
            ->assertCreated()
            ->json('data');

        $this->assertSame('Vogue', $created['outlet']['title']);
        $this->assertCount(1, $created['values']['articles']);
    }

    #[Test]
    public function the_kinds_of_the_config_are_the_options_of_the_select(): void
    {
        $screen = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/'.Outlet::SCREEN)->assertOk()->json();

        $this->assertStringContainsString('"value":"expert_comment","label":"Expert comment"', (string) json_encode($screen));
        $this->assertStringContainsString('"name":"slug"', (string) json_encode($screen));
        $this->assertStringContainsString('"name":"seo"', (string) json_encode($screen), 'the SEO tab is patched in by module-seo');
    }

    #[Test]
    public function deleting_for_good_takes_the_articles_with_it(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('One'), $this->row('Two')]);

        $outlet->delete();
        $this->assertSame(2, Article::query()->count(), 'the bin keeps them');

        $outlet->forceDelete();
        $this->assertSame(0, Article::query()->count());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function save(Outlet $outlet, array $values): Outlet
    {
        return $this->app->make(OutletForm::class)->save($outlet, $values);
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(callable $work): array
    {
        try {
            $work();
        } catch (ValidationException $refused) {
            return $refused->errors();
        }

        $this->fail('The save was not refused.');
    }

    /**
     * @param  list<string>  $keys
     */
    private function assertRefused(array $keys, callable $work): void
    {
        $errors = $this->errors($work);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $errors);
        }
    }
}
