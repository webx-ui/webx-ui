<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\Rendering\Values;

/**
 * A block field with a `default` is drawn with it in the editor, so its template reads it too
 * until somebody sets the field.
 */
final class FieldDefaultsTest extends TestCase
{
    #[Test]
    public function an_unset_field_reads_its_default_and_a_set_one_its_value(): void
    {
        $type = new BlockType(
            slug: 'popup',
            title: 'Popup',
            description: null,
            icon: null,
            group: 'content',
            sort: 0,
            allow: null,
            allowedIn: null,
            maxPerEntity: null,
            enabled: true,
            versionId: 1,
            version: 1,
            schema: [
                ['id' => 'shown', 'type' => 'wx-switch', 'default' => true],
                ['id' => 'heading', 'type' => 'wx-input', 'localized' => true, 'default' => 'Hello'],
                ['id' => 'note', 'type' => 'wx-input'],
            ],
            template: '',
            styles: '',
            script: null,
            sample: [],
        );

        $values = $this->app->make(Values::class);

        $this->assertSame(['shown' => true, 'heading' => 'Hello'], $values->resolve($type, []));
        $this->assertSame(
            ['shown' => false, 'heading' => 'Hi', 'note' => 'x'],
            $values->resolve($type, ['shown' => false, 'heading' => ['en' => 'Hi'], 'note' => 'x']),
        );
    }
}
