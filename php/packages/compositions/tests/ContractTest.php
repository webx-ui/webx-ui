<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Tests;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Exceptions\CompositionException;
use WebxUi\Compositions\Inspector;
use WebxUi\Compositions\Testing\ComparesHtml;

/**
 * What a base says besides its tree (COMPOSITIONS §2.2) — `vars`, the variables its leaves get, and
 * `dispatcher`, the view that includes it — and the comparison a view turned into a composition is
 * held to (§10).
 */
final class ContractTest extends TestCase
{
    use ComparesHtml;

    #[Test]
    public function a_base_names_the_variables_of_its_leaves_and_its_dispatcher(): void
    {
        $this->card();

        $tree = $this->tree(composition: 'card');

        $this->assertSame(['card', 'related'], $tree->vars);
        $this->assertSame('fixture::card', $tree->dispatcher);
    }

    #[Test]
    public function a_variant_gets_the_variables_of_its_parent_but_not_its_dispatcher(): void
    {
        $this->card();
        $this->theme('card.wide', 'default', ['extends' => 'card', 'attrs' => ['card' => ['class' => 'is-wide']]]);
        $this->theme('card.flat', 'default', ['extends' => 'card', 'vars' => ['card'], 'dispatcher' => 'fixture::card-flat']);

        $wide = $this->tree(composition: 'card.wide');
        $flat = $this->tree(composition: 'card.flat');

        $this->assertSame(['card', 'related'], $wide->vars);
        $this->assertNull($wide->dispatcher);
        $this->assertSame(['card'], $flat->vars);
        $this->assertSame('fixture::card-flat', $flat->dispatcher);
    }

    #[Test]
    public function a_base_without_them_says_nothing(): void
    {
        $tree = $this->tree();

        $this->assertSame([], $tree->vars);
        $this->assertNull($tree->dispatcher);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function refused(): array
    {
        return [
            'vars in a patch' => [['vars' => ['card']], 'blog.article', 'vars is said by the base of a composition, not a patch'],
            'dispatcher in a patch' => [['dispatcher' => 'x::y'], 'default', 'dispatcher is said by the base of a composition, not a patch'],
            'a dollar' => [['root' => ['id' => 'card', 'tag' => 'div'], 'vars' => ['$card']], 'default', 'vars lists the names of variables without $'],
            'not a list' => [['root' => ['id' => 'card', 'tag' => 'div'], 'vars' => ['card' => true]], 'default', 'vars lists the names of variables'],
            'an empty dispatcher' => [['root' => ['id' => 'card', 'tag' => 'div'], 'dispatcher' => ''], 'default', 'dispatcher names a view'],
        ];
    }

    /** @param array<string, mixed> $file */
    #[Test]
    #[DataProvider('refused')]
    public function the_reader_refuses_a_contract_that_cannot_be_read_as_meant(array $file, string $handle, string $message): void
    {
        // A base is a file of a composition of its own; a patch patches the fixtures' layout.
        $composition = isset($file['root']) ? 'card' : 'layout';
        $this->theme($composition, $handle, $file);

        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage($message);

        $this->tree($handle, $composition);
    }

    #[Test]
    public function show_prints_them_in_the_tree_the_overview_and_json(): void
    {
        $this->card();

        Artisan::call('webx:composition:show', ['composition' => 'card']);
        $tree = Artisan::output();

        Artisan::call('webx:composition:show');
        $overview = Artisan::output();

        Artisan::call('webx:composition:show', ['composition' => 'card', '--json' => true]);
        $json = json_decode(Artisan::output(), true);

        Artisan::call('webx:composition:show', ['--json' => true]);
        $all = array_column((array) json_decode(Artisan::output(), true)['compositions'], null, 'name');

        $this->assertStringContainsString("vars: \$card, \$related\ndispatcher: fixture::card", $tree);
        $this->assertStringContainsString("  vars: \$card, \$related\n  dispatcher: fixture::card", $overview);
        $this->assertSame(['card', 'related'], $json['vars']);
        $this->assertSame('fixture::card', $json['dispatcher']);
        $this->assertSame(['card', 'related'], $all['card']['vars']);
        $this->assertSame([], $all['layout']['vars']);
        $this->assertNull($all['layout']['dispatcher']);
    }

    #[Test]
    public function html_is_the_same_up_to_the_whitespace_between_tags(): void
    {
        $before = "<article class=\"a\">\n    <h1>Title</h1>\n\n        <p>One two</p>\n</article>\n";

        self::assertSameHtml($before, '<article class="a"><h1>Title</h1>    <p>One two</p></article>');
        $this->assertSame('<p>a</p><p>b</p>', self::spacelessHtml("\n<p>a</p>\n    <p>b</p>\n"));
        self::assertSameHtml("<address>\n                    Street\n            </address>", "<address>\n    Street\n</address>");
    }

    /** @return array<string, array{string}> */
    public static function different(): array
    {
        return [
            'a space inside text' => ['<p>One  two</p>'],
            'a space lost between a word and a tag' => ['<p>One<b>two</b></p>'],
            'a space lost at a tag' => ['<p>One two </p>'],
            'an attribute' => ['<p class="x">One two</p>'],
        ];
    }

    #[Test]
    #[DataProvider('different')]
    public function anything_but_that_whitespace_is_a_difference(string $after): void
    {
        $before = str_contains($after, '<b>') ? '<p>One <b>two</b></p>' : '<p>One two</p>';

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('beyond the whitespace between tags');

        self::assertSameHtml($before, $after);
    }

    #[Test]
    public function the_contract_does_not_change_what_is_compiled(): void
    {
        $this->theme('card', 'default', $this->base());
        $without = $this->app->make(Inspector::class)->blade('card');

        $this->card();
        $this->app->forgetInstance(Inspector::class);

        $this->assertSame($without, $this->app->make(Inspector::class)->blade('card'));
    }

    /** A composition of the local theme with a contract: `card`. */
    private function card(): void
    {
        $this->theme('card', 'default', [...$this->base(), 'vars' => ['card', 'related'], 'dispatcher' => 'fixture::card']);
    }

    /** @return array<string, mixed> */
    private function base(): array
    {
        return ['root' => ['id' => 'card', 'tag' => 'div', 'attrs' => ['class' => 'card'], 'children' => [['id' => 'card.title', 'html' => '<h2>Card</h2>']]]];
    }
}
