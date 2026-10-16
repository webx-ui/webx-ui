<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Mcp;

use Illuminate\Contracts\Container\Container;
use Throwable;
use WebxUi\Mcp\McpResource;
use WebxUi\Snippets\Checks\PlaceFinder;
use WebxUi\Snippets\Checks\SiteViews;
use WebxUi\Snippets\Models\Snippet;
use WebxUi\Snippets\Output\Seen;
use WebxUi\Snippets\Places;

/**
 * What an agent reads before it writes a snippet (§9.3): the rules and what is already there,
 * and the places it may choose from.
 *
 * Whether a place is printed is said twice, because each way misses something: the views say it
 * statically (a place set by an expression is not seen), the pages say it once they have been
 * opened (nothing is seen right after a deploy). Either is enough to trust the place.
 */
final class SnippetsResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'snippets://catalog',
                'Snippets catalog',
                'What a snippet is and the rules it is held to — the size limit, the paths, the consent '
                .'categories, dev and stage, where nothing is printed — and every snippet the site has, in '
                .'short. Read it before adding one, so that a service is not put on the site twice.',
                fn (): array => $this->catalog(),
            ),
            new McpResource(
                'snippets://places',
                'Snippet places',
                'The places a snippet may go to: the three built-in ones and the site\'s own from '
                .'config/webx-snippets.php, whether the views print each, and how many snippets it holds; '
                .'places no longer declared that still hold snippets, apart.',
                fn (): array => $this->places(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $environments = array_values(array_filter((array) config('webx-snippets.environments', ['production']), is_string(...)));

        return [
            'rules' => [
                'code' => 'Raw HTML and JavaScript as the service gave it, up to '.Snippet::MAX_BYTES.' bytes. No <html>, <head> or <body> — the answer of a write warns about them.',
                'places' => 'A key from snippets://places. Inside a place, snippets run in their order (snippets_reorder).',
                'consent' => 'necessary is printed at once; statistics (shown as "Analytics"), marketing and media wait until the visitor agrees to that category in the cookie banner.',
                'paths' => 'only_paths and except_paths are paths from the root without the language prefix, * for any run: "/", "/blog/*". Empty only_paths is the whole site.',
                'locales' => 'Only pages in these languages; empty is every language.',
                'environments' => 'Every enabled snippet is printed in '.implode(', ', $environments).'; anywhere else only those with on_stage.',
                'never_printed' => 'The panel, the block preview and the panel\'s error pages never get snippets.',
                'enabled' => 'A new snippet is off until enabled: true. Turn one on only when a person asked for it.',
            ],
            'environment' => app()->environment(),
            'snippets' => Snippet::query()->orderBy('place')->orderBy('position')->orderBy('id')->get()
                ->map(fn (Snippet $snippet): array => [
                    'id' => $snippet->id,
                    'name' => $snippet->name,
                    'place' => $snippet->place,
                    'place_declared' => $this->container->make(Places::class)->has($snippet->place),
                    'consent' => $snippet->consent,
                    'enabled' => $snippet->enabled,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function places(): array
    {
        $places = $this->container->make(Places::class);
        $counts = Snippet::query()->selectRaw('place, count(*) as total, sum(case when enabled then 1 else 0 end) as enabled')
            ->groupBy('place')
            ->get()
            ->keyBy('place');
        $inViews = $this->inViews();
        $seen = $this->container->make(Seen::class)->all();

        $count = static fn (string $key, string $column): int => (int) ($counts->get($key)?->getAttribute($column) ?? 0);
        $declared = [];

        foreach ($places->keys() as $key) {
            $declared[] = [
                'key' => $key,
                'label' => $places->label($key),
                'built_in' => $places->builtIn($key),
                // Null when the views could not be searched: unknown, not "no".
                'in_views' => $inViews === null ? null : in_array($key, $inViews, true),
                'last_seen_on_a_page' => $seen[$key] ?? null,
                'snippets' => $count($key, 'total'),
                'enabled' => $count($key, 'enabled'),
            ];
        }

        $undeclared = [];

        foreach ($counts->keys() as $key) {
            if (! $places->has((string) $key)) {
                $undeclared[] = ['key' => (string) $key, 'snippets' => $count((string) $key, 'total'), 'printed' => false];
            }
        }

        return [
            'places' => $declared,
            'undeclared' => $undeclared,
            'own_place' => 'A site adds a place in config/webx-snippets.php (places: key => label) and prints it with <x-webx-snippets place="key" /> in its views. Places are not edited from the panel or by tools.',
        ];
    }

    /**
     * The places some view the site reads prints, by a static search.
     *
     * @return list<string>|null
     */
    private function inViews(): ?array
    {
        try {
            $paths = $this->container->make(SiteViews::class)->paths();

            return array_map(strval(...), array_keys($this->container->make(PlaceFinder::class)->find($paths)['places']));
        } catch (Throwable) {
            return null;
        }
    }
}
