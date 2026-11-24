<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Runner;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Settings\Settings;

/**
 * What the watcher is told to do (§14.2): limits, the pause, and the runners it may start — the
 * section's settings screen over the defaults of `config/webx-tasks.php`, read on every call, so a
 * value saved a minute ago is the one the next poll gets.
 */
final class RunnerSettings
{
    public const PAUSED = 'tasks.runner.paused';

    /** The numbers, by the key they are stored under, with the least each may be. */
    public const LIMITS = [
        'max_workers' => ['tasks.runner.max_workers', 1],
        'per_project' => ['tasks.runner.per_project', 1],
        'daily_sessions' => ['tasks.runner.daily_sessions', 0],
        'max_attempts' => ['tasks.runner.max_attempts', 1],
    ];

    /** The runners a watcher knows how to start, by name, with the fields each has. */
    public const RUNNERS = [
        'claude' => ['enabled', 'args'],
        'agy' => ['enabled', 'model', 'args'],
    ];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    public function paused(): bool
    {
        return (bool) $this->value(self::PAUSED, 'paused', false);
    }

    /**
     * Everything the watcher reads at once.
     *
     * @return array{max_workers: int, per_project: int, daily_sessions: int, max_attempts: int, paused: bool, runners: array<string, array<string, mixed>>}
     */
    public function all(): array
    {
        $limits = [];

        foreach (self::LIMITS as $name => [$key, $least]) {
            $limits[$name] = max($least, (int) $this->value($key, $name, $least));
        }

        $runners = [];

        foreach (self::RUNNERS as $runner => $fields) {
            foreach ($fields as $field) {
                $value = $this->value("tasks.runner.{$runner}.{$field}", "runners.{$runner}.{$field}", $field === 'enabled' ? false : '');

                $runners[$runner][$field] = match ($field) {
                    'enabled' => (bool) $value,
                    'args' => self::words((string) $value),
                    default => trim((string) $value),
                };
            }
        }

        /** @var array{max_workers: int, per_project: int, daily_sessions: int, max_attempts: int} $limits */
        return [...$limits, 'paused' => $this->paused(), 'runners' => $runners];
    }

    /**
     * The values the settings screen shows, every one with its default filled in.
     *
     * @return array<string, mixed>
     */
    public function screenValues(): array
    {
        $all = $this->all();
        $values = [self::PAUSED => $all['paused']];

        foreach (self::LIMITS as $name => [$key]) {
            $values[$key] = $all[$name];
        }

        foreach (self::RUNNERS as $runner => $fields) {
            foreach ($fields as $field) {
                $value = $all['runners'][$runner][$field];
                $values["tasks.runner.{$runner}.{$field}"] = is_array($value) ? implode(' ', $value) : $value;
            }
        }

        return $values;
    }

    /** How long a watcher may say nothing before it is reported as silent, in minutes. */
    public function silentAfter(): int
    {
        return max(1, (int) $this->config->get('webx-tasks.runner.silent_minutes', 5));
    }

    /**
     * Arguments typed on one line, split as a shell would split simple ones: by spaces, with
     * quotes holding a phrase together.
     *
     * @return list<string>
     */
    public static function words(string $line): array
    {
        preg_match_all('/"([^"]*)"|\'([^\']*)\'|(\S+)/', $line, $matches, PREG_SET_ORDER);

        return array_values(array_map(
            // An unmatched group at the end is missing, one in the middle is empty.
            static fn (array $match): string => match (true) {
                ($match[3] ?? '') !== '' => $match[3],
                ($match[2] ?? '') !== '' => $match[2],
                default => $match[1],
            },
            $matches,
        ));
    }

    private function value(string $key, string $config, mixed $fallback): mixed
    {
        $default = $this->config->get("webx-tasks.runner.{$config}", $fallback);

        return $this->settings()->get($key, $default) ?? $default;
    }

    private function settings(): Settings
    {
        return $this->container->make(Settings::class);
    }
}
