<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\History;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Models\CmsUser;

/**
 * A module's own panel API, declared behind `webx.panel` the way every module declares it, with
 * the real `cms.auth`: a save made there is the panel's, with the administrator who made it.
 */
final class PanelHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        History::types()->register('test.note', null, permission: 'notes.view');

        Route::prefix('api/cms')->middleware('webx.panel')->group(function (): void {
            Route::put('test-notes/{id}', function (int $id) {
                History::recordFor('test.note', $id, 'published', ['is_visible' => [false, true]]);

                return ['ok' => true];
            });
        });
    }

    #[Test]
    public function a_save_through_a_modules_api_is_panel_with_the_signed_in_administrator(): void
    {
        $admin = $this->admin();

        // Signed in on the panel's guard without making it the default one, as a real session
        // is: until `cms.auth` has run, `$request->user()` knows nobody.
        $this->signIn($admin);

        $this->putJson('api/cms/test-notes/7')->assertOk();

        $row = HistoryEntry::query()->sole();
        $this->assertSame('panel', $row->source);
        $this->assertSame($admin->id, $row->admin_id);
        $this->assertSame('Admin', $row->admin_name);
    }

    #[Test]
    public function the_group_is_still_closed_to_a_stranger(): void
    {
        $this->putJson('api/cms/test-notes/7')->assertUnauthorized();

        $this->assertSame(0, HistoryEntry::query()->count());
    }

    private function signIn(CmsUser $admin): void
    {
        $this->app->make('auth')->guard('cms')->setUser($admin);
    }
}
