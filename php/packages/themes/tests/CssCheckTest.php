<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Checks\CssFinding;
use WebxUi\Themes\Checks\CssProblem;
use WebxUi\Themes\TokenType;
use WebxUi\Themes\Vocabulary;

/** CSS knows only tokens (spec §8): each kind of finding, what is allowed, and where it is said. */
final class CssCheckTest extends PlainTestCase
{
    #[Test]
    public function a_literal_colour_in_any_form_is_a_finding(): void
    {
        $css = <<<'CSS'
            .a {
              color: #fff;
              background: rgb(0 0 0 / 0.5);
              border: 1px solid black;
              outline-color: oklch(70% 0.1 200);
              box-shadow: 0 0 2px hsla(0, 0%, 0%, 0.2);
              fill: RebeccaPurple;
              --webx-slider-arrow: white;
              --webx-slider-gap: #0e6b5c;
            }
            CSS;

        $this->assertSame([
            [CssProblem::LiteralColor, 2, '#fff'],
            [CssProblem::LiteralColor, 3, 'rgb(…)'],
            [CssProblem::LiteralColor, 4, 'black'],
            [CssProblem::LiteralColor, 5, 'oklch(…)'],
            [CssProblem::LiteralColor, 6, 'hsla(…)'],
            [CssProblem::LiteralColor, 7, 'RebeccaPurple'],
            [CssProblem::LiteralColor, 8, 'white'],
            [CssProblem::LiteralColor, 9, '#0e6b5c'],
        ], self::found($css));
    }

    #[Test]
    public function what_is_nobodys_colour_is_allowed(): void
    {
        $css = <<<'CSS'
            .a {
              color: currentColor;
              background: transparent;
              border-color: inherit;
              outline-color: initial;
              caret-color: unset;
              color: var(--site-color-text);
              background: color-mix(in srgb, var(--site-color-accent) 20%, transparent);
              transition: color var(--site-duration) var(--site-easing), background-color var(--site-duration);
              animation: red-pulse 1s;
              grid-area: tan;
              white-space: nowrap;
              background-image: url("icons.svg#red");
              content: "#fff";
              --webx-slider-gap: var(--site-space-4);
            }
            CSS;

        $this->assertSame([], self::found($css));
    }

    #[Test]
    public function a_literal_font_family_is_a_finding_and_a_generic_one_is_not(): void
    {
        $css = <<<'CSS'
            .a { font-family: "Inter", sans-serif; }
            .b { font-family: Georgia, serif; }
            .c { font: italic 600 1rem/1.4 Open Sans, sans-serif; }
            .d { font-family: ui-monospace, monospace; }
            .e { font: inherit; }
            .f { font: italic bold var(--site-font-size-2)/1.2 var(--site-font-heading); }
            .g { font-family: var(--site-font-body); }
            .h { font-family: system-ui, sans-serif; }
            CSS;

        $this->assertSame([
            [CssProblem::LiteralFont, 1, '"Inter"'],
            [CssProblem::LiteralFont, 2, 'Georgia'],
            [CssProblem::LiteralFont, 3, 'Open Sans'],
        ], self::found($css));
    }

    #[Test]
    public function a_token_not_in_the_vocabulary_and_a_fallback_are_findings(): void
    {
        $css = <<<'CSS'
            .a {
              color: var(--site-color-olive);
              padding: var(--site-space-20);
              border-color: var(--site-color-border, #ccc);
              gap: var(--webx-slider-gap);
              grid-template-columns: repeat(var(--team-columns, 3), 1fr);
              outline-color: var(--team-ring, red);
            }
            CSS;

        $this->assertSame([
            [CssProblem::UnknownToken, 2, '--site-color-olive'],
            [CssProblem::UnknownToken, 3, '--site-space-20'],
            [CssProblem::Fallback, 4, 'var(--site-color-border, …)'],
            [CssProblem::LiteralColor, 4, '#ccc'],
            // A block's own variable keeps its default; a colour in that default is still a colour.
            [CssProblem::LiteralColor, 7, 'red'],
        ], self::found($css));

        // A name the chain adds to the vocabulary is a token like any other.
        $chain = new Vocabulary([...Vocabulary::base()->tokens, 'color-olive' => TokenType::Color]);
        $this->assertSame([], CssCheck::check('.a { color: var(--site-color-olive); }', $chain));
    }

    #[Test]
    public function only_a_media_query_by_width_is_a_finding(): void
    {
        $css = <<<'CSS'
            @media (min-width: 40rem) { .a { gap: 0; } }
            @media screen and (max-width: 600px) { .a { gap: 0; } }
            @media (prefers-reduced-motion: reduce) { .a { transition: none; } }
            @media (scripting: none), print { .a { display: none; } }
            @container (min-width: 30rem) { .a { gap: 0; } }
            CSS;

        $this->assertSame([
            [CssProblem::MediaWidth, 1, '@media (min-width: 40rem)'],
            [CssProblem::MediaWidth, 2, '@media screen and (max-width: 600px)'],
        ], self::found($css));
    }

    #[Test]
    public function comments_font_faces_and_properties_are_not_read(): void
    {
        $css = <<<'CSS'
            /* color: #fff; font-family: Georgia; @media (min-width: 1px) {} */
            @font-face { font-family: "Inter"; src: url(inter.woff2) format("woff2"); }
            @property --webx-tint { syntax: "<color>"; inherits: true; initial-value: black; }
            .a {
              color: var(--site-color-text); /* not #000 */
            }
            CSS;

        $this->assertSame([], self::found($css));
    }

    #[Test]
    public function the_widgets_own_variables_are_allowed_and_nesting_is_read(): void
    {
        $css = <<<'CSS'
            :where(:root) {
              --webx-slider-gap: var(--site-space-4);
              --webx-header-height: 4rem;
            }

            .webx-slider {
              gap: var(--webx-slider-gap);
              color: var(--site-color-text);

              &:hover {
                color: red;
              }
            }
            CSS;

        $this->assertSame([[CssProblem::LiteralColor, 11, 'red']], self::found($css));
    }

    #[Test]
    public function the_message_says_what_and_why(): void
    {
        $finding = CssCheck::check('.a { color: #fff; }', Vocabulary::base())[0];

        $this->assertInstanceOf(CssFinding::class, $finding);
        $this->assertStringStartsWith('literal colour #fff — ', $finding->message());
        $this->assertStringContainsString('var(--site-color-', $finding->message());
    }

    /** @return list<array{CssProblem, int, string}> */
    private static function found(string $css): array
    {
        return array_map(
            static fn (CssFinding $finding): array => [$finding->problem, $finding->line, $finding->subject],
            CssCheck::check($css, Vocabulary::base()),
        );
    }
}
