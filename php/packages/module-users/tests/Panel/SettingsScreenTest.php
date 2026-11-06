<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Users\Fields\Requirement;
use WebxUi\Users\Fields\Requirements;
use WebxUi\Users\Panel\SettingsScreen;

/**
 * The rows of «required» on the settings of visitors: the built-in fields are in the screen's
 * file, in the order of `Requirements::BUILT_IN`, with every requirement to choose from.
 */
final class SettingsScreenTest extends PanelTestCase
{
    #[Test]
    public function the_built_in_fields_have_their_row_with_every_requirement(): void
    {
        $root = $this->app->make(ScreenRegistry::class)->tree(SettingsScreen::NAME);
        $rows = array_values(array_filter(
            Tree::find($root, 'fields-card')['children'],
            static fn (array $node): bool => isset($node['name']),
        ));

        $this->assertSame(
            array_map(static fn (string $name): string => Requirements::PREFIX.$name, Requirements::BUILT_IN),
            array_slice(array_column($rows, 'name'), 0, count(Requirements::BUILT_IN)),
        );

        foreach (array_slice($rows, 0, count(Requirements::BUILT_IN)) as $row) {
            $this->assertSame('wx-segmented', $row['type']);
            $this->assertSame(Requirement::Optional->value, $row['default']);
            $this->assertSame(array_column(Requirement::cases(), 'value'), array_column($row['props']['options'], 'value'));
        }
    }
}
