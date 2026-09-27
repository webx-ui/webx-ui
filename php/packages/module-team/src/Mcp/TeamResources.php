<?php

declare(strict_types=1);

namespace WebxUi\Team\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Team\Models\Member;
use WebxUi\Team\Networks;
use WebxUi\Team\Panel\MemberNames;

/**
 * What an agent reads before it writes a person (§5.8): the whole team in one message.
 *
 * Everybody out of the bin, in the one order every block shows them in, unpublished people too —
 * the point of reading this first is not to type Anna in a second time beside the copy that is not
 * out yet. The networks the site has head it, because a link to any other one is refused.
 *
 * One name per row, in the language the agent works in; `written_in` says where the text is — the
 * person is shown in every language either way (decision 8) — and `team_get` has every language of
 * the one it picks.
 */
final class TeamResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'team://catalog',
                'Team catalog',
                'Every person of the team in the order the site shows them — the name, the job title, whether '
                .'they are published, the languages their text is written in and the services they are linked '
                .'to — with the social networks this site accepts. Read it before adding a person, so that you '
                .'reuse rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $codes = $locales->codes();
        $services = $this->container->make(RelationTargets::class)->find('service') !== null;

        return [
            'locales' => $codes,
            'default_locale' => $locales->defaultCode(),
            'networks' => $this->container->make(Networks::class)->all(),
            'members' => Member::query()->orderBy('position')->orderBy('id')->get()
                ->map(static function (Member $member) use ($locales, $codes, $services): array {
                    $row = [
                        'id' => (int) $member->getKey(),
                        'name' => MemberNames::of($member, $locales),
                        'job_title' => $member->wordsIn('job_title', $locales->current(), $locales->defaultCode()),
                        'published' => $member->published,
                        'written_in' => array_values(array_filter($codes, $member->writtenIn(...))),
                    ];

                    if ($services) {
                        $row['services'] = $member->relatedIds(Member::SERVICES);
                    }

                    return $row;
                })
                ->values()
                ->all(),
        ];
    }
}
