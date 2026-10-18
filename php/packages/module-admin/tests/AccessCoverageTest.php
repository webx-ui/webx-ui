<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Testing\AssertsPermissions;
use WebxUi\Mcp\McpServiceProvider;

/**
 * The frame's own doors (WEBX_UI_PERMISSIONS.md §5.5, §15). The cache and the journal's tools
 * have permissions of their own; the rest are shared by every section — notes, history, links,
 * the record being edited, uploads — and each asks the permission the section registered with
 * the type it names, inside: there is no one permission to put on the route. Those are listed
 * with the reason, and their tests are the sections' own.
 */
final class AccessCoverageTest extends TestCase
{
    use AssertsPermissions;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class, AuthServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    #[Test]
    public function every_route_and_tool_asks_for_a_declared_permission(): void
    {
        $byType = 'asks the permission the section registered with the type it names, inside';

        $this->assertPanelRoutesNeedPermission('WebxUi\\Admin\\Http\\', [
            'GET api/cms/manifest' => 'the shape of the panel, no data; the menu hides what the person may not open',
            'GET api/cms/screens/{name}' => 'the shape of a screen, no data; nodes behind a permission are cut out inside',
            'GET api/cms/shortcodes' => 'the names of the shortcodes the editor offers, no data',
            'GET api/cms/links/routes' => 'the kinds of address a link can take, no data',
            'GET api/cms/links/sources' => 'the sources of links, each filtered by its own permission inside',
            'GET api/cms/links/search' => $byType,
            'POST api/cms/links/resolve' => $byType,
            'GET api/cms/collections' => 'the sources of collections, each filtered by its own permission inside',
            'GET api/cms/relations/{target}' => $byType,
            'GET api/cms/site-bar/context' => 'every record on the page is asked of its own section, inside',
            'POST api/cms/site-bar/publish' => $byType,
            'POST api/cms/site-bar/discard' => $byType,
            'GET api/cms/entities/{type}/{id}/notes' => $byType,
            'POST api/cms/entities/{type}/{id}/notes' => $byType,
            'PUT api/cms/notes/{note}' => 'the note\'s record says which permission its notes are behind, inside',
            'DELETE api/cms/notes/{note}' => 'the note\'s record says which permission its notes are behind, inside',
            'GET api/cms/history/{type}/{id}' => $byType,
            'GET api/cms/history/runs/{id}' => 'the run\'s type says which permission it is read behind, inside',
            'POST api/cms/editing/{entity}/{id}' => $byType,
            'DELETE api/cms/editing/{entity}/{id}' => 'takes away only your own «I am editing this»',
            'GET api/cms/editing/{entity}/{id}/drafts' => $byType,
            'POST api/cms/editing/{entity}/{id}/drafts/{version}/restore' => $byType,
            'POST api/cms/uploads' => 'the purpose of the file says who may send one, inside',
            'GET api/cms/uploads/{id}' => 'only your own upload',
            'PATCH api/cms/uploads/{id}' => 'only your own upload',
            'DELETE api/cms/uploads/{id}' => 'only your own upload',
        ]);

        $this->assertToolsNeedPermission('cache', 'history');
    }
}
