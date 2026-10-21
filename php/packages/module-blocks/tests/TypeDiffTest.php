<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Panel\TypeDiff;

/**
 * What an import shows instead of overwriting (BLOCK_SCHEMAS §4.1): fields by key, layout left
 * out, `+` the file's and `−` the site's; text by lines; the row by its keys; only what the
 * document says.
 */
final class TypeDiffTest extends TestCase
{
    #[Test]
    public function the_same_type_is_no_difference(): void
    {
        $block = $this->site();

        $this->assertSame([], TypeDiff::of($this->document(), $block));
        $this->assertSame([], TypeDiff::of(['slug' => 'card', 'title' => 'Card'], $block), 'what the file leaves out it says nothing about');
    }

    #[Test]
    public function fields_are_compared_by_key_and_layout_is_not_a_field(): void
    {
        $diff = TypeDiff::of($this->document(['schema' => [
            ['type' => 'wx-row', 'children' => [
                ['id' => 'title', 'type' => 'wx-input', 'label' => 'Title', 'localized' => true],
            ]],
            ['id' => 'items', 'type' => 'wx-list', 'children' => [['id' => 'name', 'type' => 'wx-input']]],
            ['id' => 'subtitle', 'type' => 'wx-input'],
        ]]), $this->site());

        $this->assertSame(['subtitle'], $diff['fields']['added'] ?? null);
        $this->assertSame(['badge'], $diff['fields']['removed'] ?? null);
        $this->assertSame([
            ['key' => 'title', 'what' => 'localized'],
            ['key' => 'items', 'what' => 'wx-repeater → wx-list'],
        ], $diff['fields']['changed'] ?? null);
        $this->assertArrayNotHasKey('template', $diff);

        $this->assertSame(
            [['fields', '+ subtitle  − badge  ~ title (localized)  ~ items (wx-repeater → wx-list)']],
            TypeDiff::describe($diff),
        );
    }

    #[Test]
    public function texts_count_the_lines_the_file_adds_and_removes_and_the_row_names_its_keys(): void
    {
        $diff = TypeDiff::of($this->document([
            'title' => 'Card, again',
            'icon' => 'star',
            'template' => "<div data-wx-block=\"card\">\n<h2>{{ \$title }}</h2>\n<p>new</p>\n</div>",
            'styles' => ".b-card { padding: 1em; }\n.b-card h2 { margin: 0; }\n",
        ]), $this->site());

        $this->assertSame(['added' => 1, 'removed' => 0], $diff['template'] ?? null);
        $this->assertSame(['added' => 1, 'removed' => 0], $diff['styles'] ?? null);
        $this->assertSame(['title', 'icon'], $diff['row'] ?? null);
        $this->assertSame(['template', 'differs (+1 −0 lines)'], TypeDiff::describe($diff)[0]);
        $this->assertSame(['styles', 'differ (+1 −0 lines)'], TypeDiff::describe($diff)[1]);
        $this->assertSame(['row', 'title, icon'], TypeDiff::describe($diff)[2]);
    }

    #[Test]
    public function an_open_draft_is_the_side_of_the_site(): void
    {
        $block = $this->site();
        $block->saveVersion(['styles' => '.b-card { margin: 0; }']);

        $diff = TypeDiff::of($this->document(), $block->refresh());

        $this->assertSame(['added' => 1, 'removed' => 1], $diff['styles'] ?? null);
    }

    private function site(): Block
    {
        $block = Block::query()->create(['slug' => 'card', 'title' => 'Card', 'icon' => 'square']);
        $block->saveVersion([
            'schema' => $this->document()['schema'],
            'template' => "<div data-wx-block=\"card\">\n<h2>{{ \$title }}</h2>\n</div>",
            'styles' => ".b-card { padding: 1em; }\n",
        ]);
        $block->publish();

        return $block->refresh();
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function document(array $with = []): array
    {
        return $with + [
            'slug' => 'card',
            'title' => 'Card',
            'icon' => 'square',
            'schema' => [
                ['id' => 'title', 'type' => 'wx-input', 'label' => 'Title'],
                ['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'name', 'type' => 'wx-input']]],
                ['id' => 'badge', 'type' => 'wx-input'],
            ],
            'template' => "<div data-wx-block=\"card\">\n<h2>{{ \$title }}</h2>\n</div>",
            'styles' => ".b-card { padding: 1em; }\n",
        ];
    }
}
