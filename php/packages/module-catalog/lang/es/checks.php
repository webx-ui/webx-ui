<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Categorías sin descripción',
            'found' => 'Hay categorías publicadas sin descripción.',
            'why' => 'Una página de categoría con solo una lista de productos dice poco a un buscador y se toma fácilmente por una página pobre.',
            'fix' => 'Escribe unas frases sobre lo que contiene la categoría y cómo elegir en ella.',
        ],
        'product_image' => [
            'title' => 'Productos sin imagen',
            'found' => 'Hay productos publicados sin imagen.',
            'why' => 'Un producto sin imagen se vende peor, parece inacabado en el listado y no tiene nada que mostrar al compartirlo.',
            'fix' => 'Añade al menos una imagen a cada producto, o retira del sitio los que no estén listos.',
        ],
        'product_price' => [
            'title' => 'Productos sin precio',
            'found' => 'Hay productos publicados sin precio, o con precio cero.',
            'why' => 'El visitante no sabe cuánto cuesta, y el producto no puede aparecer en los resultados de compras.',
            'fix' => 'Indica el precio, o retira del sitio los productos que aún no están a la venta.',
        ],
    ],
];
