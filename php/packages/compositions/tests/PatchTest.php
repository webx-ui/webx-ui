<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Compositions;

/**
 * The operations of a patch (COMPOSITIONS §3), each on the fixture layout: what it does, and what
 * it says instead of doing when it cannot be applied as written.
 */
final class PatchTest extends TestCase
{
    #[Test]
    public function without_a_patch_the_modules_add_their_pieces_by_id_order(): void
    {
        $this->assertSame(
            ['html', 'html>head', 'head>meta.charset', 'head>head.slot', 'head>head.stack', 'html>body', 'body>header', 'body>main', 'main>content', 'main>a.note', 'main>b.note', 'body>footer'],
            $this->ids($this->tree()),
        );
        $this->assertSame([], $this->findings($this->tree()));
    }

    /** §3.1: true is an attribute without a value, false and null take one away, class is added to. */
    #[Test]
    public function attrs_set_a_bare_attribute_add_to_the_class_and_take_attributes_away(): void
    {
        $this->theme('layout', 'default', ['attrs' => [
            'html' => ['data-webx-smooth-scroll' => true],
            'body' => ['class' => 'is-wide site', 'data-x' => 'a "quoted" value'],
            'main' => ['class' => ['remove' => 'site-main'], 'hidden' => true],
        ]]);
        $this->config('layout', 'default', ['attrs' => ['main' => ['hidden' => false], 'body' => ['data-x' => null]]]);

        $html = $this->page();

        $this->assertStringContainsString('<html lang="en" data-webx-smooth-scroll>', $html);
        $this->assertStringContainsString('<body class="site is-wide">', $html);
        $this->assertStringContainsString('<main id="content">', $html);
    }

    #[Test]
    public function an_attribute_value_is_escaped_and_never_blade(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['body' => ['data-x' => 'a "quoted" {{ $secret }} @if']]]);

        $this->assertStringContainsString('<body class="site" data-x="a &quot;quoted&quot; {{ $secret }} @if">', $this->page());
    }

    #[Test]
    public function class_false_takes_the_whole_attribute_away(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['body' => ['class' => false]]]);

        $this->assertStringContainsString("<body>\n", $this->page());
    }

    /** §3.1: lang is the page's, id="content" is where "Skip to content" leads. */
    #[Test]
    public function the_reserved_attributes_stay_and_are_said(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['html' => ['lang' => 'fr'], 'main' => ['id' => 'page']]]);

        $html = $this->page();

        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('<main id="content" class="site-main">', $html);
        $this->assertCount(2, $this->findings($this->tree()));
        $this->assertStringContainsString('attrs html.lang — lang is the language of the page', $this->findings($this->tree())[0]);
    }

    #[Test]
    public function attrs_of_a_node_without_attributes_are_a_finding(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['header' => ['class' => 'x'], 'nowhere' => ['class' => 'y']]]);

        $this->assertSame([
            'theme/compositions/layout/default.json: attrs header — a view has no attributes — replace changes what it is',
            'theme/compositions/layout/default.json: attrs nowhere — no node with this id',
        ], $this->relative($this->findings($this->tree())));
    }

    #[Test]
    public function add_puts_a_node_before_after_first_or_last(): void
    {
        $this->theme('layout', 'default', ['add' => [
            ['id' => 'promo.first', 'to' => 'body', 'first' => true, 'view' => 'components.promo'],
            ['id' => 'promo.before', 'to' => 'main', 'before' => 'content', 'html' => '<hr>'],
            ['id' => 'promo.after', 'to' => 'body', 'after' => 'header', 'tag' => 'div', 'attrs' => ['class' => 'band'], 'children' => [
                ['id' => 'promo.inner', 'html' => '<b>Inner</b>'],
            ]],
            ['id' => 'promo.last', 'to' => 'body', 'html' => '<i>Last</i>'],
        ]]);

        $this->assertSame(
            ['body>promo.first', 'body>header', 'body>promo.after', 'promo.after>promo.inner', 'body>main', 'main>promo.before', 'main>content', 'main>a.note', 'main>b.note', 'body>footer', 'body>promo.last'],
            array_values(array_filter($this->ids($this->tree()), static fn (string $id): bool => str_starts_with($id, 'body>') || str_starts_with($id, 'main>') || str_starts_with($id, 'promo.'))),
        );
        $this->assertStringContainsString("    <div class=\"band\">\n        <b>Inner</b>\n    </div>\n", $this->page());
    }

    /** §3.3: a module's piece is not lost because a theme moved its neighbour. */
    #[Test]
    public function add_beside_a_neighbour_that_is_not_there_puts_the_node_last_and_says_so(): void
    {
        $this->theme('layout', 'default', ['add' => [['id' => 'x', 'to' => 'main', 'after' => 'gone', 'html' => '<hr>']]]);

        $this->assertSame('main>x', array_slice(array_values(array_filter($this->ids($this->tree()), static fn (string $id): bool => str_starts_with($id, 'main>'))), -1)[0]);
        $this->assertSame(['theme/compositions/layout/default.json: add x — no "gone" inside "main": put last'], $this->relative($this->findings($this->tree())));
    }

    #[Test]
    public function add_into_nothing_into_a_leaf_or_under_a_taken_id_is_a_finding(): void
    {
        $this->theme('layout', 'default', ['add' => [
            ['id' => 'x', 'to' => 'nowhere', 'html' => '<hr>'],
            ['id' => 'y', 'to' => 'header', 'html' => '<hr>'],
            ['id' => 'footer', 'to' => 'body', 'html' => '<hr>'],
        ]]);

        $this->assertSame([
            'theme/compositions/layout/default.json: add x — no node "nowhere" to add into',
            'theme/compositions/layout/default.json: add y — "header" is a view, not a container',
            'theme/compositions/layout/default.json: add footer — "footer" is already in the tree — replace changes a node',
        ], $this->relative($this->findings($this->tree())));
        $this->assertStringContainsString('<footer>Footer</footer>', $this->page());
    }

    #[Test]
    public function remove_takes_a_node_away_but_not_a_protected_one(): void
    {
        $this->theme('layout', 'default', ['remove' => ['footer', 'a.note', 'content', 'main', 'nothing']]);

        $html = $this->page();

        $this->assertStringNotContainsString('<footer>', $html);
        $this->assertStringNotContainsString('>a</aside>', $html);
        $this->assertStringContainsString('<p>Body</p>', $html);
        $this->assertSame([
            'theme/compositions/layout/default.json: remove content — a protected node of the base stays',
            'theme/compositions/layout/default.json: remove main — a protected node of the base stays',
            'theme/compositions/layout/default.json: remove nothing — no node with this id',
        ], $this->relative($this->findings($this->tree())));
    }

    /** A lower layer took the node away already — an extending layout asks for the same: a note, not a finding. */
    #[Test]
    public function remove_of_a_node_removed_already_is_a_note(): void
    {
        $this->theme('layout', 'default', ['remove' => ['footer']]);
        $this->config('layout', 'default', ['remove' => ['footer']]);

        $findings = $this->tree()->findings;

        $this->assertCount(1, $findings);
        $this->assertTrue($findings[0]->note);
        $this->assertStringEndsWith('removed already by theme/compositions/layout/default.json', $this->relative($this->findings($this->tree()))[0]);
    }

    #[Test]
    public function move_puts_a_node_elsewhere_or_among_its_siblings(): void
    {
        $this->theme('layout', 'default', ['move' => [
            ['id' => 'footer', 'to' => 'main', 'first' => true],
            ['id' => 'b.note', 'before' => 'a.note'],
        ]]);

        $this->assertSame(
            ['main>footer', 'main>content', 'main>b.note', 'main>a.note'],
            array_values(array_filter($this->ids($this->tree()), static fn (string $id): bool => str_starts_with($id, 'main>'))),
        );
    }

    /** §3.4 and §3.6: a node into itself, into a leaf, a protected node out of its place. */
    #[Test]
    public function move_in_a_circle_out_of_a_protected_place_or_of_nothing_is_a_finding(): void
    {
        $this->theme('layout', 'default', ['add' => [
            ['id' => 'box', 'to' => 'body', 'tag' => 'div', 'children' => [['id' => 'box.inner', 'tag' => 'div']]],
            ['id' => 'grid', 'to' => 'main', 'tag' => 'div'],
        ], 'move' => [
            ['id' => 'box', 'to' => 'box.inner'],
            ['id' => 'footer', 'to' => 'header'],
            ['id' => 'content', 'to' => 'body'],
            ['id' => 'body', 'to' => 'head'],
            ['id' => 'ghost', 'last' => true],
            ['id' => 'content', 'to' => 'grid'],
        ]]);

        $tree = $this->tree();

        $this->assertSame([
            'theme/compositions/layout/default.json: move box — "box.inner" is inside the node itself',
            'theme/compositions/layout/default.json: move footer — "header" is a view, not a container',
            'theme/compositions/layout/default.json: move content — a protected node of the base stays inside "main"',
            'theme/compositions/layout/default.json: move body — a protected node of the base does not move',
            'theme/compositions/layout/default.json: move ghost — no node with this id',
        ], $this->relative($this->findings($tree)));

        // Inside its main it does move: a grid of the account's page (§3.7).
        $this->assertContains('grid>content', $this->ids($tree));
    }

    /** §3.5: the same place and id, another kind; what other layers added inside goes, and is said. */
    #[Test]
    public function replace_changes_what_a_node_is_in_its_place(): void
    {
        $this->theme('layout', 'default', [
            'add' => [['id' => 'aside', 'to' => 'body', 'after' => 'main', 'tag' => 'aside']],
            'replace' => ['footer' => ['component' => 'note', 'attrs' => ['from' => 'footer']]],
        ]);
        $this->config('layout', 'default', ['replace' => ['aside' => ['view' => 'components.promo'], 'main' => ['html' => '<hr>']]]);

        $html = $this->page();

        $this->assertStringContainsString("    </main>\n    <div class=\"promo\">Promo</div>\n    <aside class=\"note\">footer</aside>\n</body>", $html);
        $this->assertSame(['config/webx-compositions.php[patches][layout][default]: replace main — a protected node of the base is not replaced'], $this->findings($this->tree()));
    }

    #[Test]
    public function replace_says_which_pieces_of_other_layers_it_drops(): void
    {
        $this->theme('layout', 'default', ['add' => [['id' => 'a.note.inside', 'to' => 'a.note', 'html' => '<hr>']]]);
        $this->config('layout', 'blog.article', ['replace' => ['a.note' => ['view' => 'components.promo']]]);

        $notes = array_values(array_filter($this->tree('blog.article')->findings, static fn ($finding): bool => $finding->note));

        $this->assertCount(1, $notes);
        $this->assertSame('drops "a.note.inside" added by theme', str_replace($this->local, 'theme', $notes[0]->message));
    }

    /** §2.3: a named slot of a component is a `tag` node `x-slot:<name>` among its children — and only there. */
    #[Test]
    public function a_named_slot_is_a_tag_inside_a_component_and_left_out_elsewhere(): void
    {
        $this->theme('layout', 'default', [
            'add' => [
                ['id' => 'boxed', 'to' => 'body', 'after' => 'main', 'component' => 'box', 'children' => [
                    ['id' => 'boxed.title', 'tag' => 'x-slot:title', 'children' => [['id' => 'boxed.words', 'html' => 'Title']]],
                    ['id' => 'boxed.body', 'html' => '<p>Inside</p>'],
                ]],
                ['id' => 'stray', 'to' => 'body', 'tag' => 'x-slot:title'],
            ],
        ]);

        $html = $this->page();
        $tree = $this->tree();

        $this->assertStringContainsString('<section class="box"><h2>Title</h2><p>Inside</p></section>', $html);
        $this->assertStringNotContainsString('x-slot', $html);
        $this->assertContains('boxed>boxed.title', $this->ids($tree));
        $this->assertContains(
            'theme: tag stray — x-slot:title is a slot of a component and stands only in one: left out',
            $this->relative(array_map(strval(...), $this->app->make(Compositions::class)->findings())),
        );
    }

    /** §3.2: props merge by key; null gives the prop back to the component's default. */
    #[Test]
    public function props_of_a_component_are_set_by_a_patch(): void
    {
        $this->theme('layout', 'default', ['props' => ['a.note' => ['limit' => 5], 'header' => ['x' => 1]]]);

        $this->assertStringContainsString('<aside class="note">a5</aside>', $this->page());
        $this->assertSame(['theme/compositions/layout/default.json: props header — a view has no props'], $this->relative($this->findings($this->tree())));

        $this->config('layout', 'default', ['props' => ['a.note' => ['limit' => null]]]);

        $this->assertStringContainsString('<aside class="note">a</aside>', $this->page());
    }

    /** §3.6: add → move → replace → attrs → props → remove, whatever order the keys are written in. */
    #[Test]
    public function the_operations_of_a_file_apply_in_their_fixed_order(): void
    {
        $this->theme('layout', 'default', '{"remove": ["footer"], "attrs": {"late": {"class": "seen"}}, "add": [{"id": "late", "to": "body", "after": "footer", "tag": "div"}]}');

        $html = $this->page();

        $this->assertStringContainsString("    <div class=\"seen\"></div>\n</body>", $html);
        $this->assertStringNotContainsString('<footer>', $html);
        $this->assertSame([], $this->findings($this->tree()));
    }

    /**
     * @param  list<string>  $findings
     * @return list<string>
     */
    private function relative(array $findings): array
    {
        return array_map(fn (string $finding): string => str_replace($this->local, 'theme', $finding), $findings);
    }
}
