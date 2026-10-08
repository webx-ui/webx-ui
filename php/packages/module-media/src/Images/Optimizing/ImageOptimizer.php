<?php

declare(strict_types=1);

namespace WebxUi\Media\Images\Optimizing;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
use Intervention\Image\ImageManager;

/**
 * What a picture goes through on its way into the library, and again from «Optimize».
 *
 * Read the right way up (the EXIF orientation is applied, then dropped with the rest of the
 * metadata), the configured steps in order, then encoded at the configured quality — into
 * `format` for an upload, in its own format for a file already in the library: its key ends in
 * its extension, and the content that names that key would have to be rewritten with it.
 *
 * Only what can be re-encoded without losing anything a person meant: JPEG, PNG and still WebP.
 * A GIF or an animated WebP would come out as its first frame, and an SVG is not pixels at all.
 *
 * HEIC and HEIF as well, where Imagick is built with libheif: read only, and always converted —
 * no browser but Safari draws one, and GD reads none. Without Imagick they stay as they came.
 */
final class ImageOptimizer
{
    private const FORMATS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /** Read, never written: what an iPhone takes photographs in. */
    private const HEIC = ['heic' => 'image/heic', 'heif' => 'image/heif'];

    private ?bool $readsHeic = null;

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-media.optimize.enabled', true);
    }

    /** Whether a file of this kind is one the pipeline takes at all. */
    public function handles(string $extension, string $mime): bool
    {
        $extension = strtolower($extension);
        $mime = strtolower($mime);

        if (isset(self::HEIC[$extension])) {
            return $this->enabled() && $this->readsHeic() && in_array($mime, [...array_values(self::HEIC), 'image/heic-sequence', 'application/octet-stream'], true);
        }

        return $this->enabled()
            && isset(self::FORMATS[$extension])
            && in_array($mime, [...array_values(self::FORMATS), 'image/jpg', 'image/pjpeg'], true);
    }

    /**
     * The format pictures are converted into — on the way in, and by «Convert to WebP» — or null
     * when `format` names none the pipeline writes.
     */
    public function format(): ?string
    {
        $format = strtolower((string) ($this->settings()['format'] ?? ''));

        return isset(self::FORMATS[$format]) ? $format : null;
    }

    /**
     * The extensions a picture already in the library can be converted from: JPEG and PNG, and
     * HEIC where it can be read. Not WebP — that is what they become — nor GIF or SVG.
     *
     * @return list<string>
     */
    public function convertible(): array
    {
        $from = array_values(array_diff(['jpg', 'jpeg', 'png', 'webp'], [$this->format()]));

        return [...$from, ...($this->readsHeic() ? array_keys(self::HEIC) : [])];
    }

    /** Imagick with a HEIC decoder in it, which is what libheif makes. */
    public function readsHeic(): bool
    {
        if ($this->readsHeic === null) {
            try {
                $this->readsHeic = class_exists(\Imagick::class) && \Imagick::queryFormats('HEI*') !== [];
            } catch (\Throwable) {
                $this->readsHeic = false;
            }
        }

        return $this->readsHeic;
    }

    /**
     * Which settings a file was optimized with, so «Optimize» passes over what they would only
     * re-encode — every pass at a lossy quality loses a little more — and takes it up again
     * once the settings change. The output format is not in it: it never touches a stored file.
     */
    public function signature(): string
    {
        $config = $this->settings();

        return substr(md5((string) json_encode([$config['max_side'] ?? null, $config['quality'] ?? null, $config['steps'] ?? []])), 0, 16);
    }

    /**
     * The picture after the pipeline, or null for bytes it does not take — not a picture it
     * can read, or one with frames.
     *
     * `$convert` is for an upload: the result is encoded into `format`. Whichever way, a result
     * that is not smaller and not resized comes back as the bytes it was given.
     */
    public function optimize(string $contents, string $extension, bool $convert): ?Optimized
    {
        $extension = strtolower($extension);
        $heic = isset(self::HEIC[$extension]);

        if (! isset(self::FORMATS[$extension]) && ! ($heic && $this->readsHeic())) {
            return null;
        }

        // Measured from the header before anything is decoded: the upload form refuses a picture
        // over the budget, but an agent's download or an import never met that form, and decoding
        // is where a server runs out of memory. Kept as it came, rather than refused here.
        $size = $heic ? $this->heicSize($contents) : @getimagesizefromstring($contents);
        $budget = (int) $this->config->get('webx-media.image.max_pixels', 50_000_000);

        if ($size === false || $budget < $size[0] * $size[1]) {
            return null;
        }

        try {
            $image = $this->manager($heic)->read($contents);
        } catch (ImageException) {
            return null;
        }

        if ($image->isAnimated()) {
            return null;
        }

        $width = $image->width();
        $height = $image->height();
        $config = $this->settings();

        foreach ((array) ($config['steps'] ?? []) as $step) {
            $image = $this->step((string) $step)->apply($image, $config);
        }

        // A HEIC is always converted: it cannot be written back, and nothing draws it anyway.
        $format = $convert || $heic ? (string) $this->format() : '';
        $target = isset(self::FORMATS[$format]) ? $format : ($heic ? 'jpg' : $extension);

        $encoded = (string) $image->encodeByExtension($target, quality: (int) ($config['quality'] ?? 82));
        $resized = $image->width() !== $width || $image->height() !== $height;

        if ($heic) {
            return new Optimized($encoded, $target, self::FORMATS[$target], $image->width(), $image->height(), smaller: true);
        }

        if (strlen($encoded) >= strlen($contents) && ! $resized) {
            return new Optimized($contents, $extension, self::FORMATS[$extension], $width, $height, smaller: false);
        }

        return new Optimized($encoded, $target, self::FORMATS[$target], $image->width(), $image->height(), smaller: strlen($encoded) < strlen($contents));
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        return (array) $this->config->get('webx-media.optimize', []);
    }

    private function step(string $class): OptimizeStep
    {
        $step = $this->container->make($class);

        if (! $step instanceof OptimizeStep) {
            throw new \InvalidArgumentException("[{$class}] in webx-media.optimize.steps is not an ".OptimizeStep::class.'.');
        }

        return $step;
    }

    /**
     * Width and height of a HEIC from its header, without decoding it.
     *
     * @return array{0: int, 1: int}|false
     */
    private function heicSize(string $contents): array|false
    {
        try {
            $image = new \Imagick;
            $image->pingImageBlob($contents);

            return [$image->getImageWidth(), $image->getImageHeight()];
        } catch (\Throwable) {
            return false;
        }
    }

    private function manager(bool $imagick = false): ImageManager
    {
        $driver = $imagick || $this->config->get('webx-media.image.driver') === 'imagick' ? new ImagickDriver : new GdDriver;

        return new ImageManager($driver, autoOrientation: true, strip: true);
    }
}
