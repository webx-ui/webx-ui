<?php

declare(strict_types=1);

return [
    'catalog' => [
        'category_description' => [
            'title' => 'Categories without a description',
            'found' => 'Published categories have no description.',
            'why' => 'A category page with nothing but a list of products says little to a search engine and is easily taken for a thin page.',
            'fix' => 'Write a few sentences about what the category holds and how to choose in it.',
        ],
        'product_image' => [
            'title' => 'Products without a picture',
            'found' => 'Published products have no image.',
            'why' => 'A product without a picture sells worse, looks unfinished in the listing and has nothing to show when shared.',
            'fix' => 'Add at least one image to each product, or unpublish the ones that are not ready.',
        ],
        'product_price' => [
            'title' => 'Products without a price',
            'found' => 'Published products have no price, or a price of zero.',
            'why' => 'A visitor cannot tell what it costs, and the product cannot be shown in shopping results.',
            'fix' => 'Set the price, or unpublish the products that are not for sale yet.',
        ],
    ],
];
