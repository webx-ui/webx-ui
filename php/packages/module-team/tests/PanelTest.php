<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Team\Models\Member;

/**
 * The section as the panel reaches it (§5.6, §5.7): the manifest, the permissions, the list in its
 * order, the form that creates and saves — in exactly the shapes the panel is written against.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_section_is_one_entry_at_the_top_of_the_menu(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        /** @var array<int, array<string, mixed>> $modules */
        $modules = $response->json('data.modules');
        $team = null;

        foreach ($modules as $module) {
            if (($module['id'] ?? null) === 'team') {
                $team = $module;
            }
        }

        $this->assertIsArray($team);
        $this->assertNull($team['group'] ?? null, 'no group: with no categories there is nothing to group it with');
        $this->assertSame('users', $team['icon'] ?? null);
        $this->assertSame(['team.view', 'team.manage'], $team['permissions']);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $member = $this->member('Anna');

        $this->getJson($this->api())->assertUnauthorized();

        $viewer = $this->editor(['team.view']);

        $this->actingAs($viewer, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($viewer, 'cms')->getJson($this->api($member->id))->assertOk();
        $this->actingAs($viewer, 'cms')->postJson($this->api(), ['values' => ['name' => ['en' => 'Boris']]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->putJson($this->api($member->id), ['values' => ['published' => false]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->deleteJson($this->api($member->id))->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api($member->id.'/restore'))->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('reorder'), ['ids' => [$member->id]])->assertForbidden();
    }

    #[Test]
    public function the_list_is_everybody_in_their_order_in_the_shape_of_the_spec(): void
    {
        $this->picture();
        $a = $this->member('Anna Petrova', attributes: [
            'job_title' => ['en' => 'Orthodontist'],
            'photo' => ['path' => 'media/ab/cd/anna.jpg'],
        ]);
        $b = $this->member('Boris');
        $c = $this->member('Vera', published: false);
        DB::table('team_members')->where('id', $a->id)->update(['position' => 30]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame(['data'], array_keys((array) $response->json()), 'no meta, no pages, no filters');
        $this->assertSame([$b->id, $c->id, $a->id], array_column((array) $response->json('data'), 'id'));

        $row = (array) $response->json('data.2');
        $this->assertSame(
            ['id', 'name', 'job_title', 'initials', 'photo', 'published', 'position', 'updated_at', 'deleted_at'],
            array_keys($row),
        );
        $this->assertSame('Anna Petrova', $row['name']);
        $this->assertSame('Orthodontist', $row['job_title']);
        $this->assertSame('AP', $row['initials']);
        $this->assertSame(['thumb'], array_keys((array) $row['photo']));
        $this->assertTrue($row['published']);
        $this->assertSame(30, $row['position']);
        $this->assertNull($row['deleted_at']);

        $this->assertNull($response->json('data.0.photo'));
        $this->assertSame('', $response->json('data.0.job_title'));
        $this->assertFalse($response->json('data.1.published'));

        $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=bor')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=orthodont')
            ->assertOk()
            ->assertJsonPath('data.0.id', $a->id);
    }

    #[Test]
    public function a_row_is_named_in_the_panels_language_then_the_default_then_by_its_number(): void
    {
        $nameless = Member::query()->create(['text' => ['en' => 'Words.']]);
        $russian = Member::query()->create(['name' => ['ru' => 'Анна'], 'job_title' => ['ru' => 'Врач']]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame('#'.$nameless->id, $response->json('data.0.name'));
        $this->assertSame('', $response->json('data.0.initials'));
        $this->assertSame('#'.$russian->id, $response->json('data.1.name'), 'not in English, not in the default language');

        $inRussian = $this->actingAs($this->editor(), 'cms')->getJson($this->api(), ['X-Webx-Locale' => 'ru'])->assertOk();
        $this->assertSame('Анна', $inRussian->json('data.1.name'));
        $this->assertSame('Врач', $inRussian->json('data.1.job_title'));
    }

    #[Test]
    public function a_drag_writes_the_order_it_was_made_in(): void
    {
        $a = $this->member('A');
        $b = $this->member('B');
        $c = $this->member('C');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertSame([$c->id, $a->id, $b->id], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api())->json('data'), 'id'));
        $this->assertSame(['C', 'A', 'B'], array_column(team()->get(), 'name'));

        // A category, as the reviews' list would send it, is not obeyed: there are none.
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$b->id, $c->id, $a->id], 'category' => 3])
            ->assertNoContent();
        $this->assertSame(['B', 'C', 'A'], array_column(team()->get(), 'name'));

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('reorder'), ['ids' => 'all'])->assertUnprocessable()->assertJsonValidationErrors(['ids']);
    }

    #[Test]
    public function a_new_person_is_created_from_the_values_of_their_form(): void
    {
        $implants = $this->service('implants');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => [
                'name' => ['en' => 'Anna Petrova', 'ru' => 'Анна Петрова'],
                'job_title' => ['en' => 'Orthodontist'],
                'text' => ['en' => "Twelve years.\nBraces and aligners.", 'ru' => ''],
                'photo' => ['path' => 'media/ab/cd/anna.jpg', 'url' => 'https://cdn.example/anna.jpg'],
                'socials' => [
                    ['network' => 'instagram', 'url' => ' https://instagram.com/anna '],
                    ['network' => '', 'url' => ''],
                    ['network' => 'x', 'url' => 'https://x.com/anna'],
                ],
                'services' => [$implants->id],
                'published' => true,
            ],
        ])->assertCreated();

        $this->assertSame(['member', 'values'], array_keys((array) $response->json('data')));
        $this->assertSame(['id', 'name', 'published', 'deleted_at'], array_keys((array) $response->json('data.member')));
        $this->assertSame('Anna Petrova', $response->json('data.member.name'));
        $this->assertTrue($response->json('data.member.published'));

        $values = (array) $response->json('data.values');
        $this->assertSame(['name', 'job_title', 'text', 'photo', 'socials', 'published', 'services'], array_keys($values));
        $this->assertSame(['en' => 'Anna Petrova', 'ru' => 'Анна Петрова'], $values['name']);
        $this->assertSame(['en' => 'Orthodontist'], $values['job_title']);
        $this->assertSame(['en' => "Twelve years.\nBraces and aligners."], $values['text'], 'an emptied language is taken away');
        $this->assertSame(['path' => 'media/ab/cd/anna.jpg'], $values['photo'], 'the address is never kept');
        $this->assertSame(
            [['network' => 'instagram', 'url' => 'https://instagram.com/anna'], ['network' => 'x', 'url' => 'https://x.com/anna']],
            $values['socials'],
            'the empty row is dropped, the others are kept in order and trimmed',
        );
        $this->assertSame([$implants->id], $values['services']);
        $this->assertTrue($values['published']);

        $member = Member::query()->findOrFail($response->json('data.member.id'));
        $this->assertSame([$implants->id], $member->relatedIds(Member::SERVICES));

        $this->actingAs($this->editor(), 'cms')->getJson($this->api($member->id))
            ->assertOk()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.values.services', [$implants->id]);
    }

    #[Test]
    public function a_name_in_the_default_language_is_the_one_required_field(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => ['name' => ['ru' => 'Анна'], 'job_title' => ['en' => 'Doctor']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['name.en']);

        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), ['values' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name.en']);

        $this->assertSame(0, Member::query()->withTrashed()->count(), 'a refusal leaves nothing behind');

        $member = $this->member('Anna');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($member->id), ['values' => ['name' => ['en' => '']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name.en']);
        $this->assertSame('Anna', $member->refresh()->getTranslation('name', 'en'));
    }

    #[Test]
    public function a_social_link_is_refused_under_its_row(): void
    {
        $cases = [
            'not a web address' => [['network' => 'instagram', 'url' => 'javascript:alert(1)'], 'socials.0.url'],
            'no scheme' => [['network' => 'instagram', 'url' => 'instagram.com/anna'], 'socials.0.url'],
            'no address' => [['network' => 'instagram', 'url' => ''], 'socials.0.url'],
            'no network' => [['network' => '', 'url' => 'https://example.com'], 'socials.0.network'],
        ];

        foreach ($cases as $case => [$row, $key]) {
            $errors = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
                'values' => ['name' => ['en' => 'Anna'], 'socials' => [['network' => '', 'url' => ''], $row]],
            ])->assertUnprocessable()->json('errors');

            // The empty row before it is dropped, so the refused row is the first one.
            $this->assertSame([$key], array_keys((array) $errors), $case);
        }

        // A network the config does not have is refused by the screen: the select's options.
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => ['name' => ['en' => 'Anna'], 'socials' => [['network' => 'myspace', 'url' => 'https://myspace.com/anna']]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['socials']);

        $this->assertSame(0, Member::query()->withTrashed()->count());
    }

    #[Test]
    public function a_save_lays_one_language_over_the_others_and_leaves_the_rest_alone(): void
    {
        $member = $this->member('Anna', 'Анна работает здесь.', attributes: [
            'socials' => [['network' => 'x', 'url' => 'https://x.com/anna']],
        ]);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($member->id), [
            'values' => ['text' => ['en' => 'Anna leads the clinic.'], 'published' => false],
        ])->assertOk()
            ->assertJsonPath('data.values.text', ['en' => 'Anna leads the clinic.', 'ru' => 'Анна работает здесь.'])
            ->assertJsonPath('data.values.socials', [['network' => 'x', 'url' => 'https://x.com/anna']])
            ->assertJsonPath('data.values.published', false)
            ->assertJsonPath('data.member.published', false);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($member->id), ['values' => ['socials' => []]])
            ->assertOk()
            ->assertJsonPath('data.values.socials', []);
        $this->assertNull($member->refresh()->socials);
    }

    #[Test]
    public function a_field_of_the_project_goes_into_extra(): void
    {
        Screens::extend(Member::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'experience', 'type' => 'wx-input', 'name' => 'experience', 'label' => 'Experience'],
        ]]);

        $member = $this->member('Anna');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($member->id), ['values' => ['experience' => '12']])
            ->assertOk()
            ->assertJsonPath('data.values.experience', '12');

        $this->assertSame('12', $member->refresh()->extra('experience'));
        $this->assertSame(['experience' => '12'], team()->first()['fields'] ?? null);
    }

    #[Test]
    public function the_bin_keeps_the_person_and_gives_them_back_as_a_row_of_the_list(): void
    {
        $implants = $this->service('implants');
        $member = $this->member('Anna');
        $member->syncRelated(Member::SERVICES, 'service', [$implants->id]);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($member->id))->assertNoContent();
        $this->assertSame([], $this->actingAs($this->editor(), 'cms')->getJson($this->api())->json('data'));
        $this->assertSame([], team()->get());

        $bin = $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?trashed=1')->assertOk();
        $this->assertSame([$member->id], array_column((array) $bin->json('data'), 'id'));
        $this->assertNotNull($bin->json('data.0.deleted_at'));

        $restored = $this->actingAs($this->editor(), 'cms')->postJson($this->api($member->id.'/restore'))->assertOk();

        $this->assertSame(
            ['id', 'name', 'job_title', 'initials', 'photo', 'published', 'position', 'updated_at', 'deleted_at'],
            array_keys((array) $restored->json('data')),
            'the bare row of the list',
        );
        $this->assertSame('Anna', $restored->json('data.name'));
        $this->assertNull($restored->json('data.deleted_at'));
        $this->assertSame([$implants->id], array_column(team()->first()['service_links'] ?? [], 'id'), 'back with the services it had');
    }

    #[Test]
    public function a_person_is_a_target_other_modules_can_point_at(): void
    {
        $this->picture();
        $this->member('Anna Petrova', attributes: ['job_title' => ['en' => 'Orthodontist'], 'photo' => ['path' => 'media/ab/cd/anna.jpg']]);
        $this->member('Boris', published: false);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/relations/team-member?q=orthodon')
            ->assertOk();

        $this->assertSame(['Anna Petrova'], array_column((array) $response->json('data'), 'title'));
        $this->assertSame('Orthodontist', $response->json('data.0.subtitle'));
        $this->assertIsString($response->json('data.0.thumb'));
        $this->assertTrue($response->json('data.0.visible'));

        $everybody = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/relations/team-member')->assertOk();
        $this->assertSame([true, false], array_column((array) $everybody->json('data'), 'visible'), 'an unpublished person is offered, marked');
    }
}
