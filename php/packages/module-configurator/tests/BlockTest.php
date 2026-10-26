<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Relations\ConfiguratorTarget;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The block `configurator` the module offers (§12.1): installed by `webx:blocks:offered`, drawn on
 * its own sample (no configurator — nothing), a configurator with its heading and preset, a page
 * that goes on answering when the configurator is gone; and the preset field that keeps the choice.
 */
final class BlockTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class, BlocksServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    #[Test]
    public function the_offered_type_is_installed_with_its_three_fields(): void
    {
        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('Configurator', $block->title);
        $this->assertSame(['configurator', 'title', 'preset'], array_column((array) $block->publishedVersion?->schema, 'id'));
        $this->assertSame(['wx-configurator', 'wx-input', 'wx-configurator-preset'], array_column((array) $block->publishedVersion?->schema, 'type'));

        // The sample names no configurator: a key would be copied into every new block.
        $this->assertNull($block->publishedVersion?->sample['configurator'] ?? null);
    }

    #[Test]
    public function it_draws_the_configurator_with_its_heading_and_preset(): void
    {
        $this->installBlock();
        $this->published();

        $html = $this->render(['configurator' => 'shower', 'title' => 'Підберіть кабіну', 'preset' => ['construction' => 'niche', 'gone' => 'x']]);

        $this->assertStringContainsString('<div class="b-configurator" data-wx-block="configurator">', $html);
        $this->assertStringContainsString('<section class="webx-configurator"', $html);
        $this->assertStringContainsString('<h2 class="webx-configurator__title">Підберіть кабіну</h2>', $html);
        $this->assertMatchesRegularExpression('~is-selected" data-webx-configurator-option="niche"~', $html);
    }

    #[Test]
    public function a_configurator_gone_from_the_site_leaves_the_block_empty(): void
    {
        $this->installBlock();
        $this->published()->delete();

        $html = $this->render(['configurator' => 'shower']);

        $this->assertStringNotContainsString('webx-configurator', $html);
        $this->assertSame('', trim($this->render([])));
    }

    #[Test]
    public function the_preset_keeps_values_and_numbers_by_key(): void
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-configurator-preset');
        $this->assertNotNull($type);

        $this->assertSame(
            ['construction' => 'niche', 'h' => 2100, 'w' => 950.5],
            $type->store(['construction' => ' niche ', 'h' => 2100.0, 'w' => 950.5, 'Bad Key' => 'x', 'glass' => '', 'list' => ['a']], []),
        );
        $this->assertNull($type->store([], []));
        $this->assertNull($type->store('construction=niche', []));
        $this->assertSame([], $type->resolve(null, []));
    }

    #[Test]
    public function the_picker_row_carries_what_a_preset_chooses_from(): void
    {
        $this->published();

        $this->assertInstanceOf(ConfiguratorTarget::class, $this->app->make(RelationTargets::class)->find(ConfiguratorTarget::KEY));

        $row = (new ConfiguratorTarget)->row(Configurator::query()->sole(), 'uk');
        $this->assertArrayHasKey('choices', $row);
        $choices = array_column($row['choices'], null, 'key');

        $this->assertSame(['construction', 'h', 'w', 'd', 'glass', 'hardware'], array_keys($choices));
        $this->assertSame('choice', $choices['construction']['kind']);
        $this->assertSame('corner', $choices['construction']['initial']);
        $this->assertSame([['value' => 'corner', 'label' => 'Кутова'], ['value' => 'niche', 'label' => 'У нішу']], $choices['construction']['options']);
        $this->assertSame(['key' => 'h', 'kind' => 'size', 'label' => 'Висота', 'unit' => 'мм', 'min' => 1300, 'max' => 2400, 'step' => 50, 'initial' => null], $choices['h']);
    }

    private function installBlock(): Block
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['configurators']])->assertSuccessful();

        return Block::query()->where('slug', 'configurator')->firstOrFail();
    }

    private function published(): Configurator
    {
        $this->mediaFile();
        $writer = app(ConfiguratorWriter::class);

        return $writer->publish($writer->create('shower', ['uk' => 'Душові'], $this->schema()));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(array $values): string
    {
        $this->app->forgetScopedInstances();

        return (string) $this->app->make(Renderer::class)->render([['key' => 'k1', 'type' => 'configurator', 'values' => $values]]);
    }
}
