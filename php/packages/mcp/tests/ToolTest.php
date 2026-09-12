<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Exceptions\McpException;
use WebxUi\Mcp\Tool;

final class ToolTest extends TestCase
{
    #[Test]
    public function a_mutating_tool_gets_dry_run_whether_its_author_thought_of_it_or_not(): void
    {
        $tool = Tool::mutating('delete_all', 'Remove everything matching a filter.', static fn (): bool => true);

        $this->assertArrayHasKey(Tool::DRY_RUN, $tool->inputSchema['properties']);
        $this->assertSame('boolean', $tool->inputSchema['properties'][Tool::DRY_RUN]['type']);
        $this->assertFalse($tool->inputSchema['properties'][Tool::DRY_RUN]['default']);
    }

    #[Test]
    public function a_read_tool_does_not(): void
    {
        $tool = Tool::read('list_all', 'List everything.', static fn (): array => []);

        $this->assertArrayNotHasKey(Tool::DRY_RUN, $tool->inputSchema['properties']);
        $this->assertFalse($tool->mutating);
    }

    #[Test]
    public function it_reads_dry_run_out_of_the_arguments(): void
    {
        $tool = Tool::mutating('touch', 'Touch something.', static fn (): bool => true);

        $this->assertFalse($tool->isDryRun([]));
        $this->assertFalse($tool->isDryRun(['dry_run' => false]));
        $this->assertTrue($tool->isDryRun(['dry_run' => true]));
    }

    #[Test]
    public function a_bare_schema_is_still_a_valid_object_schema(): void
    {
        $tool = Tool::read('list_all', 'List everything.', static fn (): array => []);

        $this->assertSame('object', $tool->inputSchema['type']);
        $this->assertSame([], $tool->inputSchema['properties']);
    }

    #[Test]
    public function a_declared_schema_survives(): void
    {
        $tool = Tool::mutating(
            'rename',
            'Rename a file.',
            static fn (): bool => true,
            [
                'properties' => ['id' => ['type' => 'string']],
                'required' => ['id'],
            ],
        );

        $this->assertSame(['type' => 'string'], $tool->inputSchema['properties']['id']);
        $this->assertSame(['id'], $tool->inputSchema['required']);
        $this->assertArrayHasKey(Tool::DRY_RUN, $tool->inputSchema['properties']);
    }

    #[Test]
    public function a_name_a_model_cannot_call_is_refused(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('[Get Seo]');

        Tool::read('Get Seo', 'Read the SEO fields.', static fn (): array => []);
    }

    #[Test]
    public function a_tool_without_a_description_is_refused(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('has no description');

        Tool::read('get_seo', '   ', static fn (): array => []);
    }

    #[Test]
    public function the_handler_runs(): void
    {
        $tool = Tool::mutating(
            'bulk_update',
            'Update many things at once.',
            static fn (array $arguments): array => ['changed' => $arguments['dry_run'] ? 0 : 7],
        );

        $this->assertSame(['changed' => 0], ($tool->handler)(['dry_run' => true]));
        $this->assertSame(['changed' => 7], ($tool->handler)(['dry_run' => false]));
    }
}
