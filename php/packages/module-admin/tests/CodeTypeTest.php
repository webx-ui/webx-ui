<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\ScreenValues;

/**
 * `wx-code` through the path a screen saves by (#125): a typed name is brought to a code rather
 * than refused, and a code kept per language is left to the model wherever its language matters.
 */
final class CodeTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Screens::register('forms.codes', [['id' => 'card', 'type' => 'wx-card', 'children' => [
            ['id' => 'code', 'type' => 'wx-code', 'name' => 'code', 'props' => ['maxlength' => 16]],
            ['id' => 'toggle', 'type' => 'wx-code', 'name' => 'toggle', 'localized' => true, 'props' => ['maxlength' => 16]],
        ]]]);
    }

    #[Test]
    public function a_plain_code_is_brought_to_its_shape_and_cut(): void
    {
        $stored = $this->app->make(ScreenValues::class)->validate('forms.codes', ['code' => '  Brake Pads & Discs, all of them ']);

        $this->assertSame('brake-pads-discs', $stored['code']);
    }

    #[Test]
    public function a_localized_code_brings_latin_and_leaves_any_other_alphabet_to_the_model(): void
    {
        $values = $this->app->make(ScreenValues::class);

        $this->assertSame(['en' => 'yes-please'], $values->validate('forms.codes', ['toggle' => ['en' => 'Yes Please!']])['toggle']);

        // Which language this is the type is not told: read the main language's way it could be
        // kept wrong, so the model brings it in its own.
        $this->assertSame(['en' => 'Так, будь ласка'], $values->validate('forms.codes', ['toggle' => ['en' => 'Так, будь ласка']])['toggle']);
    }
}
