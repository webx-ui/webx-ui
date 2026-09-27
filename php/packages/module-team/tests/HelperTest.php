<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Localization\Locales;
use WebxUi\Team\Collections\TeamSource;
use WebxUi\Team\Models\Member;

/**
 * `team()` — what a template may show of the team, as cards (§5.3) — and the `wx-collection`
 * source that hands over the same cards.
 */
final class HelperTest extends TestCase
{
    #[Test]
    public function every_visible_person_in_the_order_of_the_list(): void
    {
        $this->member('Anna');
        $this->member('Draft', published: false);
        $this->member('Boris');
        $this->member('Binned')->delete();

        $this->assertSame(['Anna', 'Boris'], $this->names(team()));
        $this->assertCount(2, team());
        $this->assertFalse(team()->isEmpty());
    }

    #[Test]
    public function a_card_carries_what_a_template_prints_and_nothing_to_query(): void
    {
        $this->picture();
        $member = $this->member('Anna Petrova', attributes: [
            'job_title' => ['en' => 'Orthodontist'],
            'photo' => ['path' => 'media/ab/cd/anna.jpg'],
            'socials' => [
                ['network' => 'instagram', 'url' => 'https://instagram.com/anna'],
                ['network' => 'myspace', 'url' => 'https://myspace.com/anna'],
                ['network' => 'x', 'url' => 'https://x.com/anna'],
            ],
        ]);
        $member->mergeExtra(['experience' => '12'])->save();

        $card = team()->first();

        $this->assertIsArray($card);
        $this->assertSame(
            ['id', 'anchor', 'categories', 'name', 'initials', 'job_title', 'text', 'photo', 'socials', 'service_links', 'fields'],
            array_keys($card),
        );
        $this->assertSame((int) $member->getKey(), $card['id']);
        $this->assertSame('member-'.$member->getKey(), $card['anchor']);
        $this->assertSame([], $card['categories']);
        $this->assertSame('Anna Petrova', $card['name']);
        $this->assertSame('AP', $card['initials']);
        $this->assertSame('Orthodontist', $card['job_title']);
        $this->assertSame('Anna Petrova works here.', $card['text']);
        $this->assertIsArray($card['photo']);
        $this->assertIsString($card['photo']['url']);
        $this->assertSame(400, $card['photo']['width']);
        $this->assertSame(
            [
                ['network' => 'instagram', 'label' => 'Instagram', 'url' => 'https://instagram.com/anna'],
                ['network' => 'x', 'label' => 'X', 'url' => 'https://x.com/anna'],
            ],
            $card['socials'],
            'a network the config does not have drops out, in the editor\'s order',
        );
        $this->assertSame([], $card['service_links']);
        $this->assertSame(['experience' => '12'], $card['fields']);

        $this->assertNull(team()->except($member)->first());

        $bare = $this->member('Boris');
        $card = team()->only([$bare->getKey()])->first();
        $this->assertIsArray($card);
        $this->assertSame('', $card['job_title']);
        $this->assertNull($card['photo']);
        $this->assertSame([], $card['socials']);
    }

    #[Test]
    public function a_network_taken_out_of_the_config_comes_back_with_it(): void
    {
        $this->member('Anna', attributes: ['socials' => [['network' => 'tiktok', 'url' => 'https://tiktok.com/@anna']]]);

        config()->set('webx-team.networks', ['instagram' => 'Instagram']);
        $this->assertSame([], team()->first()['socials'] ?? null);

        config()->set('webx-team.networks', ['tiktok' => 'TikTok']);
        $this->assertSame([['network' => 'tiktok', 'label' => 'TikTok', 'url' => 'https://tiktok.com/@anna']], team()->first()['socials'] ?? null);
    }

    #[Test]
    public function nobody_is_hidden_over_a_language(): void
    {
        $both = $this->member('Anna', 'Анна работает здесь.');
        $this->member('English only');

        $russian = team()->locale('ru')->get();

        $this->assertSame(['Anna', 'English only'], array_column($russian, 'name'), 'a name nobody translated is the name in the default language');
        $this->assertSame('Анна работает здесь.', $russian[0]['text']);
        $this->assertSame('', $russian[1]['text'], 'no other language stands in for the text, and the person stays');

        $both->setTranslation('name', 'ru', 'Анна')->setTranslation('job_title', 'en', 'Doctor')->save();

        $card = team()->locale('ru')->first();
        $this->assertSame('Анна', $card['name'] ?? null);
        $this->assertSame('А', $card['initials'] ?? null);
        $this->assertSame('Doctor', $card['job_title'] ?? null, 'a job title falls back like a name');
    }

    #[Test]
    public function only_keeps_the_order_it_was_given_and_except_and_take_narrow_it(): void
    {
        $anna = $this->member('Anna');
        $boris = $this->member('Boris');
        $vera = $this->member('Vera');

        $this->assertSame(['Vera', 'Anna'], $this->names(team()->only([$vera->getKey(), $anna->getKey()])));
        $this->assertSame(['Anna', 'Vera'], $this->names(team()->except($boris)));
        $this->assertSame(['Anna'], $this->names(team()->except([])->take(1)));
        $this->assertSame(['Anna', 'Boris', 'Vera'], $this->names(team()->take(0)));
        $this->assertSame(['Anna', 'Boris', 'Vera'], $this->names(team()->take(null)));
        $this->assertSame([], $this->names(team()->only([])));
    }

    #[Test]
    public function the_limit_counts_what_is_shown(): void
    {
        $this->member('Draft', published: false);
        $a = $this->member('A');
        $b = $this->member('B');
        $this->member('C');

        $this->assertSame([(int) $a->getKey(), (int) $b->getKey()], array_column(team()->take(2)->get(), 'id'));
    }

    #[Test]
    public function related_to_a_service_is_who_provides_it_and_to_nothing_is_nobody(): void
    {
        $implants = $this->service('implants');
        $crowns = $this->service('crowns');
        $anna = $this->member('Anna');
        $boris = $this->member('Boris');
        $this->member('Vera');

        $anna->syncRelated(Member::SERVICES, 'service', [$implants->id, $crowns->id]);
        $boris->syncRelated(Member::SERVICES, 'service', [$crowns->id]);

        $this->assertSame(['Anna'], $this->names(team()->relatedTo('service', $implants)));
        $this->assertSame(['Anna', 'Boris'], $this->names(team()->relatedTo('service', $crowns->id)));
        $this->assertSame(['Anna', 'Boris'], $this->names(team()->relatedTo('service', [$implants, (string) $crowns->id])));
        $this->assertSame([], $this->names(team()->relatedTo('service', [])), 'the people of no service are nobody');

        $card = team()->only([$anna->id])->first();
        $this->assertSame(
            [
                ['id' => $implants->id, 'title' => 'Implants', 'url' => 'http://localhost/services/implants'],
                ['id' => $crowns->id, 'title' => 'Crowns', 'url' => 'http://localhost/services/crowns'],
            ],
            $card['service_links'] ?? null,
        );
    }

    #[Test]
    public function a_service_that_is_not_on_the_site_is_no_link(): void
    {
        $hidden = $this->service('hidden', published: false);
        $shown = $this->service('shown');
        $this->member('Anna')->syncRelated(Member::SERVICES, 'service', [$hidden->id, $shown->id]);

        $this->assertSame(['Shown'], array_column(team()->first()['service_links'] ?? [], 'title'));
    }

    #[Test]
    public function the_language_is_the_one_being_rendered_unless_asked(): void
    {
        $this->member('Anna', 'Анна работает здесь.');

        $this->app->make(Locales::class)->use('ru');

        $this->assertSame('Анна работает здесь.', team()->first()['text'] ?? null);
        $this->assertSame('Anna works here.', team()->locale('en')->first()['text'] ?? null);
    }

    #[Test]
    public function the_number_of_queries_does_not_grow_with_the_list(): void
    {
        $service = $this->service('implants');
        $this->picture('media/ab/cd/a.jpg');
        $this->picture('media/ab/cd/b.jpg');

        foreach (['A', 'B'] as $name) {
            $this->member($name, attributes: ['photo' => ['path' => 'media/ab/cd/a.jpg']])->syncRelated(Member::SERVICES, 'service', [$service->id]);
        }

        $few = $this->queries(static fn () => team()->get());

        foreach (['C', 'D', 'E', 'F'] as $name) {
            $this->member($name, attributes: ['photo' => ['path' => 'media/ab/cd/b.jpg']])->syncRelated(Member::SERVICES, 'service', [$service->id]);
        }

        $this->assertSame($few, $this->queries(static fn () => team()->get()));
    }

    #[Test]
    public function the_collection_source_hands_over_the_same_cards(): void
    {
        $implants = $this->service('implants');
        $this->member('Anna')->syncRelated(Member::SERVICES, 'service', [$implants->id]);
        $this->member('Boris');

        $source = $this->app->make(CollectionSources::class)->find('team');

        $this->assertInstanceOf(TeamSource::class, $source);
        $this->assertNull($source->categories(), 'decision 1: no categories');
        $this->assertSame(['service'], $source->relations());
        $this->assertFalse($source->supportsMarkup(), 'decision 2: no markup');
        $this->assertSame('team.view', $source->permission());
        $this->assertSame(team()->locale('en')->get(), $source->items(new Selection, 'en'));
        $this->assertSame(['Anna'], array_column($source->items(new Selection(limit: 1), 'en'), 'name'));
        $this->assertSame(['Anna'], array_column($source->items(Selection::of(['related' => ['type' => 'service', 'ids' => [$implants->id]]], $source), 'en'), 'name'));
    }

    #[Test]
    public function a_new_person_goes_to_the_end_of_the_list(): void
    {
        $first = $this->member('First');
        DB::table('team_members')->where('id', $first->getKey())->update(['position' => 40]);

        $this->assertSame(41, $this->member('Second')->position);
    }

    /**
     * @param  iterable<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function names(iterable $cards): array
    {
        $names = [];

        foreach ($cards as $card) {
            $names[] = (string) $card['name'];
        }

        return $names;
    }

    private function queries(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
