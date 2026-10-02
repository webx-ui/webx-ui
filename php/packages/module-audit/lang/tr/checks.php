<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Canlı alan adında hata ayıklama modu açık',
            'found' => 'Geliştirme ortamı olmayan bir alan adında APP_DEBUG=true.',
            'why' => 'Her hata sayfası, kodu, sorguları ve parolalar dahil ortamı, bir sayfaya rastlayan herkese gösterir.',
            'fix' => '.env içinde APP_DEBUG=false yapın ve php artisan config:cache çalıştırın.',
        ],
        'env' => [
            'title' => 'Ortam production değil',
            'found' => 'Canlı bir alan adında APP_ENV production değil.',
            'why' => 'production dışında paketler farklı davranır: önbellekler, hata sayfaları, posta ve hata ayıklama araçları.',
            'fix' => '.env içinde APP_ENV=production yapın ve php artisan config:cache çalıştırın.',
        ],
        'app_url' => [
            'title' => 'APP_URL siteyle eşleşmiyor',
            'found' => 'APP_URL, sitenin yanıt verdiği şema ve ana makineden farklı.',
            'why' => 'Sitenin yazdırdığı tüm mutlak adresler — site haritası, canonical bağlantılar, e-postalar, dosya bağlantıları — başka bir yeri gösterir.',
            'fix' => 'APP_URL değerini ziyaretçilerin kullandığı adrese ayarlayın (sitede varsa https ile) ve php artisan config:cache çalıştırın.',
        ],
        'queue' => [
            'title' => 'Kuyruk istek içinde çalışıyor',
            'found' => 'Kuyruk sürücüsü sync.',
            'why' => 'E-postalar ve gönderimler ziyaretçi beklerken işlenir, yavaş bir posta sunucusu formları yavaşlatır ve denetim gibi uzun işler panelden çalıştırılamaz.',
            'fix' => 'database veya redis kuyruğunu kullanın ve bir worker çalışır durumda tutun (bir supervisor altında php artisan queue:work).',
        ],
        'mail' => [
            'title' => 'Posta hiçbir yere gitmiyor',
            'found' => 'Posta sürücüsü e-postaları günlüğe veya belleğe yazıyor.',
            'why' => 'Her form “gönderildi” der ve kimse hiçbir zaman e-posta almaz.',
            'fix' => '.env içinde gerçek bir posta sürücüsü (SMTP veya bir API) yapılandırın: MAIL_MAILER ve ayarları.',
        ],
        'schedule' => [
            'title' => 'Zamanlayıcı çalışmıyor',
            'found' => 'Zamanlayıcı bir saatten uzun süredir çalışmadı.',
            'why' => 'Yedekler, günlük temizliği ve zamanlanmış diğer her şey sessizce durur.',
            'fix' => 'Site kullanıcısının crontab dosyasına “* * * * * php artisan schedule:run” satırını ekleyin.',
        ],
        'storage_link' => [
            'title' => 'public/storage bağlantısı yok',
            'found' => 'public/storage mevcut değil.',
            'why' => 'Sitede yüklenen her görsel ve dosya 404 yanıtı verir.',
            'fix' => 'Sunucuda php artisan storage:link çalıştırın.',
        ],
        'site_gate' => [
            'title' => 'Site parola ile kapatılmış',
            'found' => 'Site kapısı açık.',
            'why' => 'Arama motorları parolanın arkasında hiçbir şey görmez — site test aşamasındayken doğru, yayına girdikten sonra yanlış.',
            'fix' => 'Site açıldığında WEBX_SITE_GATE=false yapın.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'İki ayna yanıt veriyor',
            'found' => 'Hem www hem de www’siz ad 200 yanıtı veriyor.',
            'why' => 'Her sayfa iki kez bulunur ve arama motorları ağırlığını kopyalar arasında böler.',
            'fix' => 'Web sunucusunda ikinci adı tek bir 301 ile ana ada yönlendirin.',
        ],
        'https' => [
            'title' => 'http, https’e tek adımda gitmiyor',
            'found' => 'http:// kendisi yanıt veriyor, başka yere gidiyor veya https’e bir zincirle ulaşıyor.',
            'why' => 'Ziyaretçiler güvenli olmayan bir kopyaya düşer ve her ek adım zamana ve bağlantı ağırlığına mal olur.',
            'fix' => 'Web sunucusunda ana ana makinenin http:// adresinden https:// adresine tek bir 301.',
        ],
        'tls' => [
            'title' => 'Sertifika sorunu',
            'found' => 'Sertifikanın süresi yakında doluyor, başka bir ana makineyi adlandırıyor veya güvenilir değil.',
            'why' => 'Tarayıcılar tam sayfa uyarı gösterir ve ziyaretçilerin çoğu ayrılır.',
            'fix' => 'Sertifikayı yenileyin (otomatik yenilemenin çalıştığını kontrol edin) ve bu ana makine için tam zinciri sunun.',
        ],
        'hsts' => [
            'title' => 'HSTS yok',
            'found' => 'Strict-Transport-Security başlığı yok.',
            'why' => 'İlk ziyaret yine de düz http üzerinden gidebilir ve yakalanabilir.',
            'fix' => 'https kararlı hale geldiğinde web sunucusuna Strict-Transport-Security: max-age=31536000 ekleyin.',
        ],
        'index_files' => [
            'title' => 'Index dosyaları yanıt veriyor',
            'found' => '/index.php veya başka bir index dosyası 200 yanıtı veriyor.',
            'why' => 'Sayfa ikinci bir adreste de erişilebilir — arama motorları için bir kopya.',
            'fix' => 'Index dosyalarını, onlarsız adrese 301 ile yönlendirin.',
        ],
        'slashes' => [
            'title' => 'Çift eğik çizgiler birleştirilmiyor',
            'found' => '// içeren bir adres 200 yanıtı veriyor.',
            'why' => 'Yanlış yazılmış her bağlantı sayfanın bir kopyasını daha oluşturur.',
            'fix' => 'Tekrarlanan eğik çizgili adresleri birleştirilmiş adrese 301 ile yönlendirin.',
        ],
        'trailing_slash' => [
            'title' => 'Sondaki eğik çizgiyle ve çizgisiz',
            'found' => 'Aynı sayfa sondaki eğik çizgiyle de çizgisiz de yanıt veriyor.',
            'why' => 'Bir sayfa için iki adres, sayfanın ağırlığını böler.',
            'fix' => 'Bir biçim seçin ve diğerini 301 ile yönlendirin.',
        ],
        'case' => [
            'title' => 'Büyük/küçük harf normalleştirilmiyor',
            'found' => 'Büyük harf içeren bir adres 200 yanıtı veriyor.',
            'why' => 'Başka harf biçimiyle yazılmış bir bağlantı kopya oluşturur.',
            'fix' => 'Büyük harfli adresleri küçük harfli adrese 301 ile yönlendirin.',
        ],
        'soft_404' => [
            'title' => 'Olmayan sayfalar 404 yanıtı vermiyor',
            'found' => 'Var olamayacak bir adres 200 yanıtı veriyor veya yönlendiriyor.',
            'why' => 'Arama motorları yazım hatalarını ve silinmiş sayfaları gerçek sayfa olarak dizine ekler.',
            'fix' => 'Bilinmeyen adresler için 404 yanıtı verin; onları ana sayfaya yönlendirmeyin.',
        ],
        '404_page' => [
            'title' => '404 sayfası hiçbir yere götürmüyor',
            'found' => '404 sayfasında ana sayfaya bağlantı yok.',
            'why' => 'Bozuk bir bağlantıyı izleyen ziyaretçinin gidecek yeri kalmaz.',
            'fix' => '404 şablonuna ana sayfaya, aramaya veya ana bölümlere bir bağlantı ekleyin.',
        ],
        'compression' => [
            'title' => 'HTML sıkıştırılmıyor',
            'found' => 'Sayfalar gzip veya brotli olmadan gönderiliyor.',
            'why' => 'Sayfalar birkaç kat daha ağır olur ve özellikle mobilde daha yavaş açılır.',
            'fix' => 'Web sunucusunda text/html için gzip veya brotli’yi açın.',
        ],
        'security_headers' => [
            'title' => 'Güvenlik başlıkları eksik',
            'found' => 'X-Content-Type-Options, Referrer-Policy ve çerçeveye gömme korumasından bazıları eksik.',
            'why' => 'Ucuz saldırıları kapatırlar: MIME sniffing, adres sızdırma, clickjacking.',
            'fix' => 'Başlıkları web sunucusuna ekleyin: nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'Sunucu sürümlerini açıklıyor',
            'found' => 'X-Powered-By veya sürüm numaralı Server.',
            'why' => 'O sürümdeki bilinen bir açığı arayan herkes için hazır bir harita.',
            'fix' => 'expose_php ve server_tokens ayarlarını (veya karşılıklarını) kapatın.',
        ],
        'static_cache' => [
            'title' => 'Statik dosyalar önbelleğe alınmıyor',
            'found' => 'Cache-Control olmayan veya bir haftadan kısa önbelleğe alınan CSS, JS ya da görseller.',
            'why' => 'Her sayfa bunları yeniden indirir.',
            'fix' => 'Web sunucusunda sürümlü statik dosyalara uzun bir Cache-Control verin (bir yıl, immutable).',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'İçerikte geliştirme ortamına bağlantılar',
            'found' => 'Bir kayıtta geliştirme ortamı adresi — yayınlanmış, taslakta veya şablonun yazdırmadığı bir alanda.',
            'why' => 'Geliştirme ortamında doldurulan içerik, o ortama geri işaret eden bağlantı ve görsellerle canlıya çıkar; ziyaretçiler hata alır ve geliştirme ortamı dizine eklenir.',
            'fix' => 'Kaydı açın ve ortam adresini sitenin kendi adresiyle veya göreli bir bağlantıyla değiştirin. Hepsinin yakalanması için ortamları denetim ayarlarında listeleyin.',
        ],
    ],
];
