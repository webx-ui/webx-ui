<?php

declare(strict_types=1);

namespace WebxUi\Templates\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Templates\Cdata;
use WebxUi\Templates\Escapes;
use WebxUi\Templates\Template;
use WebxUi\Templates\TemplateError;

/**
 * A feed is built here and previewed in the browser: both read the cases of `fixtures/render.json`
 * — this test here, `packages/core/src/components/CodeEditor/template.test.ts` there — so the
 * preview cannot show one file while the marketplace gets another without one of the two going red.
 */
final class ParityTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        /** @var array{cases: list<array<string, mixed>>} $fixtures */
        $fixtures = json_decode((string) file_get_contents(__DIR__.'/fixtures/render.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($fixtures['cases'] as $case) {
            yield (string) $case['name'] => [$case];
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[Test]
    #[DataProvider('cases')]
    public function the_server_renders_what_the_browser_shows(array $case): void
    {
        $template = (string) $case['template'];
        /** @var array{delimiter?: string} $options */
        $options = $case['options'] ?? [];
        $escape = Escapes::for((string) $case['format'], $options);

        if (isset($case['cdata_error'])) {
            try {
                Template::refuseInCdata($template);
                $this->fail("{$template} was accepted");
            } catch (TemplateError $error) {
                $this->assertSame('template-cdata', $error->key);
                $this->assertSame(['name' => $case['cdata_error']], $error->replace);
            }

            return;
        }

        if (isset($case['error'])) {
            try {
                Template::render($template, [], $escape);
                $this->fail("{$template} was accepted");
            } catch (TemplateError $error) {
                $this->assertSame($case['error'], $error->key);
            }

            return;
        }

        Template::refuseInCdata($template);

        /** @var array<string, mixed> $given */
        $given = $case['values'];
        $values = $this->values($given);
        $rendered = Template::render($template, $values, $escape);

        $this->assertSame($case['text'], $rendered['text']);
        $this->assertSame($case['text'], Template::compile($template, $escape)->render($values));

        if (isset($case['unknown'])) {
            $this->assertSame($case['unknown'], $rendered['unknown']);
        }
    }

    /**
     * `{ "$cdata": "…" }` is how the browser gets a {@see Cdata}.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, string|Cdata|null|list<array<string, string|Cdata|null>>>
     */
    private function values(array $values): array
    {
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $values[$name] = array_key_exists('$cdata', $value) ? Cdata::of((string) $value['$cdata']) : $this->values($value);
            }
        }

        /** @var array<string, string|Cdata|null|list<array<string, string|Cdata|null>>> $values */
        return $values;
    }
}
