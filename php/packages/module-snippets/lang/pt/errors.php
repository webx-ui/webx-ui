<?php

declare(strict_types=1);

return [
    'required' => 'O campo «:field» não pode ficar vazio.',
    'name-long' => 'O nome passa de :max caracteres.',
    'code-long' => 'O código tem :bytes bytes; o limite é :max.',
    'consent' => 'A categoria de consentimento é uma de: :categories.',
    'mask' => 'Um caminho começa na raiz, como «/blog/*», sem o endereço do site nem «?»: [:mask] não é um.',
    'masks-many' => 'No máximo :max caminhos em uma lista.',
    'locales' => 'O site não é publicado em :unknown. Ele tem: :known.',
    'boolean' => 'O campo «:field» é sim ou não.',
    'list' => 'Espera-se uma lista de caminhos ou idiomas.',
    'place-undeclared' => 'O local «:place» não está declarado em config/webx-snippets.php.',
    'place-orphaned' => 'O local «:place» não está mais declarado e o código não seria impresso em lugar nenhum: mova-o ou desligue-o.',
    'document-tags' => 'O código tem :tags: cole só o que o serviço pede para pôr dentro da página.',
    'unknown' => 'Não há código com o id :ids.',
    'reorder' => 'A ordem é um local e a lista dos seus códigos.',
];
