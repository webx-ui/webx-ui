<?php

declare(strict_types=1);

return [
    'required' => '“:field” alanı boş olamaz.',
    'name-long' => 'Ad :max karakterden uzun.',
    'code-long' => 'Kod :bytes bayt; sınır :max.',
    'consent' => 'Onay kategorisi şunlardan biridir: :categories.',
    'mask' => 'Yol kökten başlar, “/blog/*” gibi; site adresi ve “?” olmadan: [:mask] bir yol değil.',
    'masks-many' => 'Bir listede en fazla :max yol.',
    'locales' => 'Site :unknown dilinde yayımlanmıyor. Olanlar: :known.',
    'boolean' => '“:field” alanı evet ya da hayırdır.',
    'list' => 'Yol veya dil listesi bekleniyor.',
    'place-undeclared' => '“:place” yeri config/webx-snippets.php içinde tanımlı değil.',
    'place-orphaned' => '“:place” yeri artık tanımlı değil, kod hiçbir yerde yazılmaz: başka bir yere taşıyın ya da kapatın.',
    'document-tags' => 'Kodda :tags var: yalnızca hizmetin sayfanın içine koymanızı istediğini yapıştırın.',
    'unknown' => ':ids kimlikli kod yok.',
    'reorder' => 'Sıra, bir yer ve onun kodlarının listesidir.',
];
