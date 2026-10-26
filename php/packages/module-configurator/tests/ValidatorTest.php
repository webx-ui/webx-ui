<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Closure;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Configurator\Pricing\Quoter;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Configurator\Schema\Validator;
use WebxUi\Media\Models\MediaFile;

/**
 * The check a publication is held to (§9): every code is reachable, and each lands on the path
 * the editor underlines.
 */
final class ValidatorTest extends TestCase
{
    #[Test]
    public function a_good_schema_has_no_problems(): void
    {
        $this->mediaFile();

        $this->assertSame([], $this->problems($this->schema()));
    }

    #[Test]
    public function every_code_is_given_where_it_belongs(): void
    {
        $this->mediaFile();
        $seen = [];

        foreach ($this->broken() as [$break, $path, $code]) {
            $schema = $this->schema();
            $break($schema);
            $found = array_map(static fn (array $one): string => "{$one['path']} {$one['code']}", $this->problems($schema));

            $this->assertContains("{$path} {$code}", $found, "Expected {$code} at {$path}, got: ".implode('; ', $found));
            $seen[$code] = true;
        }

        $this->assertSame([], array_values(array_diff(array_keys(Validator::CODES), array_keys($seen))), 'Codes no case reaches.');
    }

    #[Test]
    public function a_message_is_in_the_language_of_the_request_and_names_the_place(): void
    {
        $schema = $this->schema();
        $schema['steps'][0]['options'][0]['area'] = '(w+depth)*h';

        $this->app->setLocale('uk');
        $problem = $this->app->make(Quoter::class)->problems($schema)[0];

        $this->assertSame('area.unknown_key', $problem->code);
        $this->assertStringContainsString('«depth»', $problem->message());
        $this->assertStringContainsString('поля розмірів немає', $problem->message());
    }

    #[Test]
    public function a_picture_is_looked_up_only_when_it_is_new(): void
    {
        $schema = $this->schema('media/gone/old.svg');

        // Published with it once, and the file went from the library since: still a draft that saves.
        $this->assertSame([], $this->problems($schema, known: ['media/gone/old.svg']));
        $this->assertSame(['steps.0.options.0.image image.unknown'], array_map(
            static fn (array $one): string => "{$one['path']} {$one['code']}",
            $this->problems($schema),
        ));
    }

    #[Test]
    public function a_picture_given_as_an_id_is_told_once(): void
    {
        // What an agent sends after reading media://fields: the library's id rather than its key.
        $this->mediaFile();
        $schema = $this->schema();
        $schema['steps'][0]['options'][0]['image'] = ['id' => 35];

        $this->assertSame(['steps.0.options.0.image image.missing'], array_map(
            static fn (array $one): string => "{$one['path']} {$one['code']}",
            $this->problems($schema),
        ));
    }

    #[Test]
    public function a_swatch_takes_colours_and_gradients_and_nothing_that_could_carry_css(): void
    {
        foreach (['#fff', '#59616a', '#59616a80', 'linear-gradient(135deg, #b8862b, #f3d27a)', 'radial-gradient(circle at center, #fff 0%, #000 100%)', 'linear-gradient(to bottom right, #fff, #000)'] as $good) {
            $this->assertTrue(Validator::swatch($good), $good);
        }

        foreach (['red', 'url(x.png)', '#fff; background: url(x)', 'linear-gradient(red, blue)', 'linear-gradient(#fff, #000) , url(x)', 'expression(alert(1))', 'var(--x)'] as $bad) {
            $this->assertFalse(Validator::swatch($bad), $bad);
        }
    }

    /**
     * How to break the schema, where the problem lands, and its code.
     *
     * @return list<array{0: Closure(array<string, mixed>&): void, 1: string, 2: string}>
     */
    private function broken(): array
    {
        return [
            [static function (array &$s): void {
                $s = ['version', 1];
            }, '', 'schema.invalid'],
            [static function (array &$s): void {
                $s['version'] = 2;
            }, 'version', 'schema.version'],
            [static function (array &$s): void {
                $s['currency'] = 'uah';
            }, 'currency', 'currency.invalid'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['product'] = ['sku' => 'GLASS-1'];
            }, 'steps.2.options.1.product', 'product.invalid'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['product'] = 'NOBODY-1';
            }, 'steps.2.options.1.product', 'product.unknown'],
            [static function (array &$s): void {
                $s['pricing']['round'] = 0;
            }, 'pricing.round', 'pricing.round'],
            [static function (array &$s): void {
                $s['discount'] = ['percent' => 0];
            }, 'discount.percent', 'discount.percent'],
            [static function (array &$s): void {
                $s['discount'] = ['percent' => 10, 'until' => '2026-02-30'];
            }, 'discount.until', 'discount.until'],
            [static function (array &$s): void {
                $s['steps'] = [];
            }, 'steps', 'steps.empty'],
            [static function (array &$s): void {
                $s['steps'][2]['key'] = 'Glass';
            }, 'steps.2.key', 'key.invalid'],
            [static function (array &$s): void {
                $s['steps'][2]['key'] = 'h';
            }, 'steps.2.key', 'key.duplicate'],
            [static function (array &$s): void {
                $s['steps'][2]['type'] = 'checkboxes';
            }, 'steps.2.type', 'type.unknown'],
            [static function (array &$s): void {
                $s['steps'][2]['options'] = [];
            }, 'steps.2.options', 'options.empty'],
            [static function (array &$s): void {
                $s['steps'][1]['fields'] = [];
            }, 'steps.1.fields', 'fields.empty'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['value'] = 'Graphite glass';
            }, 'steps.2.options.1.value', 'value.invalid'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['value'] = 'clear';
            }, 'steps.2.options.1.value', 'value.duplicate'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['label'] = ['en' => 'Graphite'];
            }, 'steps.2.options.1.label', 'label.missing'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['subtitle'] = ['uk', 'en'];
            }, 'steps.2.options.1.subtitle', 'text.invalid'],
            [static function (array &$s): void {
                unset($s['steps'][0]['options'][1]['image']);
            }, 'steps.0.options.1.image', 'image.missing'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['swatchImage'] = 'media/none.svg';
            }, 'steps.2.options.1.swatchImage', 'image.unknown'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['swatch'] = 'url(x.png)';
            }, 'steps.2.options.1.swatch', 'swatch.invalid'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['price'] = ['perM2' => '600'];
            }, 'steps.2.options.1.price.perM2', 'price.invalid'],
            [static function (array &$s): void {
                unset($s['steps'][0]['options'][0]['area'], $s['steps'][0]['options'][1]['area']);
            }, 'steps.0.options.0.price.perM2', 'price.per_m2'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][0]['area'] = 'w*h';
            }, 'steps.2', 'area.main_count'],
            [static function (array &$s): void {
                unset($s['steps'][0]['options'][1]['area']);
            }, 'steps.0.options.1.area', 'area.missing'],
            [static function (array &$s): void {
                $s['steps'][0]['options'][1]['area'] = 'w*h)';
            }, 'steps.0.options.1.area', 'area.syntax'],
            [static function (array &$s): void {
                $s['steps'][0]['options'][1]['area'] = 'w*height';
            }, 'steps.0.options.1.area', 'area.unknown_key'],
            [static function (array &$s): void {
                $s['steps'][1]['fields'][0]['min'] = 3000;
            }, 'steps.1.fields.0', 'range.invalid'],
            [static function (array &$s): void {
                $s['steps'][0]['options'][0]['limits'] = ['height' => [1, 2]];
            }, 'steps.0.options.0.limits.height', 'limits.unknown_field'],
            [static function (array &$s): void {
                $s['steps'][0]['options'][0]['defaults']['depth'] = 900;
            }, 'steps.0.options.0.defaults.depth', 'defaults.unknown_field'],
            [static function (array &$s): void {
                $s['steps'][2]['when'] = ['construction' => 'corner'];
            }, 'steps.2.when.construction', 'condition.invalid'],
            [static function (array &$s): void {
                $s['steps'][2]['when'] = ['shape' => ['corner']];
            }, 'steps.2.when.shape', 'condition.unknown_step'],
            [static function (array &$s): void {
                $s['steps'][2]['when'] = ['hardware' => ['gold']];
            }, 'steps.2.when.hardware', 'condition.forward'],
            [static function (array &$s): void {
                $s['steps'][2]['when'] = ['size' => ['h']];
            }, 'steps.2.when.size', 'condition.not_choice'],
            [static function (array &$s): void {
                $s['steps'][2]['options'][1]['unless'] = ['construction' => ['bath']];
            }, 'steps.2.options.1.unless.construction.0', 'condition.unknown_value'],
            [static function (array &$s): void {
                $s['initial']['glass'] = 'flutes';
            }, 'initial.glass', 'initial.unknown'],
            [static function (array &$s): void {
                $s['previews'] = [['when' => ['glass' => ['flutes']], 'image' => 'media/demo/corner.svg']];
            }, 'previews.0.when.glass.0', 'previews.condition'],
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $schema
     * @param  list<string>  $known
     * @return list<array{path: string, code: string, message: string}>
     */
    private function problems(array $schema, array $known = []): array
    {
        // The library as the module asks it, and a catalogue that has none of the products asked
        // about — the one with products is CatalogLinkTest's.
        $media = static fn (array $keys): array => MediaFile::query()->whereIn('path', $keys)->pluck('path')->map(strval(...))->all();

        return array_map(
            static fn (Problem $problem): array => $problem->toArray(),
            (new Validator('uk', $media, $known, static fn (array $skus): array => []))->validate($schema),
        );
    }
}
