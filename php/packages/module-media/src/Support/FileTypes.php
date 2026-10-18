<?php

declare(strict_types=1);

namespace WebxUi\Media\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * What a file is, decided by the server from its bytes — never by the name or the type the
 * client sent with it.
 *
 * Both matter after the upload. The extension is what the web server serves the key under: a
 * PNG named `x.html` and kept as `<uuid>.html` is a page on the site's origin. The type is what
 * the panel answers with. So a file is kept under an extension from the white list that agrees
 * with its content, and with the type its content has; a file for which there is no such pair is
 * refused.
 */
final class FileTypes
{
    public const SVG = 'image/svg+xml';

    /**
     * Never accepted, whatever the configuration lists: a browser runs these as documents of the
     * site, and nothing a library is for needs one. `svgz` because it cannot be sanitized
     * without unpacking it, and an SVG that is not sanitized is a page.
     *
     * @var list<string>
     */
    private const NEVER = [
        'html', 'htm', 'xhtml', 'xht', 'shtml', 'mht', 'mhtml', 'xml', 'xsl', 'xslt',
        'js', 'mjs', 'svgz', 'swf', 'hta',
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pht',
    ];

    /**
     * Types the detector reports for a family of formats rather than for one: an Office file is
     * a ZIP or an OLE container to `finfo`, a CSV is plain text. A file detected as one of these
     * is believed about the member of the family its extension names.
     *
     * @var array<string, list<string>>
     */
    private const CONTAINERS = [
        'application/zip' => ['docx', 'xlsx', 'pptx', 'odt', 'ods'],
        'application/x-zip-compressed' => ['docx', 'xlsx', 'pptx', 'odt', 'ods'],
        'application/x-ole-storage' => ['doc', 'xls', 'ppt'],
        'application/cdfv2' => ['doc', 'xls', 'ppt'],
        'application/vnd.ms-office' => ['doc', 'xls', 'ppt'],
        'text/plain' => ['csv', 'txt'],
    ];

    public function __construct(private readonly Config $config) {}

    /**
     * The extension and type to keep a file under, or `null` when its content and its name
     * do not make one.
     *
     * @return array{0: string, 1: string}|null
     */
    public function resolve(UploadedFile $upload): ?array
    {
        $detected = $this->detected($upload);

        if ($detected === null) {
            return null;
        }

        $claimed = $this->clean($upload->getClientOriginalExtension());

        if ($claimed !== '' && $this->allowed($claimed) && $this->agrees($claimed, $detected)) {
            return [$claimed, $this->typeFor($claimed, $detected)];
        }

        // A name that says nothing, or the wrong thing: the content names the extension.
        $guessed = $this->clean((string) $upload->guessExtension());

        if ($guessed !== '' && $this->allowed($guessed) && $this->agrees($guessed, $detected)) {
            return [$guessed, $this->typeFor($guessed, $detected)];
        }

        return null;
    }

    /** Whether the extension may be kept at all: on the configured list, and not a document. */
    public function allowed(string $extension): bool
    {
        if (in_array($extension, self::NEVER, true)) {
            return false;
        }

        $list = array_map(
            static fn (mixed $one): string => strtolower((string) $one),
            (array) $this->config->get('webx-media.upload.extensions', []),
        );

        return in_array($extension, $list, true)
            || ($extension === 'jpeg' && in_array('jpg', $list, true))
            || ($extension === 'jpg' && in_array('jpeg', $list, true));
    }

    private function agrees(string $extension, string $detected): bool
    {
        // Text, as far as the detector is concerned — the sanitizer decides whether it is an SVG.
        if ($extension === 'svg') {
            return $detected === self::SVG || str_starts_with($detected, 'text/') || str_ends_with($detected, '/xml');
        }

        $types = MimeTypes::getDefault();

        return in_array($detected, $types->getMimeTypes($extension), true)
            || in_array($extension, $types->getExtensions($detected), true)
            || in_array($extension, self::CONTAINERS[$detected] ?? [], true);
    }

    private function typeFor(string $extension, string $detected): string
    {
        if ($extension === 'svg') {
            return self::SVG;
        }

        if (isset(self::CONTAINERS[$detected])) {
            return MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? $detected;
        }

        return $detected;
    }

    /**
     * `getMimeType()` reads the bytes (finfo) — unlike `getClientMimeType()`, which is whatever
     * the request said. Laravel's `mimes` rule asks the same question, so the two cannot disagree.
     */
    private function detected(UploadedFile $upload): ?string
    {
        if (! $upload->isFile()) {
            return null;
        }

        $type = $upload->getMimeType();

        return $type === null ? null : strtolower(trim(explode(';', $type)[0]));
    }

    private function clean(string $extension): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($extension));
    }
}
