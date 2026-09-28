<?php

declare(strict_types=1);

// The regions of the layout (the regions spec): the words a declaration may point at with
// `trans::webx-blocks::regions.header`, and what the server says about a region.
return [
    'header' => 'Cabeçalho',
    'footer' => 'Rodapé',
    'usage' => 'Zona «:title»',
    'preview-failed' => 'Um bloco desta zona falha. No site, a zona inteira mostrará no lugar a marcação do código.',
    'not-in-registry' => ':path não é uma página do registo de endereços do site, por isso a zona é mostrada numa página vazia do layout.',
    'too-many' => 'A zona comporta no máximo :max blocos.',
    'not-allowed' => 'O bloco «:type» não pode ser colocado nesta zona.',
    'refused' => 'A zona não aceita estes blocos.',
    'conflict' => 'A zona foi alterada desde que a abriu. Recarregue para ver as alterações.',
    'failed-block' => 'O bloco «:type» (:key) falha: :reason',
    'not-published' => 'Não publicado: um bloco do rascunho não se desenha.',
    'nothing-to-publish' => 'A zona nunca foi guardada: não há nada para publicar.',
    'no-version' => 'A zona não tem a versão :number.',
    'no-fallback' => 'O layout ainda não indicou uma vista para esta zona, ou a vista já não existe.',
    'adopt-taken' => 'Já existe um tipo de bloco «:slug».',
    'adopt-failed' => 'Não foi possível transformar a marcação num tipo de bloco.',
    'adopt-forbidden' => 'Mover a marcação para um tipo de bloco requer o direito de editar tipos de bloco.',
];
