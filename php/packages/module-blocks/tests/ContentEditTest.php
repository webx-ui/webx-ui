<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\ContentEdit;
use WebxUi\Blocks\Exceptions\BlocksException;

/**
 * Editing a tree by key, without rendering anything and without a database.
 */
final class ContentEditTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function tree(): array
    {
        return [
            ['key' => 'hero', 'type' => 'hero', 'values' => ['title' => 'Old', 'note' => 'kept']],
            ['key' => 'cols', 'type' => 'columns', 'values' => [
                'left' => [
                    ['key' => 'in-left', 'type' => 'text', 'values' => ['body' => 'Inside']],
                ],
            ]],
        ];
    }

    #[Test]
    public function set_changes_one_field_and_leaves_the_rest_alone(): void
    {
        $tree = ContentEdit::set($this->tree(), 'hero', ['title' => 'New']);

        $this->assertSame('New', $tree[0]['values']['title']);
        $this->assertSame('kept', $tree[0]['values']['note'], 'a field nobody mentioned keeps its value');
        $this->assertSame($this->tree()[1], $tree[1], 'nothing else is touched');
    }

    #[Test]
    public function set_reaches_a_nested_block(): void
    {
        $tree = ContentEdit::set($this->tree(), 'in-left', ['body' => 'Rewritten']);

        $this->assertSame('Rewritten', $tree[1]['values']['left'][0]['values']['body']);
    }

    #[Test]
    public function a_localized_field_takes_one_language_at_a_time(): void
    {
        $localized = static fn (string $type, string $field): bool => $field === 'title';
        $tree = [['key' => 'hero', 'type' => 'hero', 'values' => ['title' => ['en' => 'Hello', 'ru' => 'Привет']]]];

        $tree = ContentEdit::set($tree, 'hero', ['title' => 'Здравствуйте'], 'ru', $localized);

        $this->assertSame(['en' => 'Hello', 'ru' => 'Здравствуйте'], $tree[0]['values']['title']);
    }

    #[Test]
    public function a_localized_field_refuses_a_bare_string(): void
    {
        $localized = static fn (string $type, string $field): bool => $field === 'title';

        $this->expectException(BlocksException::class);
        $this->expectExceptionMessage('is localized');

        ContentEdit::set($this->tree(), 'hero', ['title' => 'One language only'], null, $localized);
    }

    #[Test]
    public function a_plain_field_refuses_a_locale(): void
    {
        $localized = static fn (string $type, string $field): bool => false;

        $this->expectException(BlocksException::class);
        $this->expectExceptionMessage('holds one value');

        ContentEdit::set($this->tree(), 'hero', ['title' => 'Bonjour'], 'fr', $localized);
    }

    #[Test]
    public function add_puts_a_node_where_it_is_told(): void
    {
        $node = ['key' => 'quote', 'type' => 'quote', 'values' => []];

        $top = ContentEdit::insert($this->tree(), $node, null, null, 'cols');
        $this->assertSame(['hero', 'quote', 'cols'], array_column($top, 'key'));

        $inside = ContentEdit::insert($this->tree(), $node, 'cols', 'left', null, 'in-left');
        $this->assertSame(['in-left', 'quote'], array_column($inside[1]['values']['left'], 'key'));
    }

    #[Test]
    public function a_parent_with_two_constructor_fields_has_to_be_told_which(): void
    {
        $tree = [['key' => 'two', 'type' => 'columns', 'values' => [
            'left' => [['key' => 'a', 'type' => 'text', 'values' => []]],
            'right' => [['key' => 'b', 'type' => 'text', 'values' => []]],
        ]]];

        $this->expectException(BlocksException::class);
        $this->expectExceptionMessage('more than one field');

        ContentEdit::insert($tree, ['key' => 'c', 'type' => 'text', 'values' => []], 'two');
    }

    #[Test]
    public function move_takes_a_node_out_and_puts_it_back_elsewhere(): void
    {
        $tree = ContentEdit::move($this->tree(), 'in-left', null, null, null, 'hero');

        $this->assertSame(['hero', 'in-left', 'cols'], array_column($tree, 'key'));
        $this->assertSame([], $tree[2]['values']['left']);
    }

    #[Test]
    public function a_node_cannot_be_moved_inside_itself(): void
    {
        $this->expectException(BlocksException::class);
        $this->expectExceptionMessage('inside itself');

        ContentEdit::move($this->tree(), 'cols', 'in-left');
    }

    #[Test]
    public function remove_takes_the_branch_with_it(): void
    {
        $tree = ContentEdit::remove($this->tree(), 'cols');

        $this->assertSame(['hero'], array_column($tree, 'key'));
        $this->assertNull(ContentEdit::find($tree, 'in-left'));
    }

    #[Test]
    public function an_unknown_key_is_refused_rather_than_ignored(): void
    {
        $this->expectException(BlocksException::class);
        $this->expectExceptionMessage('[ghost]');

        ContentEdit::set($this->tree(), 'ghost', ['title' => 'Nobody']);
    }

    #[Test]
    public function the_outline_says_what_is_on_the_page_without_the_values(): void
    {
        $outline = ContentEdit::outline($this->tree());

        $this->assertSame(
            [
                ['key' => 'hero', 'type' => 'hero', 'depth' => 0, 'label' => 'Old'],
                ['key' => 'cols', 'type' => 'columns', 'depth' => 0],
                ['key' => 'in-left', 'type' => 'text', 'depth' => 1, 'parent' => 'cols', 'label' => 'Inside'],
            ],
            $outline,
        );
    }

    #[Test]
    public function hiding_a_block_writes_the_flag_and_showing_it_takes_it_away(): void
    {
        $hidden = ContentEdit::visibility($this->tree(), 'in-left', true);

        $this->assertTrue($hidden[1]['values']['left'][0]['hidden']);
        $this->assertSame('Inside', $hidden[1]['values']['left'][0]['values']['body'], 'what is in it is untouched');

        // Back on, the node is what it was — no `hidden: false` left lying in the content.
        $shown = ContentEdit::visibility($hidden, 'in-left', false);

        $this->assertSame($this->tree(), $shown);
    }

    #[Test]
    public function hiding_a_block_nobody_has_is_an_error_like_any_other_edit(): void
    {
        $this->expectException(BlocksException::class);

        ContentEdit::visibility($this->tree(), 'nope', true);
    }

    #[Test]
    public function the_outline_says_which_blocks_are_switched_off(): void
    {
        $outline = ContentEdit::outline(ContentEdit::visibility($this->tree(), 'hero', true));

        $this->assertSame(
            ['key' => 'hero', 'type' => 'hero', 'depth' => 0, 'label' => 'Old', 'hidden' => true],
            $outline[0],
        );
        $this->assertArrayNotHasKey('hidden', $outline[1], 'the rest say nothing rather than false');
    }

    #[Test]
    public function the_revision_follows_the_content_and_nothing_else(): void
    {
        $this->assertSame(Content::revision($this->tree()), Content::revision($this->tree()));
        $this->assertNotSame(
            Content::revision($this->tree()),
            Content::revision(ContentEdit::set($this->tree(), 'hero', ['title' => 'New'])),
        );

        // Switching a block off is a change to the page like any other: two people who disagree
        // about it disagree for real.
        $this->assertNotSame(
            Content::revision($this->tree()),
            Content::revision(ContentEdit::visibility($this->tree(), 'hero', true)),
        );
    }
}
