<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Panel\Lints;

final class LintsTest extends TestCase
{
    #[Test]
    public function a_clean_block_says_nothing(): void
    {
        $styles = <<<'CSS'
        /* a comment with h2 { } inside */
        .b-hero { display: grid; }
        .b-hero__title, .b-hero__text { margin: 0; }
        .b-hero:hover .b-hero__cta { color: red; }
        @container (max-width: 700px) {
          .b-hero { grid-template-columns: 1fr; }
        }
        @keyframes b-hero-in { from { opacity: 0 } 50% { opacity: .5 } to { opacity: 1 } }
        .b-hero { &__art { border-radius: 8px; } }
        CSS;

        $this->assertSame([], Lints::check('hero', '<div data-wx-block="hero"></div>', $styles));
    }

    #[Test]
    public function each_habit_is_named_with_its_line(): void
    {
        $styles = "\n.b-hero { }\n.promo, #x { }\nh2, a { }\n@media (min-width: 1px) { }";

        $lints = Lints::check('hero', '<div></div>', $styles);

        $this->assertSame(
            [['template', 'no-marker', null], ['styles', 'stray-selectors', 3], ['styles', 'bare-selectors', 4], ['styles', 'media-query', 5]],
            array_map(static fn (array $lint): array => [$lint['file'], $lint['code'], $lint['line']], $lints),
        );

        $this->assertStringContainsString('.promo, #x', $lints[1]['message']);
        $this->assertStringContainsString('h2, a', $lints[2]['message']);
    }
}
