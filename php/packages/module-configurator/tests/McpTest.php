<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * Configurators by their other doors (§14): an agent builds one end to end — reads the reference,
 * checks the document, rehearses, creates, prices, publishes — and a dry run writes nothing.
 */
final class McpTest extends TestCase
{
    private const array TOOLS = [
        'configurators_list', 'configurators_get', 'configurators_create', 'configurators_update',
        'configurators_publish', 'configurators_unpublish', 'configurators_discard', 'configurators_versions',
        'configurators_version_restore', 'configurators_delete', 'configurators_quote', 'configurators_validate',
    ];

    #[Test]
    public function the_section_offers_its_tools_and_resources(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(self::TOOLS, array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('configurators')));
        $this->assertSame(['configurators.view', 'configurators.manage'], $registry->tool('configurators_quote')->permissions());
        $this->assertSame(['configurators.manage'], $registry->tool('configurators_publish')->permissions());

        $uris = array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources());
        $this->assertContains('configurators://catalog', $uris);
        $this->assertContains('configurators://schema', $uris);

        $this->assertSame(0, Artisan::call('webx:mcp-tools', ['--module' => 'configurators']));
        $this->assertStringContainsString('configurators_quote', Artisan::output());
    }

    #[Test]
    public function the_schema_reference_is_the_validator_s_own(): void
    {
        $resource = collect($this->app->make(ToolRegistry::class)->resources())->firstWhere('uri', 'configurators://schema');
        $reference = ($resource->handler)();

        $this->assertArrayHasKey('area.syntax', $reference['errors']);
        $this->assertArrayHasKey('cards', $reference['step_types']);
        // The example in it is a schema the check takes, picture keys aside.
        $this->assertSame([], array_values(array_filter(
            $this->content($this->agent('configurators_validate', ['schema' => $reference['example']]))['errors'],
            static fn (array $error): bool => $error['code'] !== 'image.unknown',
        )));
    }

    #[Test]
    public function an_agent_builds_one_end_to_end_and_a_dry_run_writes_nothing(): void
    {
        $this->mediaFile();

        $checked = $this->content($this->agent('configurators_validate', ['schema' => $this->schema()]));
        $this->assertTrue($checked['valid']);

        $dry = $this->content($this->agent('configurators_create', ['key' => 'shower', 'title' => 'Душові кабіни', 'schema' => $this->schema(), 'dry_run' => true]));
        $this->assertTrue($dry['dry_run']);
        $this->assertNull($dry['configurator']['id']);
        $this->assertSame([], $dry['errors']);
        $this->assertSame(23900, $dry['initial_quote']['total']);
        $this->assertSame(0, Configurator::withTrashed()->count());

        $created = $this->content($this->agent('configurators_create', ['key' => 'shower', 'title' => 'Душові кабіни', 'schema' => $this->schema()]));
        $this->assertSame('draft', $created['configurator']['status']);

        // Not on the site yet: the live price is refused, the draft's is there.
        $this->agent('configurators_quote', ['configurator' => 'shower'])->assertHasErrors(['not on the site']);

        $quote = $this->content($this->agent('configurators_quote', [
            'configurator' => 'shower',
            'draft' => true,
            'values' => ['glass' => 'graphite', 'hardware' => 'gold'],
            'actions' => [['select' => ['construction', 'niche']], ['set' => ['w', 5000]]],
        ]));

        // A niche door 1600 × 2000: 3.2 m²; 5000 + 4000 × 3.2 + 600 × 3.2 = 19 720; +12% = 22 086.40.
        $this->assertSame(['construction' => 'niche', 'h' => 2000, 'w' => 1600, 'glass' => 'graphite', 'hardware' => 'gold'], $quote['normalized']);
        $this->assertSame(['d'], $quote['hidden']);
        $this->assertEqualsWithDelta(3.2, $quote['area'], 1e-9);
        $this->assertSame(22100, $quote['total']);
        // In the language the agent works in, the document's own words where it has them.
        $this->assertSame('Construction: Niche — 4,000 × 3.2 m²', $quote['lines'][1]['line']);
        $this->assertSame('Фурнітура', $quote['selection'][2]['label']);

        $revision = $created['revision'];
        $this->content($this->agent('configurators_publish', ['configurator' => 'shower', 'revision' => $revision]));

        $live = $this->content($this->agent('configurators_quote', ['configurator' => 'shower', 'values' => ['h' => 2000, 'w' => 900, 'd' => 900]]));
        $this->assertSame(23900, $live['total']);
        $this->assertSame('live', $live['schema']);

        $versions = $this->content($this->agent('configurators_versions', ['configurator' => 'shower']));
        $this->assertSame(1, $versions['versions'][0]['number']);
        $this->assertTrue($versions['versions'][0]['on_site']);
    }

    #[Test]
    public function a_size_outside_its_limits_is_an_error_and_a_price_from_outside_is_never_read(): void
    {
        $answer = $this->content($this->agent('configurators_quote', [
            'schema' => $this->schema(),
            'values' => ['h' => 2600, 'total' => 1],
        ]));

        $this->assertSame('values.h', $answer['errors'][0]['path']);
        $this->assertSame('values.range', $answer['errors'][0]['code']);
        $this->assertStringContainsString('1300', $answer['errors'][0]['message']);
        $this->assertSame(0, Configurator::withTrashed()->count());
    }

    #[Test]
    public function an_update_needs_the_revision_and_a_publication_with_problems_is_refused(): void
    {
        $this->mediaFile();
        $created = $this->content($this->agent('configurators_create', ['key' => 'shower', 'title' => 'Душові кабіни', 'schema' => $this->schema()]));

        $broken = $this->schema();
        $broken['steps'][0]['options'][1]['area'] = 'w*h)';

        $this->agent('configurators_update', ['configurator' => 'shower', 'schema' => $broken])->assertHasErrors(['Read the configurator first']);
        $this->agent('configurators_update', ['configurator' => 'shower', 'schema' => $broken, 'revision' => 'nope'])->assertHasErrors(['changed since you read it']);

        $dry = $this->content($this->agent('configurators_update', ['configurator' => 'shower', 'schema' => $broken, 'revision' => $created['revision'], 'dry_run' => true]));
        $this->assertSame('area.syntax', $dry['errors'][0]['code']);
        $this->assertSame([], $this->content($this->agent('configurators_get', ['configurator' => 'shower']))['errors']);

        $saved = $this->content($this->agent('configurators_update', ['configurator' => 'shower', 'schema' => $broken, 'revision' => $created['revision']]));
        $this->assertSame('steps.0.options.1.area', $saved['errors'][0]['path']);
        $this->assertNull($saved['initial_quote']);

        $this->agent('configurators_publish', ['configurator' => 'shower', 'revision' => $saved['revision']])->assertHasErrors(['area.syntax']);
        $this->assertFalse(Configurator::query()->firstOrFail()->isPublished());
    }

    #[Test]
    public function a_configurator_goes_to_the_bin_only_for_real(): void
    {
        $created = $this->content($this->agent('configurators_create', ['key' => 'doors', 'title' => 'Двері']));

        $this->content($this->agent('configurators_delete', ['configurator' => $created['configurator']['id'], 'dry_run' => true]));
        $this->assertFalse(Configurator::withTrashed()->firstOrFail()->trashed());

        $this->content($this->agent('configurators_delete', ['configurator' => 'doors']));
        $this->assertTrue(Configurator::withTrashed()->firstOrFail()->trashed());

        $listed = $this->content($this->agent('configurators_list', ['trashed' => true]));
        $this->assertSame('doors', $listed['configurators'][0]['key']);
    }

    #[Test]
    public function a_reader_without_manage_may_price_but_not_write(): void
    {
        $reader = $this->editor(['configurators.view']);

        $this->agent('configurators_quote', ['schema' => $this->schema()], $reader)->assertOk();
        $this->agent('configurators_create', ['key' => 'doors', 'title' => 'Двері'], $reader)->assertHasErrors();
        $this->assertSame(0, Configurator::withTrashed()->count());
    }

    #[Test]
    public function an_old_publication_goes_back_into_the_draft_and_the_site_waits(): void
    {
        $this->mediaFile();
        $created = $this->content($this->agent('configurators_create', ['key' => 'shower', 'title' => 'Душові кабіни', 'schema' => $this->schema()]));
        $this->content($this->agent('configurators_publish', ['configurator' => 'shower', 'revision' => $created['revision']]));

        $cheaper = $this->schema();
        $cheaper['steps'][0]['options'][0]['price']['fixed'] = 7000;
        $read = $this->content($this->agent('configurators_get', ['configurator' => 'shower']));
        $updated = $this->content($this->agent('configurators_update', ['configurator' => 'shower', 'schema' => $cheaper, 'revision' => $read['revision']]));
        $this->content($this->agent('configurators_publish', ['configurator' => 'shower', 'revision' => $updated['revision']]));
        $this->assertSame(22900, $this->content($this->agent('configurators_quote', ['configurator' => 'shower']))['total']);

        $dry = $this->content($this->agent('configurators_version_restore', ['configurator' => 'shower', 'number' => 1, 'dry_run' => true]));
        $this->assertSame(['schema'], $dry['changes']);
        $this->assertFalse(Configurator::query()->sole()->hasDraft());

        $restored = $this->content($this->agent('configurators_version_restore', ['configurator' => 'shower', 'number' => 1]));
        $this->assertSame('modified', $restored['configurator']['status']);
        // The site still prices by version 2 until somebody publishes.
        $this->assertSame(22900, $this->content($this->agent('configurators_quote', ['configurator' => 'shower']))['total']);
        $this->assertSame(23900, $this->content($this->agent('configurators_quote', ['configurator' => 'shower', 'draft' => true]))['total']);
    }
}
