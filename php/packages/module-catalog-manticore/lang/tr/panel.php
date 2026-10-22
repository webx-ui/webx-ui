<?php

declare(strict_types=1);

return [
    'title' => 'Arama dizini',
    'lead' => 'Katalog Manticore ile arar, filtreler ve sayar. Manticore yanıt vermezse panel veritabanından yanıt verir.',
    'refresh' => 'Yenile',
    'load-failed' => 'Dizinin durumu yüklenemedi.',

    'connection' => 'Sunucu',
    'address' => 'Adres',
    'prefix' => 'Tablo öneki',
    'version' => 'Sürüm',
    'available' => 'Yanıt veriyor',
    'unavailable' => 'Yanıt vermiyor',

    'tables' => 'Tablolar',
    'language' => 'Dil',
    'table' => 'Tablo',
    'in-index' => 'Dizinde',
    'in-database' => 'Veritabanında',
    'state' => 'Durum',
    'state-ready' => 'Güncel',
    'state-stale' => 'Şema eski',
    'state-missing' => 'Yok',
    'filling' => 'Yeniden oluşturma yanındaki tabloyu dolduruyor.',
    'filled' => 'Yeniden oluşturma yanındaki tabloyu dolduruyor: :done / :total',
    'no-tables' => 'Sunucu hangi tabloları olduğunu söylemedi.',

    'queue' => 'Kuyruk',
    'waiting' => 'Yazılmayı bekliyor',
    'oldest' => 'En eskisi şu zamandan beri',
    'queue-empty' => 'Kaydedilen her ürün dizinde.',

    'outdated' => 'Dizin, kataloğun şu anda yazdığı şemada değil',
    'outdated-text' => 'Vitrin bu arada eski tablolardan yanıt verir, ama onlarda olmayanlar — yeni bir filtre, kodlar — aranmaz. Yeniden oluşturma, canlı tabloların yanında yeni tablolar doldurur ve onları yerine koyar; büyük bir katalogda bu birkaç dakikalık yük demektir, o yüzden zamanı seçin.',
    'rebuild' => 'Yeniden oluştur',
    'rebuild-again' => 'Tekrar yeniden oluştur',
    'rebuild-title' => 'Arama dizini yeniden oluşturulsun mu?',
    'rebuild-text' => 'Her ürün yeni tablolara yeniden yazılır, ardından bunlar canlı tabloların yerini alır. Vitrin bu arada eski tablolarla çalışmaya devam eder.',
    'rebuild-busy' => 'Yeniden oluşturma zaten sürüyor.',
    'rebuild-locked' => 'Başka bir yeniden oluşturma çalışıyor — konsoldan başlatıldı. O bittiğinde bunu başlatın.',
    'rebuild-console' => 'Konsoldan başlatılan bir yeniden oluşturma çalışıyor: bittiğinde düğme geri gelir.',
    'rebuild-queued' => 'Kuyruk işçisi bekleniyor',
    'rebuild-running' => 'Yeniden oluşturuluyor: :done / :total',
    'rebuild-done' => 'Yeniden oluşturulan ürünler: :done',
    'rebuild-failed' => 'Yeniden oluşturma başarısız oldu',
    'rebuild-stalled' => 'Çeyrek saattir yeniden oluşturmadan ses yok: kuyruk işçisi çalışmıyor ya da durdu. İşçi çalışınca yeniden başlatın.',
    'rebuild-started' => 'Yeniden oluşturma kuyruğa alındı.',
    'cancel' => 'Vazgeç',
];
