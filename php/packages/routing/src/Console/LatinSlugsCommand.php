<?php

declare(strict_types=1);

namespace WebxUi\Routing\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\LatinSlugs;

/**
 * Moves the slugs saved before the rule to ASCII, with a 301 from every old address.
 *
 * Shows what it would do until told otherwise: this rewrites a site's addresses, and it is run by
 * hand on each site, after reading the list. Exit code 1 when a record was refused — its address
 * is taken — so that nobody takes «done» for all of them.
 */
final class LatinSlugsCommand extends Command
{
    protected $signature = 'webx:slugs:latin
        {--dry-run : List what would change; this is the default}
        {--apply : Change the slugs and write the redirects}';

    protected $description = 'Move slugs saved in another alphabet to ASCII, with redirects from the old addresses';

    public function handle(LatinSlugs $slugs): int
    {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $found = iterator_to_array($slugs->found(), false);

        if ($found === []) {
            $this->components->info('Every slug is in ASCII.');

            return self::SUCCESS;
        }

        $this->table(['Model', 'Id', 'Language', 'Was', 'Becomes'], array_map(static fn (array $one): array => [
            class_basename($one['model']), $one['id'], $one['locale'] ?? '—', $one['from'], $one['to'],
        ], $found));

        if (! $apply) {
            $this->components->warn(count($found).' slug(s) would change, a suffix added where the address is taken. Nothing was written: run with --apply.');

            return self::SUCCESS;
        }

        return $this->apply($slugs, $found);
    }

    /**
     * @param  list<array{model: class-string<Model>, id: int|string, locale: string|null, from: string, to: string}>  $found
     */
    private function apply(LatinSlugs $slugs, array $found): int
    {
        $records = [];

        foreach ($found as $one) {
            $records[$one['model'].'#'.$one['id']] = [$one['model'], $one['id']];
        }

        $redirects = 0;
        $unkept = 0;
        $refused = [];

        foreach ($records as [$model, $id]) {
            $result = $slugs->move($model, $id);

            if ($result['refused'] !== null) {
                $refused[] = class_basename($model).' '.$id.': '.$result['refused'];

                continue;
            }

            if ($slugs->redirect($result['moved'])) {
                $redirects += count($result['moved']);
            } else {
                $unkept += count($result['moved']);
            }
        }

        $this->components->info((count($records) - count($refused)).' record(s) moved, '.$redirects.' redirect(s) written.');

        if ($unkept > 0) {
            $this->components->warn($unkept.' address(es) moved with no redirect written: module-seo is not installed. The registry\'s aliases still answer for them.');
        }

        foreach ($refused as $line) {
            $this->components->error($line);
        }

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }
}
