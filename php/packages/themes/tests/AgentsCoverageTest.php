<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Vocabulary;

/**
 * What an agent reads before it touches a theme (THEMES §16) names everything there is: every
 * `webx:theme:*` command and every key of the manifest in the engine's AGENTS.md, every token of
 * the vocabulary in theme-default's, and the 41 rules in RULES.md, numbered as the check and the
 * contract name them. A command or a token added without a line here is one no agent will use.
 */
final class AgentsCoverageTest extends TestCase
{
    /** The keys of a theme's manifest, `extra.webx.theme` or `theme` in theme.json (§5). */
    private const array MANIFEST_KEYS = ['title', 'uses', 'requires', 'locales', 'fonts', 'preset', 'settings'];

    #[Test]
    public function the_engines_guide_names_every_command_and_every_key_of_the_manifest(): void
    {
        $guide = (string) file_get_contents(dirname(__DIR__).'/AGENTS.md');
        $commands = array_values(array_filter(array_keys(Artisan::all()), static fn (string $name): bool => str_starts_with($name, 'webx:theme:')));

        $this->assertCount(6, $commands, 'make, extract, eject, show, check, sync');

        foreach ($commands as $command) {
            $this->assertStringContainsString("`{$command}", $guide, "{$command} is not in AGENTS.md");
        }

        foreach (self::MANIFEST_KEYS as $key) {
            $this->assertStringContainsString("`{$key}`", $guide, "the manifest key {$key} is not in AGENTS.md");
        }

        $this->assertStringContainsString('`Testing\ThemeContract`', $guide);
        $this->assertStringContainsString('[RULES.md](RULES.md)', $guide);
    }

    #[Test]
    public function the_rules_are_the_forty_one_of_the_spec_in_order(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__).'/RULES.md');

        preg_match_all('/^(\d+)\. (.*)$/m', $rules, $matches);

        $this->assertSame(range(1, 41), array_map('intval', $matches[1]));

        foreach ($matches[2] as $index => $first) {
            $this->assertMatchesRegularExpression('/\*\*Do( not)?\*\*/', $first, 'rule '.($index + 1).' starts with **Do** or **Do not**');
        }
    }

    #[Test]
    public function theme_defaults_guide_names_every_token_of_the_vocabulary(): void
    {
        $file = dirname(__DIR__, 2).'/theme-default/AGENTS.md';

        if (! is_file($file)) {
            $this->markTestSkipped('Not in the monorepo: theme-default is not beside the engine.');
        }

        $guide = (string) file_get_contents($file);

        foreach (Vocabulary::base()->names() as $name) {
            // A run of numbered tokens is written once: `space-1`…`space-8`.
            $covered = str_contains($guide, "`{$name}`")
                || (preg_match('/^(.*-)(\d+)$/', $name, $parts) === 1
                    && preg_match('/`'.preg_quote($parts[1], '/').'(\d+)`…`'.preg_quote($parts[1], '/').'(\d+)`/', $guide, $range) === 1
                    && (int) $parts[2] >= (int) $range[1] && (int) $parts[2] <= (int) $range[2]);

            $this->assertTrue($covered, "the token {$name} is not in theme-default's AGENTS.md");
        }
    }
}
