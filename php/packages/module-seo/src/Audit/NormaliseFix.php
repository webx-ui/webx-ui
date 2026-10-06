<?php

declare(strict_types=1);

namespace WebxUi\Seo\Audit;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Seo\Normalisation;

/**
 * `seo.normalise-*` (audit spec §7): a finding of `host.*` closed by turning on the part of
 * {@see Normalisation} that answers it. One class, six fixes — each is one setting and the value
 * it takes.
 *
 * Not offered when the setting already has that value: the finding is still there, so the
 * request never reaches Laravel — the web server answers it — and the preview's note is the only
 * thing that would help, which the finding's own text already says.
 */
final readonly class NormaliseFix implements AuditFix
{
    /** id => [check, setting] */
    public const FIXES = [
        'seo.normalise-host' => ['host.mirror', Normalisation::HOST],
        'seo.normalise-https' => ['host.https', Normalisation::HTTPS],
        'seo.normalise-slashes' => ['host.slashes', Normalisation::SLASHES],
        'seo.normalise-index' => ['host.index_files', Normalisation::INDEX],
        'seo.normalise-trailing' => ['host.trailing_slash', Normalisation::TRAILING],
        'seo.normalise-case' => ['host.case', Normalisation::LOWERCASE],
    ];

    public function __construct(
        private string $id,
        private Normalisation $settings,
    ) {}

    public function textNamespace(): string
    {
        return 'webx-seo';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fixes(): array
    {
        return [self::FIXES[$this->id][0]];
    }

    public function available(Finding $finding): bool
    {
        $value = $this->value($finding);

        return $value !== null && $this->settings->value($this->setting()) !== $value;
    }

    public function preview(Finding $finding): FixPreview
    {
        if (! $this->available($finding)) {
            return new FixPreview;
        }

        $before = $this->settings->value($this->setting());

        return new FixPreview([[
            'label' => (string) __('webx-seo::screen.'.substr($this->setting(), strlen('seo.'))),
            'before' => $this->say($before),
            'after' => $this->say($this->value($finding)),
            'edit_url' => '/settings',
        ]], (string) __('webx-seo::audit.normalise-note'));
    }

    public function apply(Finding $finding): void
    {
        $value = $this->value($finding);

        if ($value !== null) {
            $this->settings->set($this->setting(), $value);
        }
    }

    private function setting(): string
    {
        return self::FIXES[$this->id][1];
    }

    /** What the setting becomes for this finding; null when the finding does not say. */
    private function value(Finding $finding): string|bool|null
    {
        return match ($this->setting()) {
            // The mirror the audit opened is the main one: the finding is about the other.
            Normalisation::HOST => $this->mainMirror($finding),
            // The policy the site's own links already follow: the first inner link of the home
            // page is what the check compared, and its form is the one to keep.
            Normalisation::TRAILING => $this->trailing($finding),
            Normalisation::HTTPS => str_starts_with((string) config('app.url'), 'https://') ? true : null,
            default => true,
        };
    }

    private function mainMirror(Finding $finding): ?string
    {
        $other = (string) parse_url((string) $finding->url, PHP_URL_HOST);

        if ($other === '') {
            return null;
        }

        return str_starts_with($other, 'www.') ? Normalisation::BARE : Normalisation::WWW;
    }

    private function trailing(Finding $finding): ?string
    {
        $inner = $finding->details['summary']['params']['url'] ?? null;

        if (! is_string($inner) || $inner === '') {
            return null;
        }

        // One policy: the registry's addresses carry no slash at the end, so neither will this.
        return Normalisation::STRIP;
    }

    private function say(mixed $value): string
    {
        return match (true) {
            $value === true => (string) __('webx-seo::audit.on'),
            $value === null, $value === false, $value === '' => (string) __('webx-seo::screen.normalise-off'),
            $value === Normalisation::WWW, $value === Normalisation::BARE => (string) __('webx-seo::screen.normalise-host-'.$value),
            $value === Normalisation::STRIP => (string) __('webx-seo::screen.normalise-trailing-'.$value),
            default => is_scalar($value) ? (string) $value : '',
        };
    }
}
