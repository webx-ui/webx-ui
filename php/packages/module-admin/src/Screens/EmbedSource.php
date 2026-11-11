<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * Where a `wx-embed` field may point: one service's embeddable frames — Google Maps' "Embed a map"
 * ({@see Embeds}). The field keeps the address of the frame and never the HTML it was pasted in,
 * so what a source says is the one question that matters: which address, if any, is in this text.
 */
interface EmbedSource
{
    /**
     * The address of the frame in what was pasted — the whole `<iframe …>` the service gives, or
     * the address alone — brought to one spelling; null when the text is not one of this source's.
     */
    public function address(string $pasted): ?string;

    /** Why a value was refused, in the language of whoever is saving it. */
    public function refusal(): string;
}
