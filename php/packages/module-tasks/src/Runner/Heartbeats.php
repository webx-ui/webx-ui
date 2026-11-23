<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Runner;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use WebxUi\Tasks\Models\RunnerBeat;

/**
 * The watcher's heartbeat (§14.3): once a poll it says what it runs, and the panel keeps the last
 * word of each watcher. A watcher that stops saying anything is noticed by the clock — the screen
 * «Agents» says so, and `webx:tasks:runner-watch` tells a person once per silence.
 */
final class Heartbeats
{
    public function __construct(private readonly RunnerSettings $settings) {}

    /**
     * Keep what the watcher said, now. The next silence is a new one: the alarm is re-armed.
     *
     * @param  array<string, mixed>  $beat
     */
    public function record(string $runner, array $beat): RunnerBeat
    {
        $runner = trim($runner);

        if ($runner === '' || mb_strlen($runner) > 100) {
            throw ValidationException::withMessages(['runner' => 'A watcher is named by 1 to 100 characters.']);
        }

        $beat = RunnerBeat::query()->updateOrCreate(['runner' => $runner], [
            'payload' => $this->payload($beat),
            'beat_at' => Carbon::now(),
            'silent_notified_at' => null,
        ]);

        return $beat;
    }

    /**
     * Every watcher there has been, the most recent first, with whether it has gone silent.
     *
     * @return list<array<string, mixed>>
     */
    public function status(): array
    {
        return RunnerBeat::query()
            ->orderByDesc('beat_at')
            ->get()
            ->map(fn (RunnerBeat $beat): array => $this->present($beat))
            ->values()
            ->all();
    }

    /**
     * The watchers silent longer than the limit that nobody has been told about yet.
     *
     * @return list<RunnerBeat>
     */
    public function newlySilent(): array
    {
        return RunnerBeat::query()
            ->whereNull('silent_notified_at')
            ->where('beat_at', '<=', $this->silentSince())
            ->orderBy('beat_at')
            ->get()
            ->all();
    }

    public function isSilent(RunnerBeat $beat): bool
    {
        return $beat->beat_at->lte($this->silentSince());
    }

    /**
     * @return array<string, mixed>
     */
    public function present(RunnerBeat $beat): array
    {
        return [
            'runner' => $beat->runner,
            'beat_at' => $beat->beat_at->toIso8601String(),
            'silent' => $this->isSilent($beat),
            'silent_notified_at' => $beat->silent_notified_at?->toIso8601String(),
            ...$beat->payload,
        ];
    }

    private function silentSince(): Carbon
    {
        return Carbon::now()->subMinutes($this->settings->silentAfter());
    }

    /**
     * The fields of a beat, each brought to its type; anything else the watcher sends is dropped
     * rather than kept — the row is shown to people, and only what the screen knows belongs in it.
     *
     * @param  array<string, mixed>  $beat
     * @return array<string, mixed>
     */
    private function payload(array $beat): array
    {
        $windows = $beat['windows'] ?? [];

        if (! is_array($windows) || ! array_is_list($windows)) {
            throw ValidationException::withMessages(['windows' => '`windows` is a list of the sessions running now.']);
        }

        $int = static fn (string $name): ?int => isset($beat[$name]) && is_numeric($beat[$name]) ? max(0, (int) $beat[$name]) : null;
        $text = static fn (mixed $value, int $length = 255): ?string => is_scalar($value) && trim((string) $value) !== ''
            ? mb_substr(trim((string) $value), 0, $length)
            : null;

        return [
            'windows' => array_map(static function (mixed $window, int $index) use ($text): array {
                if (! is_array($window)) {
                    throw ValidationException::withMessages(["windows.{$index}" => 'A window is an object: agent, stage, runner, since.']);
                }

                return [
                    'agent' => $text($window['agent'] ?? null, 100),
                    'stage' => isset($window['stage']) && is_numeric($window['stage']) ? (int) $window['stage'] : null,
                    'runner' => $text($window['runner'] ?? null, 50),
                    'since' => self::moment($window['since'] ?? null),
                ];
            }, $windows, array_keys($windows)),
            'paused' => (bool) ($beat['paused'] ?? false),
            'waiting' => $text($beat['waiting'] ?? null, 500),
            'daily_sessions' => $int('daily_sessions'),
            'version' => $text($beat['version'] ?? null, 50),
            'hard_max_workers' => $int('hard_max_workers'),
            'max_workers' => $int('max_workers'),
        ];
    }

    private static function moment(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
