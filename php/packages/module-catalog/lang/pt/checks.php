<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Categorias sem descrição',
            'found' => 'Há categorias publicadas sem descrição.',
            'why' => 'Uma página de categoria que só tem uma lista de produtos diz pouco a um motor de busca e é facilmente tomada por uma página pobre.',
            'fix' => 'Escreva algumas frases sobre o que a categoria contém e como escolher nela.',
        ],
        'product_image' => [
            'title' => 'Produtos sem imagem',
            'found' => 'Há produtos publicados sem imagem.',
            'why' => 'Um produto sem imagem vende pior, parece inacabado na listagem e não tem nada para mostrar quando é partilhado.',
            'fix' => 'Adicione pelo menos uma imagem a cada produto, ou retire do site os que não estão prontos.',
        ],
        'product_price' => [
            'title' => 'Produtos sem preço',
            'found' => 'Há produtos publicados sem preço, ou com preço zero.',
            'why' => 'O visitante não sabe quanto custa, e o produto não pode aparecer nos resultados de compras.',
            'fix' => 'Defina o preço, ou retire do site os produtos que ainda não estão à venda.',
        ],
    ],
];
