<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class BlueprintMacroTest extends TestCase
{
    #[Test]
    public function the_service_provider_registers_the_blueprint_macros(): void
    {
        $this->assertTrue(Blueprint::hasMacro('nestedSet'));
        $this->assertTrue(Blueprint::hasMacro('dropNestedSet'));
    }

    #[Test]
    public function the_macro_adds_the_same_columns_as_the_helper(): void
    {
        Schema::create('macro_probe', function (Blueprint $table): void {
            $table->id();
            // Through __call on purpose: a macro does not exist as far as static analysis is
            // concerned, and this is the one place that proves the macro reaches the helper.
            $table->__call('nestedSet', []);
        });

        foreach (['parent_id', 'lft', 'rgt', 'depth'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('macro_probe', $column),
                "The macro did not add [{$column}].",
            );
        }
    }
}
