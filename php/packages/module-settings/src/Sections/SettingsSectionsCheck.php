<?php

declare(strict_types=1);

namespace WebxUi\Settings\Sections;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * `webx:doctor`: which optional tabs of the settings this site offers, and why.
 *
 * A warning only where somebody has to act — a tab declared by a theme, a package or the
 * project that cannot be drawn because its field type is missing, or contacts kept only by the
 * transition rule. Branding hidden for want of the media module on a site that never asked
 * for it is how such a site is meant to look, not a problem.
 */
final class SettingsSectionsCheck implements Check
{
    public function __construct(private readonly SettingsSections $sections) {}

    public function run(): array
    {
        $claimants = $this->sections->claimants();
        $diagnoses = [];

        foreach (array_keys(SettingsSections::NEEDS) as $tab) {
            $subject = "Settings: {$tab}";
            $who = implode(', ', $claimants[$tab] ?? []);
            $missing = $this->sections->missing($tab);

            if (! $this->sections->used($tab)) {
                $diagnoses[] = Diagnosis::ok($subject, 'hidden — nothing on the site prints it. A theme declares it under "settings" of its manifest, a package under extra.webx.settings, the project in webx-settings.uses.');

                continue;
            }

            if ($missing !== []) {
                $install = implode(', ', array_unique(array_map(
                    static fn (string $type): string => SettingsSections::PROVIDERS[$type] ?? "a package that registers [{$type}]",
                    $missing,
                )));
                $types = implode(', ', $missing);

                $diagnoses[] = $this->sections->declared($tab)
                    ? Diagnosis::warn($subject, "hidden — used by {$who}, but its fields need the type [{$types}]; install {$install}.")
                    : Diagnosis::ok($subject, "hidden — its fields need the type [{$types}], and {$install} is not installed.");

                continue;
            }

            $diagnoses[] = match (true) {
                $who !== '' => Diagnosis::ok($subject, "shown — used by {$who}."),
                $tab === SettingsSections::CONTACTS && $this->sections->transition() === 'theme' => Diagnosis::ok($subject, 'shown — the theme has not declared "settings" yet, so it is taken to print contacts. Declare them in its manifest: the rule goes in the next minor.'),
                $tab === SettingsSections::CONTACTS => Diagnosis::warn($subject, "shown — contacts are stored but nothing declares them; add 'uses' => ['contacts'] to config/webx-settings.php (or \"settings\" to the theme's manifest). The tab is kept for now and hidden from the next minor on."),
                default => Diagnosis::ok($subject, 'shown — used by the panel itself.'),
            };
        }

        return $diagnoses;
    }
}
