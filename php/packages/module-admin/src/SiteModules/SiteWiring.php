<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteModules;

use Illuminate\Filesystem\Filesystem;
use JsonException;
use stdClass;

/**
 * What a site needs once to carry modules of its own, and each module once more (SITE_MODULES
 * §6, §7, §9.3) — written into the site's files, idempotently, with a line per file.
 *
 * - `composer.json`: the path repository `modules/*`, and the autoload of the module's tests
 *   (Composer never reads `autoload-dev` of a dependency, so the site's has to carry it);
 * - `phpunit.xml`: the suite `Modules`, so `php artisan test` runs them;
 * - `package.json`: what the module's front end is checked and tested with, and two scripts;
 * - `modules/tsconfig.json`: the compiler options every module's `js/` shares.
 *
 * Two files are only read. The Vue plugin in `vite.config.*` is a line of code in the site's own
 * file: without a marker to write between, it is named rather than guessed at. The `Dockerfile`
 * is the site's too — `webx:doctor` has the check, and the line here says what it will ask for.
 *
 * Nothing that is there is changed: a dependency at another range, a script of the same name, a
 * repository written differently are the site's decisions.
 */
final class SiteWiring
{
    /** What the module's `js/` is checked and tested with: devDependencies of a `file:` package are never installed. */
    public const DEV_DEPENDENCIES = [
        '@types/node' => '^24.10.1',
        '@vitejs/plugin-vue' => '^6.0.1',
        '@vue/test-utils' => '^2.4.6',
        'jsdom' => '^28.0.0',
        'typescript' => '^5.9.3',
        'vitest' => '^5.0.0',
        'vue-tsc' => '^3.1.3',
    ];

    public const SCRIPTS = [
        'typecheck:modules' => 'vue-tsc -p modules/tsconfig.json --noEmit',
        'test:modules' => 'vitest run --dir modules',
    ];

    private const REPOSITORY = 'modules/*';

    private const SUITE = 'modules/*/tests';

    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array<string, string> File → what was done, or what is left to do by hand.
     */
    public function wire(string $base, ModuleName $name): array
    {
        return [
            'composer.json' => $this->composer($base.'/composer.json', $name),
            'phpunit.xml' => $this->phpunit($base.'/phpunit.xml'),
            'package.json' => $this->package($base.'/package.json'),
            'modules/tsconfig.json' => $this->tsconfig($base.'/modules/tsconfig.json'),
            ...$this->vite($base),
            ...$this->dockerfile($base.'/Dockerfile'),
        ];
    }

    private function composer(string $path, ModuleName $name): string
    {
        $json = $this->readJson($path);

        if ($json === null) {
            return 'not found — add the path repository "modules/*" and the autoload of the tests by hand';
        }

        $done = [];

        if (! $this->hasRepository($json)) {
            $repository = (object) ['type' => 'path', 'url' => self::REPOSITORY, 'options' => (object) ['symlink' => true]];

            if (($json->repositories ?? null) instanceof stdClass) {
                $json->repositories->{'site-modules'} = $repository;
            } else {
                $json->repositories = [...(is_array($json->repositories ?? null) ? $json->repositories : []), $repository];
            }

            $done[] = 'the path repository modules/*';
        }

        $namespace = $name->namespace().'Tests\\';
        $json->{'autoload-dev'} ??= new stdClass;
        $json->{'autoload-dev'}->{'psr-4'} ??= new stdClass;

        if (! isset($json->{'autoload-dev'}->{'psr-4'}->{$namespace})) {
            $json->{'autoload-dev'}->{'psr-4'}->{$namespace} = $name->folder().'/tests/';
            $done[] = "the autoload of {$namespace}";
        }

        if ($done === []) {
            return 'already has the path repository and the autoload of the tests';
        }

        $this->writeJson($path, $json, '    ');

        return 'added '.implode(' and ', $done);
    }

    private function hasRepository(stdClass $json): bool
    {
        $repositories = $json->repositories ?? [];

        foreach (is_array($repositories) || $repositories instanceof stdClass ? (array) $repositories : [] as $repository) {
            if ($repository instanceof stdClass && ($repository->type ?? null) === 'path' && rtrim((string) ($repository->url ?? ''), '/') === self::REPOSITORY) {
                return true;
            }
        }

        return false;
    }

    private function phpunit(string $path): string
    {
        $line = '<testsuite name="Modules"><directory>'.self::SUITE.'</directory></testsuite>';

        if (! $this->files->exists($path)) {
            return "not found — add {$line} to the configuration PHPUnit runs";
        }

        $xml = $this->files->get($path);

        if (str_contains($xml, self::SUITE)) {
            return 'already runs modules/*/tests';
        }

        if (preg_match('#^([ \t]*)</testsuites>#m', $xml, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return "no <testsuites> to add to — add {$line}";
        }

        [$closing, $at] = $match[0];
        $indent = $match[1][0];
        $inner = $indent.($indent === '' ? '    ' : $indent);
        $suite = "{$inner}<testsuite name=\"Modules\">\n{$inner}{$this->step($indent)}<directory>".self::SUITE."</directory>\n{$inner}</testsuite>\n";

        $this->files->put($path, substr($xml, 0, $at).$suite.substr($xml, $at));

        return 'added the suite Modules (modules/*/tests)';
    }

    /** One more level of the indentation the file already uses. */
    private function step(string $indent): string
    {
        return $indent === '' ? '    ' : $indent;
    }

    private function package(string $path): string
    {
        $json = $this->readJson($path);

        if ($json === null) {
            return 'not found — the front end of the module needs the site\'s package.json';
        }

        $json->devDependencies ??= new stdClass;
        $json->scripts ??= new stdClass;
        $added = [];
        $kept = [];

        foreach (self::DEV_DEPENDENCIES as $package => $range) {
            if (! isset($json->devDependencies->{$package}) && ! isset($json->dependencies->{$package})) {
                $json->devDependencies->{$package} = $range;
                $added[] = $package;
            }
        }

        foreach (self::SCRIPTS as $script => $command) {
            if (! isset($json->scripts->{$script})) {
                $json->scripts->{$script} = $command;
                $added[] = "the script {$script}";
            } elseif ($json->scripts->{$script} !== $command) {
                $kept[] = "{$script} is yours, kept";
            }
        }

        if ($added === []) {
            return 'already has what the modules are checked with'.($kept === [] ? '' : ' ('.implode(', ', $kept).')');
        }

        $devDependencies = (array) $json->devDependencies;
        ksort($devDependencies);
        $json->devDependencies = (object) $devDependencies;

        $this->writeJson($path, $json, $this->indentOf($this->files->get($path)));

        return 'added '.implode(', ', $added).($kept === [] ? '' : '; '.implode(', ', $kept));
    }

    private function tsconfig(string $path): string
    {
        if ($this->files->exists($path)) {
            return 'already there';
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, (string) json_encode([
            'compilerOptions' => [
                'target' => 'ES2022',
                'module' => 'ESNext',
                'moduleResolution' => 'Bundler',
                'lib' => ['ES2022', 'DOM', 'DOM.Iterable'],
                'types' => ['node'],
                'strict' => true,
                'noUncheckedIndexedAccess' => true,
                'isolatedModules' => true,
                'skipLibCheck' => true,
                'jsx' => 'preserve',
                'noEmit' => true,
            ],
            'include' => ['*/js/src/**/*.ts', '*/js/src/**/*.vue'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return 'written: the compiler options of every module\'s js/';
    }

    /**
     * @return array<string, string>
     */
    private function vite(string $base): array
    {
        foreach (['vite.config.js', 'vite.config.mjs', 'vite.config.ts'] as $file) {
            if (! $this->files->exists($base.'/'.$file)) {
                continue;
            }

            $source = $this->files->get($base.'/'.$file);

            if (str_contains($source, '@vitejs/plugin-vue') && preg_match('/\bvue\s*\(/', $source) === 1) {
                return [$file => 'already builds single-file components'];
            }

            return [$file => "add import vue from '@vitejs/plugin-vue' at the top and vue() to plugins: the module's .vue files are built from their sources"];
        }

        return ['vite.config.js' => 'not found — the site builds its panel with Vite and @vitejs/plugin-vue'];
    }

    /**
     * @return array<string, string>
     */
    private function dockerfile(string $path): array
    {
        if (! $this->files->exists($path)) {
            return [];
        }

        return str_contains($this->files->get($path), 'modules')
            ? []
            : ['Dockerfile' => 'COPY modules ./modules before npm ci in the stage that builds the front end — php artisan webx:doctor checks it'];
    }

    private function readJson(string $path): ?stdClass
    {
        if (! $this->files->exists($path)) {
            return null;
        }

        try {
            $json = json_decode($this->files->get($path), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $json instanceof stdClass ? $json : null;
    }

    /** As Composer and npm write it: pretty, slashes and letters as they are, a newline at the end. */
    private function writeJson(string $path, stdClass $json, string $indent): void
    {
        $encoded = (string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($indent !== '    ') {
            $encoded = (string) preg_replace_callback(
                '/^((?: {4})+)/m',
                static fn (array $match): string => str_repeat($indent, intdiv(strlen($match[1]), 4)),
                $encoded,
            );
        }

        $this->files->put($path, $encoded."\n");
    }

    /** The indentation of a JSON file: what its first indented line starts with. */
    private function indentOf(string $source): string
    {
        return preg_match('/^([ \t]+)"/m', $source, $match) === 1 ? $match[1] : '  ';
    }
}
