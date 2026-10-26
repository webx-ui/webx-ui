<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Configurator\Panel\ConfiguratorWriter;
use WebxUi\Configurator\Relations\ConfiguratorTarget;
use WebxUi\Configurator\Screens\ConfiguratorFieldType;

/**
 * `wx-configurator` keeps a key, as the block of the site will (§12.1), refuses a key nobody has
 * only when it is written anew, and its picker lists configurators through the relation target
 * `configurator`.
 */
final class FieldTypeTest extends TestCase
{
    #[Test]
    public function the_type_is_registered_and_keeps_the_key(): void
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-configurator');

        $this->assertInstanceOf(ConfiguratorFieldType::class, $type);
        $this->assertSame('shower', $type->store('  shower ', []));
        $this->assertNull($type->store('', []), 'empty is none');
        $this->assertNull($type->store(null, []));
        $this->assertSame('shower', $type->resolve('shower', []));
        $this->assertNull($type->resolve(['uk' => 'shower'], []));
    }

    #[Test]
    public function a_key_nobody_has_is_a_problem_that_names_the_keys_there_are(): void
    {
        $type = new ConfiguratorFieldType;

        $this->assertSame(['There is no configurator “shower” — the site has none yet.'], $type->newValueProblems('shower', []));

        $this->writer()->create('shower', ['uk' => 'Душові']);
        $this->writer()->create('doors', ['uk' => 'Двері']);

        $this->assertSame([], $type->newValueProblems('shower', []), 'a draft is a configurator');
        $this->assertSame([], $type->newValueProblems('', []));
        $this->assertSame(['There is no configurator “showers”. There are: shower, doors.'], $type->newValueProblems('showers', []));
    }

    #[Test]
    public function a_screen_refuses_a_new_unknown_key_and_keeps_one_it_already_held(): void
    {
        $this->writer()->create('shower', ['uk' => 'Душові']);
        $this->app->make(ScreenRegistry::class)->register('test.configurator', [
            ['id' => 'configurator', 'type' => 'wx-configurator', 'name' => 'configurator'],
        ]);
        $values = $this->app->make(ScreenValues::class);

        $this->assertSame(['configurator' => 'shower'], $values->validate('test.configurator', ['configurator' => 'shower'], held: []));
        $this->assertSame(['configurator' => 'gone'], $values->validate('test.configurator', ['configurator' => 'gone'], held: ['configurator' => 'gone']));

        try {
            $values->validate('test.configurator', ['configurator' => 'gone'], held: ['configurator' => 'shower']);
            $this->fail('A key nobody has was accepted.');
        } catch (ValidationException $refused) {
            $this->assertSame(['configurator' => ['There is no configurator “gone”. There are: shower.']], $refused->errors());
        }
    }

    #[Test]
    public function the_picker_finds_by_key_puts_the_exact_one_first_and_marks_what_is_off_the_site(): void
    {
        $this->mediaFile();
        $this->writer()->create('shower-doors', ['uk' => 'Двері душові']);
        $shower = $this->writer()->create('shower', ['uk' => 'Душові'], $this->schema());
        $this->writer()->publish($shower);

        // No permission of the section: choosing one is not building one.
        $rows = $this->actingAs($this->editor([]), 'cms')
            ->getJson('/api/cms/relations/'.ConfiguratorTarget::KEY.'?q=shower')
            ->assertOk()
            ->json('data');

        $this->assertSame(['shower', 'shower-doors'], array_column($rows, 'key'));
        $this->assertSame('Душові', $rows[0]['title']);
        $this->assertSame([true, false], array_column($rows, 'visible'));
    }

    private function writer(): ConfiguratorWriter
    {
        return $this->app->make(ConfiguratorWriter::class);
    }
}
