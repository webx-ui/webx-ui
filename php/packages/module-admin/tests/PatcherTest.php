<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Admin\Screens\Patcher;
use WebxUi\Admin\Screens\ScreenException;
use WebxUi\Admin\Screens\Tree;

/** The same operations `applyPatch` runs on the client, with the same outcomes. */
final class PatcherTest extends PhpUnitTestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function tree(): array
    {
        return [
            [
                'id' => 'tabs',
                'type' => 'wx-tabs',
                'children' => [
                    [
                        'id' => 'general',
                        'type' => 'wx-tab',
                        'label' => 'General',
                        'children' => [
                            ['id' => 'name', 'type' => 'wx-input', 'name' => 'name'],
                            ['id' => 'logo', 'type' => 'wx-media', 'name' => 'logo', 'props' => ['aspect' => '1/1']],
                        ],
                    ],
                    ['id' => 'seo', 'type' => 'wx-tab', 'label' => 'SEO', 'children' => []],
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $root
     * @return list<string>
     */
    private function childrenOf(array $root, string $id): array
    {
        $node = Tree::find($root, $id);

        return array_map(static fn (array $child): string => (string) $child['id'], Tree::children($node ?? []));
    }

    #[Test]
    public function adds_at_the_end_by_default_and_at_first_before_and_after_on_request(): void
    {
        $root = Patcher::apply('s.x', $this->tree(), [
            ['op' => 'add', 'target' => 'general', 'node' => ['id' => 'z', 'type' => 'wx-text']],
            ['op' => 'add', 'target' => 'general', 'node' => ['id' => 'a', 'type' => 'wx-text'], 'position' => 'first'],
            ['op' => 'add', 'target' => 'general', 'node' => ['id' => 'm', 'type' => 'wx-text'], 'position' => 'before:logo'],
            ['op' => 'add', 'target' => 'general', 'node' => ['id' => 'n', 'type' => 'wx-text'], 'position' => 'after:logo'],
        ]);

        $this->assertSame(['a', 'name', 'm', 'logo', 'n', 'z'], $this->childrenOf($root, 'general'));
    }

    #[Test]
    public function removes_replaces_moves_and_sets(): void
    {
        $root = Patcher::apply('s.x', $this->tree(), [
            ['op' => 'remove', 'target' => 'name'],
            ['op' => 'replace', 'target' => 'logo', 'node' => ['id' => 'logo-path', 'type' => 'wx-input', 'name' => 'logo']],
            ['op' => 'move', 'target' => 'seo', 'position' => 'first'],
            ['op' => 'move', 'target' => 'logo-path', 'to' => 'seo'],
            ['op' => 'set', 'target' => 'logo-path', 'label' => 'Logo', 'props' => ['rows' => 2]],
        ]);

        $this->assertSame(['seo', 'general'], $this->childrenOf($root, 'tabs'));
        $this->assertSame(['logo-path'], $this->childrenOf($root, 'seo'));
        $this->assertSame([], $this->childrenOf($root, 'general'));
        $this->assertSame('Logo', Tree::find($root, 'logo-path')['label'] ?? null);
        $this->assertSame(['rows' => 2], Tree::find($root, 'logo-path')['props'] ?? null);
    }

    #[Test]
    public function set_merges_props_instead_of_replacing_them(): void
    {
        $root = Patcher::apply('s.x', $this->tree(), [
            ['op' => 'set', 'target' => 'logo', 'props' => ['accept' => 'image']],
        ]);

        $this->assertSame(['aspect' => '1/1', 'accept' => 'image'], Tree::find($root, 'logo')['props'] ?? null);
    }

    #[Test]
    public function a_missing_target_throws_with_the_index_and_the_operation(): void
    {
        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('Patch [1] (remove) on [s.x] cannot be applied: target "ghost" not found');

        Patcher::apply('s.x', $this->tree(), [
            ['op' => 'remove', 'target' => 'logo'],
            ['op' => 'remove', 'target' => 'ghost'],
        ]);
    }

    #[Test]
    public function a_duplicate_id_and_a_missing_anchor_are_refused(): void
    {
        try {
            Patcher::apply('s.x', $this->tree(), [
                ['op' => 'add', 'target' => 'seo', 'node' => ['id' => 'name', 'type' => 'wx-text']],
            ]);
            $this->fail('A duplicate id should have been refused.');
        } catch (ScreenException $exception) {
            $this->assertStringContainsString('id "name" already exists', $exception->getMessage());
        }

        try {
            Patcher::apply('s.x', $this->tree(), [
                ['op' => 'move', 'target' => 'name', 'position' => 'before:ghost'],
            ]);
            $this->fail('A missing anchor should have been refused.');
        } catch (ScreenException $exception) {
            $this->assertStringContainsString('no sibling "ghost" to insert before', $exception->getMessage());
        }
    }

    #[Test]
    public function a_node_cannot_be_moved_into_its_own_subtree(): void
    {
        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('cannot move "tabs" into itself');

        Patcher::apply('s.x', $this->tree(), [['op' => 'move', 'target' => 'tabs', 'to' => 'general']]);
    }

    #[Test]
    public function the_input_is_left_untouched(): void
    {
        $input = $this->tree();
        Patcher::apply('s.x', $input, [['op' => 'remove', 'target' => 'logo']]);

        $this->assertNotNull(Tree::find($input, 'logo'));
    }
}
