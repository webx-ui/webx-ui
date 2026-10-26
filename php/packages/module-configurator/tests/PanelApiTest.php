<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;

/**
 * The panel's API of the section (§13.3): the same writer as the MCP tools behind it, so what is
 * checked here is the door — the shapes the editor reads, the revision and its 409, the 422s under
 * the name of the field, and the permissions.
 */
final class PanelApiTest extends TestCase
{
    private const string API = '/api/cms/configurators';

    #[Test]
    public function a_new_configurator_is_a_draft_with_a_skeleton_and_its_problems(): void
    {
        $detail = $this->actingAs($this->editor(), 'cms')
            ->postJson(self::API, ['title' => 'Душові', 'key' => 'shower'])
            ->assertCreated()
            ->json('data');

        $this->assertSame('shower', $detail['configurator']['key']);
        $this->assertSame('draft', $detail['configurator']['status']);
        $this->assertFalse($detail['configurator']['key_locked']);
        $this->assertSame(['uk' => 'Душові'], $detail['values']['title']);
        $this->assertSame([], $detail['values']['schema']['steps']);
        $this->assertSame('steps.empty', $detail['errors'][0]['code']);
        $this->assertNotSame('', $detail['revision']);
        $this->assertFalse($detail['has_inbox']);
    }

    #[Test]
    public function a_taken_key_or_a_missing_title_is_refused_under_its_field(): void
    {
        $this->writer()->create('shower', ['uk' => 'Душові']);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson(self::API, ['title' => 'Ще одна', 'key' => 'shower'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        $this->actingAs($editor, 'cms')
            ->postJson(self::API, ['title' => 'Двері', 'key' => 'Doors and more'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        $this->actingAs($editor, 'cms')
            ->postJson(self::API, ['title' => '', 'key' => 'doors'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    #[Test]
    public function the_list_says_the_status_steps_and_form_and_finds_by_key_or_title(): void
    {
        $this->mediaFile();
        $shower = $this->writer()->create('shower', ['uk' => 'Душові', 'en' => 'Showers'], $this->schema());
        $this->writer()->publish($shower);
        $this->writer()->create('doors', ['uk' => 'Двері']);

        $rows = $this->actingAs($this->editor(['configurators.view']), 'cms')
            ->getJson(self::API)
            ->assertOk()
            ->json('data');

        $this->assertSame(['shower', 'doors'], array_column($rows, 'key'));
        $this->assertSame(['published', 'draft'], array_column($rows, 'status'));
        $this->assertSame([4, 0], array_column($rows, 'steps'));
        $this->assertSame([true, false], array_column($rows, 'key_locked'));

        $found = $this->actingAs($this->editor(['configurators.view']), 'cms')
            ->getJson(self::API.'?search='.urlencode('Двер'))
            ->json('data');

        $this->assertSame(['doors'], array_column($found, 'key'));
    }

    #[Test]
    public function a_draft_saves_with_problems_and_a_stale_revision_is_a_409_with_the_record(): void
    {
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові']);
        $editor = $this->editor();

        $opened = $this->actingAs($editor, 'cms')->getJson(self::API.'/'.$configurator->id)->assertOk()->json('data');

        $schema = $this->schema();
        $schema['steps'][0]['options'][0]['area'] = 'w*h;system(1)';

        $saved = $this->actingAs($editor, 'cms')
            ->putJson(self::API.'/'.$configurator->id, [
                'values' => ['schema' => $schema, 'title' => ['uk' => 'Кабіни', 'en' => '']],
                'revision' => $opened['revision'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame(['uk' => 'Кабіни'], $saved['values']['title'], 'an empty language is no language');
        $this->assertContains('area.syntax', array_column($saved['errors'], 'code'));
        $this->assertNotSame($opened['revision'], $saved['revision']);

        // The editor that still holds the first revision is told, with what is there now.
        $this->actingAs($editor, 'cms')
            ->putJson(self::API.'/'.$configurator->id, ['values' => ['title' => ['uk' => 'Ще']], 'revision' => $opened['revision']])
            ->assertStatus(409)
            ->assertJsonPath('data.values.title.uk', 'Кабіни')
            ->assertJsonPath('data.revision', $saved['revision']);
    }

    #[Test]
    public function a_schema_that_is_not_an_object_is_refused(): void
    {
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson(self::API.'/'.$configurator->id, ['values' => ['schema' => [1, 2]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schema');
    }

    #[Test]
    public function publishing_refuses_problems_with_their_paths_and_then_locks_the_key(): void
    {
        $this->mediaFile();
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові']);
        $editor = $this->editor();

        $refused = $this->actingAs($editor, 'cms')
            ->postJson(self::API.'/'.$configurator->id.'/publish')
            ->assertUnprocessable()
            ->json();

        $this->assertSame('steps.empty', $refused['problems'][0]['code']);
        $this->assertSame('steps', $refused['problems'][0]['path']);

        $this->actingAs($editor, 'cms')
            ->putJson(self::API.'/'.$configurator->id, ['values' => ['schema' => $this->schema()]])
            ->assertOk();

        $published = $this->actingAs($editor, 'cms')
            ->postJson(self::API.'/'.$configurator->id.'/publish')
            ->assertOk()
            ->json('data');

        $this->assertSame('published', $published['configurator']['status']);
        $this->assertTrue($published['configurator']['key_locked']);
        $this->assertSame([], $published['errors']);

        $this->actingAs($editor, 'cms')
            ->putJson(self::API.'/'.$configurator->id, ['values' => ['key' => 'cabins']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    #[Test]
    public function a_publication_under_a_revision_somebody_wrote_over_is_a_409(): void
    {
        $this->mediaFile();
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові'], $this->schema());

        $this->actingAs($this->editor(), 'cms')
            ->postJson(self::API.'/'.$configurator->id.'/publish', ['revision' => 'not-the-one'])
            ->assertStatus(409);

        $this->assertFalse($configurator->refresh()->isPublished());
    }

    #[Test]
    public function unpublish_discard_versions_and_restore_of_a_version(): void
    {
        $this->mediaFile();
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові'], $this->schema());
        $this->writer()->publish($configurator);
        $this->writer()->update($configurator, ['title' => ['uk' => 'Кабіни']]);
        $this->writer()->publish($configurator);
        $editor = $this->editor();

        $versions = $this->actingAs($editor, 'cms')->getJson(self::API.'/'.$configurator->id.'/versions')->assertOk()->json('data');
        $this->assertSame([2, 1], array_column($versions, 'number'));
        $this->assertSame([true, false], array_column($versions, 'on_site'));

        $restored = $this->actingAs($editor, 'cms')
            ->postJson(self::API.'/'.$configurator->id.'/versions/1/restore')
            ->assertOk()
            ->json('data');
        $this->assertSame('Душові', $restored['values']['title']['uk']);
        $this->assertSame('modified', $restored['configurator']['status']);

        $discarded = $this->actingAs($editor, 'cms')->postJson(self::API.'/'.$configurator->id.'/discard')->assertOk()->json('data');
        $this->assertSame('Кабіни', $discarded['values']['title']['uk']);
        $this->assertSame('published', $discarded['configurator']['status']);

        $this->actingAs($editor, 'cms')->postJson(self::API.'/'.$configurator->id.'/versions/9/restore')->assertNotFound();

        $off = $this->actingAs($editor, 'cms')->postJson(self::API.'/'.$configurator->id.'/unpublish')->assertOk()->json('data');
        $this->assertSame('unpublished', $off['configurator']['status']);
        $this->assertNull($configurator->refresh()->liveSchema());
    }

    #[Test]
    public function the_bin_and_back(): void
    {
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові']);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson(self::API.'/'.$configurator->id)->assertNoContent();
        $this->actingAs($editor, 'cms')->getJson(self::API.'/'.$configurator->id)->assertNotFound();

        $bin = $this->actingAs($editor, 'cms')->getJson(self::API.'?trashed=1')->json('data');
        $this->assertSame(['shower'], array_column($bin, 'key'));
        $this->assertArrayHasKey('deleted_at', $bin[0]);

        $this->actingAs($editor, 'cms')->postJson(self::API.'/'.$configurator->id.'/restore')->assertOk()->assertJsonPath('data.key', 'shower');
        $this->assertFalse(Configurator::query()->findOrFail($configurator->id)->trashed());
    }

    #[Test]
    public function validate_and_quote_answer_as_the_mcp_tools_do(): void
    {
        $this->mediaFile();
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові'], $this->schema());
        $reader = $this->editor(['configurators.view']);

        $checked = $this->actingAs($reader, 'cms')
            ->postJson(self::API.'/validate', ['schema' => ['version' => 2]])
            ->assertOk()
            ->json('data');

        $this->assertFalse($checked['valid']);
        $this->assertSame('schema.version', $checked['errors'][0]['code']);
        $this->assertSame('version', $checked['errors'][0]['path']);

        // Not on the site: nothing live to price, the draft on request.
        $this->actingAs($reader, 'cms')->postJson(self::API.'/'.$configurator->id.'/quote', [])->assertUnprocessable();

        $quote = $this->actingAs($reader, 'cms')
            ->postJson(self::API.'/'.$configurator->id.'/quote', [
                'draft' => true,
                'values' => ['glass' => 'graphite'],
                'actions' => [['set' => ['h', 1800]]],
                'today' => '2026-10-26',
            ])
            ->assertOk()
            ->json('data');

        // (900 + 900) × 1800 / 1e6 = 3.24 m²: 8000 + 4420 × 3.24 + 600 × 3.24 = 24 264.8 → 24 300.
        $this->assertEqualsWithDelta(3.24, $quote['area'], 1e-9);
        $this->assertSame(24300, $quote['total']);
        $this->assertSame(1800, $quote['normalized']['h']);
        $this->assertSame(['fixed', 'perM2', 'perM2'], array_column($quote['lines'], 'kind'));
        $this->assertNotSame('', $quote['lines'][0]['line']);

        $refused = $this->actingAs($reader, 'cms')
            ->postJson(self::API.'/'.$configurator->id.'/quote', ['draft' => true, 'values' => ['h' => 9000]])
            ->assertOk()
            ->json('data');

        $this->assertSame([['path' => 'values.h', 'code' => 'values.range']], array_map(
            static fn (array $error): array => ['path' => $error['path'], 'code' => $error['code']],
            $refused['errors'],
        ));
    }

    #[Test]
    public function reading_needs_view_and_writing_needs_manage(): void
    {
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові']);

        $this->actingAs($this->editor([]), 'cms')->getJson(self::API)->assertForbidden();

        $reader = $this->editor(['configurators.view']);
        $this->actingAs($reader, 'cms')->getJson(self::API.'/'.$configurator->id)->assertOk();
        $this->actingAs($reader, 'cms')->putJson(self::API.'/'.$configurator->id, ['values' => ['title' => ['uk' => 'Ні']]])->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson(self::API.'/'.$configurator->id.'/publish')->assertForbidden();
        $this->actingAs($reader, 'cms')->deleteJson(self::API.'/'.$configurator->id)->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson(self::API, ['title' => 'Ні', 'key' => 'no'])->assertForbidden();
    }

    #[Test]
    public function the_editor_heartbeat_knows_the_record(): void
    {
        $configurator = $this->writer()->create('shower', ['uk' => 'Душові']);
        $editor = $this->editor();

        $opened = $this->actingAs($editor, 'cms')->getJson(self::API.'/'.$configurator->id)->json('data');

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/editing/configurators/'.$configurator->id)
            ->assertOk()
            ->assertJsonPath('data.revision', $opened['revision']);
    }

    private function writer(): ConfiguratorWriter
    {
        return $this->app->make(ConfiguratorWriter::class);
    }
}
