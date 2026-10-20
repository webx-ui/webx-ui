<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Seen;

/**
 * The panel's half (§7–§8): one dangerous permission, a save that is on the site at once, the
 * rules of a value, the warning about a whole document, the order of a place, and the journal
 * with the code before and after.
 */
final class PanelTest extends TestCase
{
    use CountsQueries;

    private const API = '/api/cms/snippets';

    #[Test]
    public function the_section_is_in_the_system_group_behind_its_own_permission(): void
    {
        /** @var list<array<string, mixed>> $list */
        $list = $this->actingAs($this->admin(), 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');
        $modules = array_column($list, null, 'id');

        $this->assertSame('system', $modules['snippets']['group'] ?? null);
        $this->assertSame(['snippets.manage'], $modules['snippets']['permissions'] ?? null);
    }

    #[Test]
    public function only_somebody_with_snippets_manage_gets_in_and_a_super_administrator_always_does(): void
    {
        $snippet = $this->snippet('head', '<i>tag</i>');

        $this->getJson(self::API)->assertUnauthorized();

        // Every other permission of the panel is not this one.
        $editor = $this->admin(false, ['pages.view', 'pages.manage', 'settings.manage', 'seo.manage']);
        $this->actingAs($editor, 'cms')->getJson(self::API)->assertForbidden();
        $this->actingAs($editor, 'cms')->getJson(self::API.'/'.$snippet->id)->assertForbidden();
        $this->actingAs($editor, 'cms')->putJson(self::API.'/'.$snippet->id, ['values' => ['enabled' => false]])->assertForbidden();

        $this->actingAs($this->admin(false, ['snippets.manage']), 'cms')->getJson(self::API)->assertOk();
        $this->actingAs($this->admin(), 'cms')->getJson(self::API.'/'.$snippet->id)->assertOk()->assertJsonPath('data.values.code', '<i>tag</i>');
    }

    #[Test]
    public function the_list_is_every_place_in_order_with_the_undeclared_ones_last(): void
    {
        config()->set('webx-snippets.places', ['before-footer' => 'Before the footer']);
        $this->snippet('body_end', '<i>b</i>', ['position' => 2]);
        $this->snippet('body_end', '<i>a</i>', ['position' => 1]);
        $this->app->make(Seen::class)->mark('body_end');
        DB::table('snippets_codes')->insert(['name' => 'Old', 'place' => 'promo', 'code' => '<i>old</i>', 'consent' => 'necessary', 'enabled' => false, 'on_stage' => false, 'position' => 1]);

        $places = $this->actingAs($this->admin(), 'cms')->getJson(self::API)->assertOk()->json('data');

        $this->assertSame(['head', 'body_start', 'body_end', 'before-footer', 'promo'], array_column($places, 'key'));
        $this->assertSame([true, true, true, false, false], array_column($places, 'built_in'));
        $this->assertFalse($places[4]['declared']);
        $this->assertFalse($places[4]['snippets'][0]['place_declared']);
        $this->assertSame([1, 2], array_column($places[2]['snippets'], 'position'));
        $this->assertSame('Head, as high as possible', $places[0]['label']);
        // A place printed by a page carries the day; one never printed, or not declared, does not.
        $this->assertSame(now()->toDateString(), $places[2]['last_seen']);
        $this->assertSame([null, null, null], [$places[0]['last_seen'], $places[3]['last_seen'], $places[4]['last_seen']]);
    }

    #[Test]
    public function a_save_is_on_the_site_at_once_and_a_new_code_is_off_until_switched_on(): void
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin, 'cms')->postJson(self::API, ['values' => [
            'name' => 'Chat',
            'place' => 'body_end',
            'code' => '<script src="https://chat.example/w.js"></script>',
            'consent' => 'marketing',
            'only_paths' => ['/contacts', ' /blog/* ', ''],
            'locales' => ['ru'],
        ]])->assertCreated()->json('data');

        $this->assertFalse($created['values']['enabled']);
        $this->assertSame(['/contacts', '/blog/*'], $created['values']['only_paths']);
        $this->assertSame([], $created['values']['except_paths']);
        $this->assertSame([], $created['warnings']);

        $id = $created['snippet']['id'];
        $this->actingAs($admin, 'cms')->putJson(self::API.'/'.$id, ['values' => ['enabled' => true]])->assertOk();

        $snippet = Snippet::query()->findOrFail($id);
        $this->assertTrue($snippet->enabled);
        $this->assertSame('marketing', $snippet->consent);
        $this->assertSame(1, $snippet->position);
    }

    #[Test]
    public function every_rule_refuses_under_its_own_field(): void
    {
        $admin = $this->admin();
        $post = fn (array $values) => $this->actingAs($admin, 'cms')->postJson(self::API, ['values' => [
            'name' => 'Tag', 'place' => 'head', 'code' => '<i>tag</i>', ...$values,
        ]]);

        $post(['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $post(['name' => str_repeat('n', 161)])->assertUnprocessable()->assertJsonValidationErrors('name');
        $post(['place' => 'promo'])->assertUnprocessable()->assertJsonValidationErrors(['place' => 'not declared']);
        $post(['code' => '   '])->assertUnprocessable()->assertJsonValidationErrors('code');
        $post(['code' => str_repeat('я', 32769)])->assertUnprocessable()->assertJsonValidationErrors(['code' => '65538 bytes']);
        $post(['consent' => 'analytics'])->assertUnprocessable()->assertJsonValidationErrors(['consent' => 'necessary, statistics, marketing, media']);
        $post(['only_paths' => ['blog/*']])->assertUnprocessable()->assertJsonValidationErrors('only_paths');
        $post(['except_paths' => ['https://example.test/blog']])->assertUnprocessable()->assertJsonValidationErrors('except_paths');
        $post(['only_paths' => ['/search?q=1']])->assertUnprocessable()->assertJsonValidationErrors('only_paths');
        $post(['locales' => ['de']])->assertUnprocessable()->assertJsonValidationErrors(['locales' => 'en, ru']);
        $post(['on_stage' => 'perhaps'])->assertUnprocessable()->assertJsonValidationErrors('on_stage');

        // 65 536 bytes is the limit itself, not over it.
        $post(['code' => str_repeat('a', 65536)])->assertCreated();
        $this->assertSame(1, Snippet::query()->count());
    }

    #[Test]
    public function a_code_in_a_place_nobody_declares_may_be_switched_off_or_moved_but_not_on(): void
    {
        DB::table('snippets_codes')->insert(['name' => 'Old', 'place' => 'promo', 'code' => '<i>old</i>', 'consent' => 'necessary', 'enabled' => true, 'on_stage' => false, 'position' => 1]);
        $id = (int) DB::table('snippets_codes')->value('id');
        $admin = $this->admin();
        $put = fn (array $values) => $this->actingAs($admin, 'cms')->putJson(self::API.'/'.$id, ['values' => $values]);

        $put(['name' => 'Old, renamed'])->assertUnprocessable()->assertJsonValidationErrors(['place' => 'no longer declared']);
        $put(['enabled' => false, 'name' => 'Old, renamed'])->assertOk();
        $put(['enabled' => true])->assertUnprocessable()->assertJsonValidationErrors('place');
        $put(['place' => 'body_end', 'enabled' => true])->assertOk()->assertJsonPath('data.snippet.place_declared', true);
    }

    #[Test]
    public function the_tags_of_a_whole_document_are_a_warning_and_the_save_goes_through(): void
    {
        $response = $this->actingAs($this->admin(), 'cms')->postJson(self::API, ['values' => [
            'name' => 'Pasted page',
            'place' => 'head',
            'code' => "<html><HEAD><script>window.tag = 1</script></head>\n<body></body></html>",
        ]])->assertCreated();

        $this->assertSame(['The code has <html>, <head>, <body>: paste only what the service asks to put inside the page.'], $response->json('data.warnings'));
        $this->assertSame(1, Snippet::query()->count());
    }

    #[Test]
    public function the_order_of_a_place_is_written_whole_and_a_code_dragged_in_moves_with_it(): void
    {
        $a = $this->snippet('body_end', '<i>a</i>');
        $b = $this->snippet('body_end', '<i>b</i>');
        $c = $this->snippet('head', '<i>c</i>');
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')->postJson(self::API.'/reorder', ['place' => 'body_end', 'ids' => [$b->id, $c->id, $a->id]])->assertNoContent();

        $this->assertSame([[$b->id, 'body_end', 0], [$c->id, 'body_end', 1], [$a->id, 'body_end', 2]], Snippet::query()->orderBy('position')->get()
            ->map(static fn (Snippet $snippet): array => [$snippet->id, $snippet->place, $snippet->position])->all());

        // The journal says where the dragged one went.
        $this->assertSame('place', HistoryEntry::query()->where('subject_id', $c->id)->where('event', 'updated')->sole()->changes[0]['field'] ?? null);

        $this->actingAs($admin, 'cms')->postJson(self::API.'/reorder', ['place' => 'promo', 'ids' => [$a->id]])->assertUnprocessable()->assertJsonValidationErrors('place');
        $this->actingAs($admin, 'cms')->postJson(self::API.'/reorder', ['place' => 'head', 'ids' => [999]])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    #[Test]
    public function the_bin_takes_a_code_off_the_site_and_gives_it_back(): void
    {
        $snippet = $this->snippet('head', '<i>tag</i>');
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')->deleteJson(self::API.'/'.$snippet->id)->assertNoContent();
        $this->assertSame([$snippet->id], array_column($this->actingAs($admin, 'cms')->getJson(self::API.'?trashed=1')->json('data'), 'id'));

        $this->actingAs($admin, 'cms')->postJson(self::API.'/'.$snippet->id.'/restore')->assertOk();
        $this->assertFalse($snippet->refresh()->trashed());

        $snippet->delete();
        $this->actingAs($admin, 'cms')->deleteJson(self::API.'/'.$snippet->id.'/purge')->assertNoContent();
        $this->assertSame(0, Snippet::query()->withTrashed()->count());
    }

    #[Test]
    public function the_journal_keeps_who_changed_a_code_and_the_whole_code_before_and_after(): void
    {
        $before = '<script>'.str_repeat('/* the old counter */ ', 60).'</script>';
        $after = '<script>'.str_repeat('/* the new counter */ ', 80).'</script>';
        $admin = $this->admin();

        $id = $this->actingAs($admin, 'cms')->postJson(self::API, ['values' => ['name' => 'Counter', 'place' => 'head', 'code' => $before]])
            ->assertCreated()
            ->json('data.snippet.id');

        $this->actingAs($admin, 'cms')->putJson(self::API.'/'.$id, ['values' => ['code' => $after, 'enabled' => true]])->assertOk();

        $entry = HistoryEntry::query()->where('subject_type', Snippet::TYPE)->where('subject_id', $id)->where('event', 'updated')->sole();
        $changes = collect($entry->changes ?? [])->keyBy('field');

        $this->assertGreaterThan(500, strlen($before));
        $this->assertSame(['field' => 'code', 'from' => $before, 'to' => $after], $changes['code']);
        $this->assertSame(['field' => 'enabled', 'from' => false, 'to' => true], $changes['enabled']);
        $this->assertSame($admin->id, $entry->admin_id);
        $this->assertSame($admin->name, $entry->admin_name);
        $this->assertSame('panel', $entry->source);

        // And the panel reads it back through the journal's own door.
        $this->actingAs($admin, 'cms')->getJson('/api/cms/history/snippets/'.$id)->assertOk()->assertJsonPath('data.0.changes.0.field', 'code');
    }

    /**
     * The card's "History" draws a face: the author's photograph travels with the row, for every
     * author of the page in one query, and an author deleted since keeps the name they signed with.
     */
    #[Test]
    public function the_journal_carries_the_authors_photograph_and_survives_a_deleted_one(): void
    {
        [$first, $second, $gone] = [$this->admin(), $this->admin(), $this->admin()];
        $first->forceFill(['avatar' => 'avatars/first.jpg'])->save();
        $gone->forceFill(['avatar' => 'avatars/gone.jpg'])->save();

        $id = $this->actingAs($first, 'cms')
            ->postJson(self::API, ['values' => ['name' => 'Counter', 'place' => 'head', 'code' => '<script>1</script>']])
            ->assertCreated()
            ->json('data.snippet.id');
        $this->actingAs($second, 'cms')->putJson(self::API.'/'.$id, ['values' => ['code' => '<script>2</script>']])->assertOk();

        $theirs = $this->actingAs($gone, 'cms')
            ->postJson(self::API, ['values' => ['name' => 'Chat', 'place' => 'body_end', 'code' => '<script>3</script>']])
            ->assertCreated()
            ->json('data.snippet.id');
        $name = $gone->name;
        $gone->delete();

        $this->actingAs($first, 'cms');

        $rows = $this->assertNoRepeatedQueries(
            fn () => $this->getJson('/api/cms/history/snippets/'.$id)->assertOk()->json('data'),
            'every author of the page is read in one query',
        );
        $byAuthor = array_column(array_column($rows, 'admin'), null, 'id');

        $this->assertSame('avatars/first.jpg', $byAuthor[$first->id]['avatar']);
        $this->assertNull($byAuthor[$second->id]['avatar'], 'no photograph is initials, not an error');

        $this->getJson('/api/cms/history/snippets/'.$theirs)
            ->assertOk()
            ->assertJsonPath('data.0.admin', ['id' => $gone->id, 'name' => $name, 'avatar' => null]);
    }
}
