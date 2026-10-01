<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Catalog;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\History\HistoryContext;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogLandings\Http\LandingForm;
use WebxUi\CatalogLandings\Jobs\GenerateLandings;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Models\LandingRun;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Reserved;
use WebxUi\Routing\UniquePath;
use WebxUi\Routing\UrlNormaliser;

/**
 * «Create in bulk» (§8.3 of the landings spec): bases × the values of one facet, a landing per
 * pair, named and addressed by templates. The panel's action and, in L4, the agent's tool call the
 * same service; `dry_run` is the preview.
 *
 * The preview is a table — address, H1, products, conflict — and the conflicts are what a save
 * would refuse or a reader would not want: the slug taken (by whom), the set already a landing
 * (which), no products, no slug to fill the template with. A conflicting row is skipped and the
 * others are made anyway.
 *
 * Up to `generate.sync_limit` rows are made inside the request; more are written to a run and
 * handed to the queue a chunk at a time, as the core's bulk actions are. Each landing goes through
 * {@see LandingForm::save()}, so a row that turned taken since the preview is refused with the
 * form's own words and its neighbours go on.
 */
final class LandingGenerator
{
    public const SLUG_TAKEN = 'slug-taken';

    public const SET_TAKEN = 'set-taken';

    public const EMPTY = 'empty';

    public const NO_SLUG = 'no-slug';

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Facets $facets,
        private readonly LandingWords $words,
        private readonly LandingForm $form,
        private readonly Locales $locales,
        private readonly UniquePath $paths,
        private readonly Reserved $reserved,
        private readonly HistoryContext $history,
        private readonly Dispatcher $bus,
        private readonly Config $config,
    ) {}

    /**
     * The request checked: the bases, the facet, the templates.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        /** @var array<string, mixed> $params */
        $params = Validator::make($input, [
            'categories' => ['required', 'array', 'min:1', 'max:500'],
            'categories.*' => ['integer'],
            'subtree' => ['sometimes', 'boolean'],
            'facet' => ['required', 'string', 'max:64'],
            'values' => ['nullable', 'array', 'max:5000'],
            'values.*' => ['required'],
            'min_products' => ['nullable', 'integer', 'min:0'],
            'slug' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'h1' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'publish' => ['sometimes', 'boolean'],
        ])->validate();

        $facet = $this->facets->find((string) $params['facet']);

        if ($facet === null || $facet->key() === CategoryFacet::KEY || $facet->kind() === FacetKind::Range) {
            throw ValidationException::withMessages([
                'facet' => [(string) __('webx-catalog-landings::errors.generate-facet', ['key' => (string) $params['facet']])],
            ]);
        }

        $slug = trim((string) ($params['slug'] ?? ''));

        if ($slug !== '' && ! str_contains($slug, '{value}')) {
            throw ValidationException::withMessages([
                'slug' => [(string) __('webx-catalog-landings::errors.generate-template')],
            ]);
        }

        return $params;
    }

    /**
     * What a generation would make, row by row, nothing written.
     *
     * @param  array<string, mixed>  $params  checked by {@see validate()}
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function plan(array $params): array
    {
        $facet = $this->facets->find((string) $params['facet']);
        assert($facet instanceof Facet);

        $chosen = isset($params['values']) && is_array($params['values'])
            ? array_values(array_unique(array_map(static fn (mixed $value): string => trim((string) $value), $params['values'])))
            : null;
        $least = max(1, (int) ($params['min_products'] ?? 1));
        $templates = $this->templates($params);
        $locale = $this->locales->current();
        $max = (int) $this->config->get('webx-catalog-landings.generate.max', 5000);

        $rows = [];
        /** @var array<string, true> $claimed  slugs this generation takes, per language */
        $claimed = [];

        foreach ($this->bases($params) as $category) {
            $counts = $this->counts($category, $facet);
            $values = $chosen ?? array_keys(array_filter($counts, static fn (int $count): bool => $count >= $least));

            foreach ($values as $value) {
                $value = (string) $value;

                if (count($rows) >= $max) {
                    throw ValidationException::withMessages([
                        'categories' => [(string) __('webx-catalog-landings::errors.generate-too-many', ['max' => $max])],
                    ]);
                }

                $rows[] = $this->row($category, $facet, $value, (int) ($counts[$value] ?? 0), $templates, $locale, $claimed);
            }
        }

        return $rows;
    }

    /**
     * Make the free rows: at once when there are few, through the queue otherwise.
     *
     * @param  array<string, mixed>  $params  checked by {@see validate()}
     *
     * @throws ValidationException
     */
    public function run(array $params, ?Authenticatable $admin = null): LandingRun
    {
        $rows = $this->plan($params);
        $free = array_values(array_filter($rows, static fn (array $row): bool => $row['conflict'] === null));
        $input = array_map(fn (array $row): array => $this->input($row, $params), $free);

        $run = (new LandingRun)->forceFill([
            'admin_id' => $admin === null ? $this->history->adminId() : (int) $admin->getAuthIdentifier(),
            'admin_name' => $admin === null ? $this->history->adminName() : (string) ($admin->name ?? ''),
            'rows' => $input,
            'total' => count($rows),
            'skipped' => count($rows) - count($free),
            'status' => LandingRun::QUEUED,
        ]);

        if (count($input) <= (int) $this->config->get('webx-catalog-landings.generate.sync_limit', 50)) {
            $this->process($run, $input);
            $run->forceFill(['status' => LandingRun::DONE, 'rows' => [], 'created_at' => Carbon::now(), 'finished_at' => Carbon::now()]);

            return $run;
        }

        $run->save();

        // After the save: a worker that picks the job up must find the run.
        $this->bus->dispatch(new GenerateLandings($run->id));

        return $run->refresh();
    }

    /** One chunk of a queued run, and whether another is left. What the queued job calls. */
    public function chunk(int $runId): bool
    {
        $run = LandingRun::query()->find($runId);

        if ($run === null || $run->finished()) {
            return false;
        }

        $size = max(1, (int) $this->config->get('webx-catalog-landings.generate.chunk', 100));
        $slice = array_slice($run->rows, $run->cursor, $size);

        if ($slice !== []) {
            $admin = $run->admin_id === null ? null : new GenericUser(['id' => $run->admin_id, 'name' => $run->admin_name]);

            $this->history->during(HistoryContext::BULK, $admin, null, function () use ($run, $slice): void {
                $this->process($run, $slice);
            });
        }

        $run->cursor += count($slice);
        $run->status = LandingRun::RUNNING;

        if ($run->cursor >= count($run->rows)) {
            $run->status = LandingRun::DONE;
            $run->finished_at = Carbon::now();
        }

        $run->save();

        return ! $run->finished();
    }

    /** Mark a run the queue gave up on, so that the panel stops waiting for it. */
    public function fail(int $runId): void
    {
        $run = LandingRun::query()->find($runId);

        if ($run !== null && ! $run->finished()) {
            $run->forceFill(['status' => LandingRun::FAILED, 'finished_at' => Carbon::now()])->save();
        }
    }

    /**
     * Save each row through the form; a refusal is an error beside its slug, the rest go on.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function process(LandingRun $run, array $rows): void
    {
        $errors = $run->errors ?? [];

        foreach ($rows as $row) {
            try {
                DB::transaction(fn () => $this->form->save(new Landing, $row));
                $run->done++;
            } catch (ValidationException $refusal) {
                $run->failed++;
                $errors[] = $this->error($row, (string) collect($refusal->errors())->flatten()->first());
            } catch (Throwable $failure) {
                report($failure);
                $run->failed++;
                $errors[] = $this->error($row, $failure->getMessage());
            }
        }

        $run->errors = array_slice($errors, 0, LandingRun::ERRORS_KEPT);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{slug: string, name: string, message: string}
     */
    private function error(array $row, string $message): array
    {
        $locale = $this->locales->current();

        return [
            'slug' => (string) ($row['slug'][$locale] ?? reset($row['slug']) ?: ''),
            'name' => (string) ($row['name'][$locale] ?? reset($row['name']) ?: ''),
            'message' => $message,
        ];
    }

    /**
     * The categories the generation goes over: the ones named, with everything under them when
     * `subtree` asks — each once, in the tree's order.
     *
     * @param  array<string, mixed>  $params
     * @return list<Category>
     */
    private function bases(array $params): array
    {
        $ids = array_map('intval', (array) $params['categories']);

        if ((bool) ($params['subtree'] ?? false)) {
            $below = [];

            foreach (Category::query()->whereKey($ids)->get() as $category) {
                array_push($below, ...$category->subtree()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
            }

            $ids = [...$ids, ...$below];
        }

        $found = Category::query()->whereKey(array_unique($ids))->orderBy('lft')->get();

        if ($found->isEmpty()) {
            throw ValidationException::withMessages([
                'categories' => [(string) __('validation.exists', ['attribute' => 'categories'])],
            ]);
        }

        return array_values($found->all());
    }

    /**
     * The values of the facet on a base with their counts, as the storefront counts them.
     *
     * @return array<string, int>
     */
    private function counts(Category $category, Facet $facet): array
    {
        $result = $this->catalog->engine()->search(new CatalogQuery(
            locale: $this->locales->current(),
            context: FilterContext::CATEGORY,
            contextId: $category->id,
            scope: [CategoryFacet::KEY => FacetValue::of([(string) $category->id])],
            count: [$facet->key()],
            perPage: 1,
        ));

        $counts = [];

        foreach ($result->facet($facet->key())->counts ?? [] as $value => $count) {
            $counts[(string) $value] = (int) $count;
        }

        return $counts;
    }

    /**
     * @param  array<string, string>  $templates
     * @param  array<string, true>  $claimed
     * @return array<string, mixed>
     */
    private function row(Category $category, Facet $facet, string $value, int $count, array $templates, string $locale, array &$claimed): array
    {
        $set = LandingSet::from([$facet->key() => ['values' => [$value]]]);
        $slugs = $this->words->slugs($category, $set, $templates['slug']);
        $names = $this->words->names($category, $set, $templates['name']);

        $row = [
            'category_id' => (int) $category->id,
            'category' => $category->displayName($locale),
            'value' => $value,
            'label' => (string) ($facet->labels([$value], $locale)[$value] ?? $value),
            'filters' => $set->all(),
            'slug' => $slugs,
            'name' => $names,
            'h1' => $templates['h1'] === '' ? [] : $this->words->names($category, $set, $templates['h1']),
            'title' => $templates['title'] === '' ? [] : $this->words->names($category, $set, $templates['title']),
            'description' => $templates['description'] === '' ? [] : $this->words->names($category, $set, $templates['description']),
            'count' => $count,
            'conflict' => null,
            'message' => null,
        ];

        if ($slugs === [] || $names === []) {
            return [...$row, 'conflict' => self::NO_SLUG, 'message' => (string) __('webx-catalog-landings::generate.conflict-no-slug')];
        }

        $holder = $this->form->holder((int) $category->id, $set);

        if ($holder !== null) {
            return [...$row, 'conflict' => self::SET_TAKEN, 'message' => (string) __('webx-catalog-landings::generate.conflict-set-taken', ['name' => $holder->label()])];
        }

        // A landing of no id: every canonical address another entity holds — a landing's too —
        // counts as taken.
        $probe = new Landing;
        $probe->id = 0;

        foreach ($slugs as $language => $slug) {
            $path = UrlNormaliser::key($slug);

            if (isset($claimed[$language.'|'.$path]) || $this->reserved->taken($path) || $this->paths->taken($path, $language, $probe)) {
                return [...$row, 'conflict' => self::SLUG_TAKEN, 'message' => (string) __('webx-catalog-landings::generate.conflict-slug-taken', ['slug' => $slug])];
            }
        }

        if ($count === 0) {
            return [...$row, 'conflict' => self::EMPTY, 'message' => (string) __('webx-catalog-landings::generate.conflict-empty')];
        }

        foreach ($slugs as $language => $slug) {
            $claimed[$language.'|'.UrlNormaliser::key($slug)] = true;
        }

        return $row;
    }

    /**
     * What the form saves for a free row.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function input(array $row, array $params): array
    {
        $seo = array_filter(['title' => $row['title'], 'description' => $row['description']], static fn (array $words): bool => $words !== []);

        return [
            'category_id' => $row['category_id'],
            'filters' => $row['filters'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'h1' => $row['h1'] === [] ? null : $row['h1'],
            'is_published' => (bool) ($params['publish'] ?? false),
            ...($seo === [] ? [] : ['seo' => $seo]),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{slug: string, name: string, h1: string, title: string, description: string}
     */
    private function templates(array $params): array
    {
        $word = static fn (string $key, string $default): string => trim((string) ($params[$key] ?? '')) === '' ? $default : trim((string) $params[$key]);

        return [
            'slug' => $word('slug', '{category}-{value}'),
            'name' => $word('name', '{category} {value}'),
            'h1' => $word('h1', ''),
            'title' => $word('title', ''),
            'description' => $word('description', ''),
        ];
    }
}
