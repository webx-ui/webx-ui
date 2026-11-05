<?php

declare(strict_types=1);

return [
    'currency' => 'Um feed usa a moeda do site: :currency.',
    'currency-unset' => 'O site não tem moeda: defina WEBX_CATALOG_CURRENCY.',
    'filter-facet' => 'O filtro do feed refere [:facet], que o site não tem.',
    'source-property' => 'O feed obtém o MPN da propriedade [:property], que o site não tem.',
    'no-item' => 'O feed não tem modelo de produto.',
    'template' => 'O modelo não pode ser lido: :reason',
    'too-large' => 'O ficheiro excede o tamanho permitido de um feed.',
    'stale' => 'A geração deixou de avançar e foi encerrada.',
    'file-xml' => 'O ficheiro não é XML válido, linha :line: :reason',
    'file-json' => 'O cabeçalho e o rodapé não formam JSON válido: :reason',
    'file-json-item' => 'O produto :product não forma JSON válido: :reason',
    'file-csv' => 'A linha :line do ficheiro tem :found campos em vez de :expected.',
];
