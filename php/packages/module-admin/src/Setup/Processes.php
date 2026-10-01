<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

use Illuminate\Support\Composer;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The child processes the installing commands run: Composer, npm and `artisan` itself.
 *
 * One place for `webx:setup` and `webx:module:add`, so that both find the same binaries the same
 * way, and one seam for the tests to stand in for: what those commands are worth is the order
 * they run things in and what they do when one of them fails, and a test that really ran
 * `composer require` would be a test of the network.
 *
 * Not final for that reason — the tests bind a recording subclass.
 */
class Processes
{
    public function __construct(private readonly Composer $composer) {}

    /**
     * Run a command to the end and hand back its exit code, its output streamed as it comes.
     *
     * No timeout: `composer require` of seven packages and `npm install` both take minutes on a
     * cold cache, and a run killed halfway leaves the site between two states.
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env  Over the top of what the child inherits.
     * @param  (callable(string): void)|null  $output
     */
    public function run(array $command, string $cwd, array $env = [], ?callable $output = null): int
    {
        $process = new Process($command, $cwd, $env);
        $process->setTimeout(null);

        return $process->run(static function (string $type, string $buffer) use ($output): void {
            if ($output !== null) {
                $output($buffer);
            }
        });
    }

    /**
     * `artisan` of the application in `$base`, as a child: it boots with the `.env` and the
     * autoloader as they are now, not as they were when this process started.
     *
     * @return list<string>
     */
    public function artisan(string $base): array
    {
        return [PHP_BINARY, $base.DIRECTORY_SEPARATOR.'artisan'];
    }

    /**
     * Composer as named, or as found beside the application or on the PATH.
     *
     * @return list<string>
     */
    public function composer(string $base, ?string $named = null): array
    {
        // The container binds Composer under the string `composer`, with the base path in it;
        // built by class name it has no path at all, and `findComposer()` then looks for
        // `/composer.phar` at the root of the drive. Setting it here covers both.
        return $this->executable(
            $this->composer->setWorkingPath($base)->findComposer($named !== '' ? $named : null),
            'composer',
        );
    }

    /** @return list<string> */
    public function npm(): array
    {
        return $this->executable(['npm'], 'npm');
    }

    /**
     * A bare name is not a program on Windows: `proc_open` gets the array as it is, so
     * `npm` — which is `npm.cmd` — is simply not found. The finder knows about PATHEXT.
     *
     * @param  list<string>  $found
     * @return list<string>
     */
    private function executable(array $found, string $name): array
    {
        if ($found !== [$name]) {
            return $found;
        }

        $path = (new ExecutableFinder)->find($name);

        return $path === null ? $found : [$path];
    }
}
