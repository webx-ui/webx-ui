<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Ödendi',
        'received' => 'Teslim alındı',
        'cancelled' => 'İptal edildi',
        'refunded' => 'İade edildi :amount',
    ],
    'blocks' => [
        'delivery' => 'Teslimat',
        'payment' => 'Ödeme',
    ],
    'order' => [
        'title' => 'Sipariş :number',
        'placed' => 'Verilme tarihi: :date',
        'scale' => 'Sipariş nerede',
        'awaiting_payment' => 'Sipariş ödeme bekliyor.',
        'items' => 'Ürünler',
        'subtotal' => 'Ürünler',
        'total' => 'Toplam',
        'tenders' => 'Bakiye ve hediye kartlarıyla ödendi',
        'payable' => 'Ödenecek',
        'contacts' => 'İletişim',
        'customer' => 'Alıcı',
        'recipient' => 'Teslim alan',
        'comment' => 'Yorum',
    ],
    'account' => [
        'menu' => 'Siparişlerim',
        'title' => 'Siparişlerim',
        'empty' => 'Henüz sipariş yok. Giriş yaptıktan sonra verdiğiniz siparişler burada görünür.',
        'back' => '← Tüm siparişler',
        'lines' => 'Ürün sayısı: :count',
        'pages' => 'Sayfalar',
        'newer' => '← Daha yeni',
        'older' => 'Daha eski →',
        'deletion' => 'Devam eden siparişler (:numbers) tamamlanacak, ancak bunları hesabınızda artık görmeyeceksiniz.',
    ],
];
