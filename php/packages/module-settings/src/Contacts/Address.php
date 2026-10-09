<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

/**
 * A place of the site: the address as written (on the current language), where it is on the
 * earth, and the link to a map — the one typed in, or OpenStreetMap at the coordinates, which
 * needs neither a key nor an account (WIDGETS §11).
 */
final class Address
{
    public function __construct(
        public readonly string $text,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $map,
        public readonly bool $primary,
    ) {}

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapUrl(): ?string
    {
        if ($this->map !== null) {
            return $this->map;
        }

        if (! $this->hasCoordinates()) {
            return null;
        }

        return sprintf('https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=17/%1$s/%2$s', $this->latitude, $this->longitude);
    }
}
