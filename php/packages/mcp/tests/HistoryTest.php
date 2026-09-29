<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Facades\History;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\Grants\Grant;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tests\Fixtures\PassportUser;
use WebxUi\Mcp\Tool;

/**
 * What an agent saves goes into the journal as the agent's doing (WEBX_UI_HISTORY.md §6): the
 * source is `mcp`, the author is the administrator who connected it, and the connection is
 * named — the same id `mcp_calls` holds, so the two logs read side by side.
 */
final class HistoryTest extends TestCase
{
    private const CLIENT = '9d2f6c1e-0a7b-4c3d-8e5f-1a2b3c4d5e6f';

    #[Test]
    public function a_tools_save_is_written_as_mcp_with_the_administrator_and_the_grant(): void
    {
        $this->app->make(HistoryTypes::class)->register('library.book', permission: 'library.view');

        $this->register(new class extends AbstractModule implements ProvidesMcpTools
        {
            use ProvidesMcpDefaults;

            public function id(): string
            {
                return 'library';
            }

            /**
             * @return list<Tool>
             */
            public function mcpTools(): array
            {
                return [
                    Tool::mutating('lend', 'Lend a book.', static function (): array {
                        History::recordFor('library.book', 5, 'updated', ['status' => ['in', 'out']]);

                        return ['ok' => true];
                    }),
                ];
            }
        });

        $grant = Grant::query()->create([
            'cms_user_id' => 1,
            'oauth_client_id' => self::CLIENT,
            'client_name' => 'Claude',
            'redirect_host' => 'claude.ai',
            'read_only' => false,
            'consent_version' => '2026-09-21',
            'created_at' => Carbon::now(),
        ]);

        WebxServer::actingAs(PassportUser::bearing(['mcp:use'], self::CLIENT))
            ->tool(new RegistryTool($this->tools()->tool('library_lend')), [])
            ->assertOk();

        $row = HistoryEntry::query()->sole();

        $this->assertSame('mcp', $row->source);
        $this->assertSame(1, $row->admin_id);
        $this->assertSame($grant->id, $row->grant_id);

        // And nothing of the agent stays behind for whatever the request does next.
        $this->assertNull($this->app->make(HistoryContext::class)->grantId());
    }
}
