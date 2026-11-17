<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The pure half of `webx:theme:critical` — `resources/critical/rules.mjs`, run in Node: how a
 * selector list is cut and how the rules of several pages become one file.
 */
class CriticalRulesTest extends BaseTestCase
{
    #[Test]
    public function a_selector_list_is_cut_only_at_its_top_level_commas(): void
    {
        $cases = [
            '.a, .b' => ['.a', '.b'],
            '.site:has(.main > .under:first-child, .main > [data-block=\'hero\'].bleed:first-child) .header' => [
                '.site:has(.main > .under:first-child, .main > [data-block=\'hero\'].bleed:first-child) .header',
            ],
            ':is(.a, .b) .c, .d' => [':is(.a, .b) .c', '.d'],
            '.x:not(.a, :where(.b, .c)), .y' => ['.x:not(.a, :where(.b, .c))', '.y'],
            '[title="a,b"], [title=\'c,d\'] .e' => ['[title="a,b"]', '[title=\'c,d\'] .e'],
            '[data-x="a\\",b"], .f' => ['[data-x="a\\",b"]', '.f'],
            '.g\\,h, .i' => ['.g\\,h', '.i'],
            ' .j ,, .k ' => ['.j', '.k'],
        ];

        $answer = $this->rules('split', array_keys($cases));

        $this->assertSame(array_values($cases), $answer);
    }

    #[Test]
    public function a_block_bundle_of_another_page_does_not_change_the_cascade_of_this_one(): void
    {
        // Page A: its bundle has the text block and the modifier it uses. Page B: its bundle has
        // a hero and the same text block, without the modifier. Laid one after another, B's
        // `.b-text` lands after A's `.b-text--page`, and on page A the modifier loses.
        $base = $this->rule('body{margin:0}');
        $header = $this->rule('.site-header{position:sticky}');
        $text = $this->rule('.b-text{padding-block:64px}');
        $page = $this->rule('.b-text--page{padding-block:24px}');
        $hero = $this->rule('.b-hero{min-height:100vh}');
        $narrow = $this->rule('.b-text{padding-block:32px}', ['@media (max-width: 600px)']);

        $screens = [
            'A 1440' => [$base, $header, $text, $page],
            'A 390' => [$base, $header, $text, $narrow, $page],
            'B 1440' => [$base, $header, $hero, $text],
            'B 390' => [$base, $header, $hero, $text, $narrow],
        ];

        $answer = $this->rules('merge', array_values($screens));

        $this->assertSame(0, $answer['conflicts']);
        // Every rule once — the same rule of two bundles is one rule.
        $this->assertCount(6, $answer['rules']);
        foreach ($screens as $name => $rules) {
            $this->assertSame(
                $rules,
                array_values(array_filter($answer['rules'], fn (array $rule): bool => in_array($rule, $rules, true))),
                "The cascade of screen {$name} changed.",
            );
        }
        $this->assertSame(
            'body{margin:0}.site-header{position:sticky}.b-hero{min-height:100vh}.b-text{padding-block:64px}'
            .'@media (max-width: 600px){.b-text{padding-block:32px}}.b-text--page{padding-block:24px}',
            $answer['css'],
        );
    }

    #[Test]
    public function a_rule_a_page_repeats_counts_where_it_is_last(): void
    {
        $a = $this->rule('.a{color:red}');
        $b = $this->rule('.a{color:blue}');

        $answer = $this->rules('merge', [[$a, $b, $a], [$b]]);

        $this->assertSame(0, $answer['conflicts']);
        $this->assertSame('.a{color:blue}.a{color:red}', $answer['css']);
    }

    #[Test]
    public function rules_two_pages_order_the_other_way_round_are_a_conflict(): void
    {
        $a = $this->rule('.a{color:red}');
        $b = $this->rule('.b{color:blue}');

        $answer = $this->rules('merge', [[$a, $b], [$b, $a]]);

        $this->assertSame(1, $answer['conflicts']);
        $this->assertSame('.a{color:red}.b{color:blue}', $answer['css']);
    }

    /**
     * @param  list<string>  $wrappers
     * @return array{wrappers: list<string>, text: string}
     */
    private function rule(string $text, array $wrappers = []): array
    {
        return ['wrappers' => $wrappers, 'text' => $text];
    }

    private function rules(string $mode, mixed $input): mixed
    {
        $node = (new ExecutableFinder)->find('node');
        $this->assertNotNull($node, 'Node is needed: the critical CSS is read by a script Node runs.');

        $module = 'file://'.realpath(__DIR__.'/../resources/critical/rules.mjs');
        $script = <<<JS
            import { mergeRules, serializeRules, splitSelectors } from '{$module}'
            const input = JSON.parse(process.argv[2])
            let output
            if (process.argv[1] === 'split') output = input.map(splitSelectors)
            else {
              const { rules, conflicts } = mergeRules(input)
              output = { rules, conflicts, css: serializeRules(rules) }
            }
            process.stdout.write(JSON.stringify(output))
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $script, $mode, json_encode($input, JSON_THROW_ON_ERROR)]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
