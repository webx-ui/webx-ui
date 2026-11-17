<?php

declare(strict_types=1);

namespace WebxUi\Themes\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use WebxUi\Cache\CacheGroups;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeCritical;

/**
 * `webx:theme:critical` — the critical CSS of the site (spec §13.3).
 *
 * Opens each address `critical` of theme.json names in Chromium, on a desktop (1440×900) and on
 * a phone (390×844), and keeps the rules the first screen and a quarter below it draw with,
 * the rules that hide a closed window, the fonts, keyframes and properties, and an `@media`
 * only where its condition holds. lightningcss minifies the result and writes the prefixes.
 * The file it writes ({@see ThemeCritical::path()}) is printed inline by `@webxTheme`, and the
 * page's stylesheets stop blocking.
 *
 * `--check` draws the first screen of the same pages with the critical CSS alone — every
 * stylesheet refused — and with the full CSS, and says where a pixel differs. Run it after a
 * change of the look, a sync or a build: the file is made of what the pages linked then.
 *
 * The browser half is `resources/critical/critical.mjs`, with `playwright` and `lightningcss`
 * from the site's node_modules: Node is the site's, as for its Vite build.
 */
final class CriticalCommand extends Command
{
    /** @var string */
    protected $signature = 'webx:theme:critical
        {--url= : The address of the running site the pages are opened on (APP_URL by default)}
        {--check : Compare the first screen with the critical CSS alone against the full CSS instead of writing}
        {--dry-run : Say how large the critical CSS would be without writing it}';

    /** @var string */
    protected $description = 'Write the critical CSS of the first screen of the theme\'s pages, or --check that it draws them as the full CSS does';

    /** The targets lightningcss prefixes for when the site has no browserslist. */
    private const array TARGETS = ['chrome' => 109, 'edge' => 109, 'firefox' => 115, 'safari' => 15, 'ios_saf' => 15, 'samsung' => 20];

    private const string BROWSERS = '>= 0.5%, last 2 versions, not dead';

    public function handle(ThemeChain $chain, ThemeCritical $critical): int
    {
        if ($chain->isEmpty()) {
            $this->components->error('The site has no theme (webx-themes.theme).');

            return self::FAILURE;
        }

        $pages = $chain->critical();

        if ($pages === []) {
            $this->components->error('No layer names the pages: "critical": ["/", "/blog/first-article"] in theme/theme.json.');

            return self::FAILURE;
        }

        $url = $this->option('url');
        $base = rtrim(is_string($url) && $url !== '' ? $url : (string) $this->laravel->make('config')->get('app.url'), '/');

        if (preg_match('~^https?://~', $base) !== 1) {
            $this->components->error("The site's address is not a URL: {$base}. --url=https://…");

            return self::FAILURE;
        }

        $check = (bool) $this->option('check');

        if ($check && $critical->css() === '') {
            $this->components->error('There is no critical CSS to check: '.$this->relative($critical->path()).'. php artisan webx:theme:critical');

            return self::FAILURE;
        }

        $answer = $this->browser([
            'mode' => $check ? 'check' : 'extract',
            'root' => $this->laravel->basePath(),
            'base' => $base,
            'pages' => $pages,
            'viewports' => ThemeCritical::VIEWPORTS,
            'below' => ThemeCritical::BELOW,
            'browsers' => self::BROWSERS,
            'targets' => array_map(static fn (int $major): int => $major << 16, self::TARGETS),
            'shots' => $this->laravel->storagePath('framework/webx-critical'),
        ]);

        if ($answer === null) {
            return self::FAILURE;
        }

        return $check ? $this->report($answer) : $this->write($critical, $answer, count($pages));
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function write(ThemeCritical $critical, array $answer, int $pages): int
    {
        $css = is_string($answer['css'] ?? null) ? trim($answer['css']) : '';

        if ($css === '') {
            $this->components->error('The pages gave no CSS: do they link their stylesheets? php artisan webx:theme:sync, npm run build');

            return self::FAILURE;
        }

        $size = sprintf('%.1f KB', strlen($css) / 1024).(function_exists('gzencode') ? sprintf(', %.1f KB gzipped', strlen((string) gzencode($css, 9)) / 1024) : '');
        $path = $critical->path();

        if ($this->option('dry-run')) {
            $this->components->info("Would write {$this->relative($path)}: {$size}, from {$pages} pages × ".count(ThemeCritical::VIEWPORTS).' screens.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, $css."\n");

        // A page cached with the head it had keeps blocking on its stylesheets until it is drawn again.
        $this->laravel->make(CacheGroups::class)->bump(CacheGroups::THEME);

        $this->components->info("Written {$this->relative($path)}: {$size}, from {$pages} pages × ".count(ThemeCritical::VIEWPORTS).' screens.');

        // Two pages that apply a pair of rules in opposite orders cannot share one file faithfully.
        $conflicts = is_int($answer['conflicts'] ?? null) ? $answer['conflicts'] : 0;
        if ($conflicts > 0) {
            $this->components->warn("{$conflicts} rules come in opposite orders on different pages: one of those pages may draw differently.");
        }
        $this->line('  Check it: php artisan webx:theme:critical --check');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function report(array $answer): int
    {
        $failed = 0;

        foreach (is_array($answer['results'] ?? null) ? $answer['results'] : [] as $result) {
            if (! is_array($result) || ! is_array($result['viewport'] ?? null)) {
                continue;
            }

            $screen = implode('×', array_map('strval', $result['viewport']));
            $differ = (int) ($result['differ'] ?? 0);
            $line = sprintf('%s at %s', (string) ($result['path'] ?? ''), $screen);

            if ($differ === 0) {
                $this->components->twoColumnDetail($line, '<fg=green>the same</>');

                continue;
            }

            $failed++;
            $share = $differ / max(1, (int) ($result['pixels'] ?? 1)) * 100;
            $this->components->twoColumnDetail($line, sprintf('<fg=red>%d pixels differ (%.2f%%)</>', $differ, $share));
        }

        if ($failed > 0) {
            $this->components->error("{$failed} first screens differ — the shots are in ".$this->relative($this->laravel->storagePath('framework/webx-critical')).'. php artisan webx:theme:critical, then --check again.');

            return self::FAILURE;
        }

        $this->components->info('The critical CSS draws every first screen as the full CSS does.');

        return self::SUCCESS;
    }

    /**
     * Runs the browser half and reads its answer; null when it failed, said already.
     *
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>|null
     */
    private function browser(array $job): ?array
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->components->error('Node is not installed: the critical CSS is read in a browser that Node drives.');

            return null;
        }

        $file = tempnam(sys_get_temp_dir(), 'webx-critical-');

        if ($file === false) {
            $this->components->error('No temporary file for the job.');

            return null;
        }

        file_put_contents($file, json_encode($job, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        try {
            $process = new Process([$node, __DIR__.'/../../resources/critical/critical.mjs', $file], $this->laravel->basePath(), null, null, 900);
            $process->run();
        } finally {
            @unlink($file);
        }

        $lines = array_filter(explode("\n", trim($process->getOutput())));
        $answer = json_decode((string) end($lines), true);

        if (! is_array($answer)) {
            $this->components->error('The browser did not answer: '.trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput()));

            return null;
        }

        if (isset($answer['error'])) {
            $this->components->error((string) (is_string($answer['error']) ? $answer['error'] : 'The browser failed.'));

            return null;
        }

        /** @var array<string, mixed> $answer */
        return $answer;
    }

    private function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $this->laravel->basePath()), '/').'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
