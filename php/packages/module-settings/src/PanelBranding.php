<?php

declare(strict_types=1);

namespace WebxUi\Settings;

use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Manifest\Branding;
use WebxUi\Admin\Manifest\BrandingImage;

/**
 * The panel's name and logo, read from the settings the client fills in themselves.
 *
 * The name is `general.project-name` — the field that has sat on the `General` tab since the
 * section was written without anybody reading it. It is localized, so a panel answers in the
 * language it is being read in, and it stays the corner's text when there is no logo, the
 * alt of the logo when there is, and the title of the browser tab either way.
 *
 * The pictures are `wx-media`, which means `module-media` resolves them into an address. When
 * that module is not installed the value comes back the way it was stored — a library path and
 * nothing else — and this class simply finds no address and reports no logo. That is the same
 * tolerance `module-seo` already has for its `og:image`: a picture field is worth having
 * without making the whole file library a dependency of the settings.
 */
final class PanelBranding implements BrandingSource
{
    public function __construct(private readonly Settings $settings) {}

    public function branding(): Branding
    {
        return new Branding(
            title: $this->title(),
            logo: $this->image('branding.logo'),
            mark: $this->image('branding.mark'),
        );
    }

    private function title(): ?string
    {
        $name = $this->settings->get('general.project-name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function image(string $key): ?BrandingImage
    {
        $value = $this->settings->get($key);

        if (! is_array($value)) {
            return null;
        }

        $url = $value['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        return new BrandingImage(
            url: $url,
            width: is_int($value['width'] ?? null) ? $value['width'] : null,
            height: is_int($value['height'] ?? null) ? $value['height'] : null,
        );
    }
}
