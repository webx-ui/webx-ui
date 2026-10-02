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
        'dev_page' => [
            'title' => 'Sayfada geliştirme ortamına bağlantılar',
            'found' => 'Sayfanın bir bağlantısı veya kaynağı — bir görsel, bir betik, bir stil, og:image, canonical — bir geliştirme ortamına gidiyor.',
            'why' => 'Ziyaretçiler kendileri için olmayan bir siteye giden bağlantıları izler, ortam kapandığında görseller bozulur ve arama motorları ortamı site üzerinden bulur.',
            'fix' => 'Adresi sayfanın içeriğinde veya şablonunda bulun ve ortamı sitenin kendi ana makinesiyle veya göreli bir bağlantıyla değiştirin.',
        ],
        'similar' => [
            'title' => 'Bu siteye benzeyen bir ana makine',
            'found' => 'Sitenin başka bir uzantıdaki ilk kelimesiyle aynı olan bir ana makineye bağlantı; örneğin shop.com yanında shop.local.',
            'why' => 'Büyük olasılıkla denetimin bilmediği bir geliştirme ortamı veya sitenin eski bir kopyasıdır.',
            'fix' => 'Bir ortam veya eski bir alan adıysa, denetim ayarlarında “Bu sitenin diğer adresleri” listesine ekleyin ve bağlantıları düzeltin; başkasının sitesiyse yapılacak bir şey yok.',
        ],
        'wrong_mirror' => [
            'title' => 'Başka bir ayna üzerinden bağlantılar',
            'found' => 'Siteye diğer aynası (www veya www’siz ad) üzerinden ya da https sitesinde http üzerinden verilmiş bir bağlantı.',
            'why' => 'Her tıklama bir yönlendirmeden geçer: ziyaretçiler için daha yavaştır ve arama motorları sayfa olmayan bir adrese giden bağlantılar görür.',
            'fix' => 'Ana aynaya https üzerinden bağlayın veya göreli bağlantılar kullanın.',
        ],
        'absolute_own' => [
            'title' => 'Siteye kendisine mutlak bağlantılar',
            'found' => 'İçerikteki bir bağlantı veya görsel, yol yerine sitenin kendi ana makinesiyle yazılmış.',
            'why' => 'Bugün çalışır, başka bir alan adına veya protokole geçildiğinde bozulur; bir geliştirme ortamına kopyalandığında da canlı siteye geri götürür.',
            'fix' => 'Sitenin kendi sayfalarına bağlantıları yol olarak yazın: https://shop.com/about yerine /about.',
        ],
        'new_domain' => [
            'title' => 'Yeni bir dış alan adı',
            'found' => 'Site, önceki tam çalıştırmada bağlantı vermediği bir alan adına bağlantı veriyor.',
            'why' => 'Yeni bir alan adı genellikle birinin eklediği yeni bir bağlantıdır — bazen de bir yazım hatası ya da siteye giren birinin bıraktığı spam bağlantılardır.',
            'fix' => 'Listelenen sayfaları açın ve bağlantının orada olması gerektiğinden emin olun.',
        ],
        'external_many' => [
            'title' => 'Bir sayfada çok sayıda dış bağlantı',
            'found' => 'Sayfada eşikten fazla dış bağlantı var.',
            'why' => 'Çoğunlukla başka sitelere bağlantılardan oluşan bir sayfa arama motorlarına bağlantı çiftliği gibi görünür ve çoğu zaman spam işaretidir.',
            'fix' => 'Ziyaretçiye yardımı olmayan bağlantıları kaldırın veya sayfayı bölün.',
        ],
        'blank_opener' => [
            'title' => 'noopener olmadan yeni sekme',
            'found' => 'Başka bir siteye giden bir bağlantı, rel="noopener" olmadan yeni sekmede açılıyor.',
            'why' => 'Eski tarayıcılarda açılan sayfa, sitenin sekmesini kendi seçtiği bir sayfaya yönlendirebilir.',
            'fix' => 'target="_blank" olan bağlantılara rel="noopener" (veya noreferrer) ekleyin.',
        ],
    ],
    'indexing' => [
        'home_noindex' => [
            'title' => 'Ana sayfa arama motorlarına kapalı',
            'found' => 'Ana sayfada robots meta etiketinde veya X-Robots-Tag başlığında noindex var.',
            'why' => 'Sitenin en önemli sayfası aramadan düşer ve çoğu zaman tüm site de onu izler.',
            'fix' => 'Ana sayfadan noindex’i kaldırın: sayfanın SEO ayarlarını, ana şablonu ve web sunucusu başlıklarını kontrol edin.',
        ],
        'noindex' => [
            'title' => 'noindex ile kapatılmış sayfalar',
            'found' => 'Sayfada robots meta etiketinde veya X-Robots-Tag başlığında noindex var.',
            'why' => 'Arama motorları sayfayı düşürür. Arama sonuçları ve hizmet sayfaları için doğru, yanlışlıkla kapatılmış içerik için yanlıştır.',
            'fix' => 'Listeye göz atın; bulunması gereken sayfaları SEO ayarlarında açın ve noindex’i kaldırın.',
        ],
    ],
    'title' => [
        'missing' => [
            'title' => 'Title yok',
            'found' => 'Sayfada <title> yok veya boş.',
            'why' => 'Title, arama motorlarının sayfaya bağlantı olarak gösterdiği satırdır; olmazsa kendileri uydurur.',
            'fix' => 'Sayfaya SEO ayarlarında bir title verin veya ana şablonun bunu yazdırdığını kontrol edin.',
        ],
        'duplicate' => [
            'title' => 'Yinelenen title’lar',
            'found' => 'Birkaç dizinlenebilir sayfa aynı title’a sahip.',
            'why' => 'Arama motorları sayfaları ayırt edemez ve birini gösterir; bu her zaman doğru olanı olmaz.',
            'fix' => 'Her sayfaya, içinde ne olduğunu söyleyen kendi title’ını verin.',
        ],
        'length' => [
            'title' => 'Title çok kısa veya çok uzun',
            'found' => 'Title, karakter cinsinden eşiklerden daha kısa veya daha uzun.',
            'why' => 'Uzun bir title arama sonuçlarında kesilir (sınır yaklaşık 600 piksel, kabaca 60 karakterdir); kısa olan çok az şey söyler.',
            'fix' => 'Title’ı denetim eşiklerindeki aralığa sığacak şekilde yeniden yazın.',
        ],
        'multiple' => [
            'title' => 'Birden fazla title',
            'found' => 'Sayfada birden fazla <title> etiketi var.',
            'why' => 'Arama motorları bunlardan birini alır; bu her zaman sayfa için yazılmış olan olmaz.',
            'fix' => 'İkinci title’ı hangi şablonun veya bloğun yazdırdığını bulun ve kaldırın.',
        ],
    ],
    'description' => [
        'missing' => [
            'title' => 'Meta description yok',
            'found' => 'Sayfada meta description yok veya boş.',
            'why' => 'Arama motorları bağlantının altındaki özeti buldukları herhangi bir metinden oluşturur.',
            'fix' => 'Sayfanın SEO ayarlarında bir description yazın: sayfanın ne sunduğunu bir iki cümleyle anlatın.',
        ],
        'duplicate' => [
            'title' => 'Yinelenen description’lar',
            'found' => 'Birkaç dizinlenebilir sayfa aynı meta description’a sahip.',
            'why' => 'Farklı bağlantıların altındaki aynı özet arayan kişiye hiçbir şey söylemez ve arama motorları onu kendi özetleriyle değiştirir.',
            'fix' => 'Her sayfa için kendi description’ını yazın.',
        ],
        'length' => [
            'title' => 'Description çok kısa veya çok uzun',
            'found' => 'Description, karakter cinsinden eşiklerden daha kısa veya daha uzun.',
            'why' => 'Uzun bir description arama sonuçlarında kesilir (yaklaşık 920 piksel, kabaca 160 karakter); kısa olan çoğu zaman değiştirilir.',
            'fix' => 'Description’ı denetim eşiklerindeki aralığa sığacak şekilde yeniden yazın.',
        ],
    ],
    'h1' => [
        'missing' => [
            'title' => 'H1 yok',
            'found' => 'Sayfada H1 başlığı yok.',
            'why' => 'H1, ziyaretçilere ve arama motorlarına sayfanın ne hakkında olduğunu söyler; ekran okuyucular içeriğin başlangıcını bulmak için onu kullanır.',
            'fix' => 'Sayfaya şablonda veya içerikte bir H1 verin — genellikle sayfanın adı.',
        ],
        'multiple' => [
            'title' => 'Birden fazla H1',
            'found' => 'Sayfada birden fazla H1 başlığı var.',
            'why' => 'Kendi başına bir hata değildir, ancak genellikle bir bloğun veya logonun daha alt bir seviye yerine H1 kullandığını gösterir.',
            'fix' => 'Sayfanın adı için tek bir H1 bırakın ve diğerlerini H2 veya daha alt yapın.',
        ],
        'equals_title' => [
            'title' => 'H1, title ile aynı',
            'found' => 'H1, title’ı kelimesi kelimesine tekrar ediyor.',
            'why' => 'Sayfayı anlatan iki yer aynı şeyi söylüyor; biri, insanların aradığı bir kelimeyi ekleyebilirdi.',
            'fix' => 'H1’i kısa ve okunaklı tutun, anahtar kelimeleri ve sitenin adını title taşısın.',
        ],
    ],
    'headings' => [
        'skipped' => [
            'title' => 'Atlanan başlık seviyesi',
            'found' => 'Aşağı inerken bir başlık seviyesi atlanıyor; örneğin H2’den sonra H4 geliyor.',
            'why' => 'Ekran okuyucular başlıklara göre gezinir ve bir boşluk eksik içerik gibi okunur.',
            'fix' => 'Seviyeleri sırayla kullanın; görünümü seviyeyle değil stillerle seçin.',
        ],
    ],
    'canonical' => [
        'missing' => [
            'title' => 'Canonical yok',
            'found' => 'Dizinlenebilir bir sayfada etikette veya başlıkta canonical bağlantısı yok.',
            'why' => 'Olmazsa, sayfanın izleme veya sıralama parametreli her kopyası sayfanın kendisiyle yarışabilir.',
            'fix' => 'Ana şablonun sayfanın kendi adresiyle <link rel="canonical"> yazdırmasını sağlayın.',
        ],
        'relative' => [
            'title' => 'Göreli canonical',
            'found' => 'Canonical tam adres olarak değil, yol olarak yazılmış.',
            'why' => 'Arama motorları onu, geldikleri adrese göre yorumlar; buna başka bir ayna veya protokol de dahildir.',
            'fix' => 'Canonical’ı şema ve ana makineyle birlikte tam adres olarak yazdırın.',
        ],
        'multiple' => [
            'title' => 'Çelişen canonical’lar',
            'found' => 'Sayfada birden fazla canonical var veya etiket ile Link başlığı uyuşmuyor.',
            'why' => 'Canonical’lar çeliştiğinde arama motorları hepsini yok sayar.',
            'fix' => 'Tek bir canonical bırakın: ikincisini ekleyen şablonu, bloğu veya sunucu kuralını bulun ve kaldırın.',
        ],
        'broken' => [
            'title' => 'Bozuk veya kapalı bir sayfaya canonical',
            'found' => 'Canonical bir yönlendirmeye, bir hataya veya noindex olan bir sayfaya gidiyor.',
            'why' => 'Sayfa, dizinlenemeyen bir orijinali işaret ediyor ve arama motorları ikisini de düşürebilir.',
            'fix' => 'Canonical’ı sayfanın kendi çalışan adresine veya canlı orijinale yöneltin.',
        ],
        'other' => [
            'title' => 'Başka bir sayfaya canonical',
            'found' => 'Canonical, sayfanın kendi adresinden farklı bir adresi gösteriyor.',
            'why' => 'Sayfa, başka biri lehine dizinlenmemeyi istiyor — filtreler ve kopyalar için doğru, bulunması gereken bir sayfa için yanlış.',
            'fix' => 'Listeye göz atın; bulunması gereken sayfalarda canonical’ı kendi adresleri yapın.',
        ],
    ],
    'html' => [
        'lang' => [
            'title' => 'Sayfa dili yok',
            'found' => '<html> etiketinde lang özniteliği yok.',
            'why' => 'Ekran okuyucular sesi ona göre seçer, tarayıcılar çeviriyi ona göre önerir ve arama motorları onu bir ipucu olarak kullanır.',
            'fix' => 'Ana şablonda sayfanın diliyle <html lang="…"> yazdırın.',
        ],
        'viewport' => [
            'title' => 'Meta viewport yok',
            'found' => 'Sayfada <meta name="viewport"> yok.',
            'why' => 'Telefonlar sayfayı masaüstü genişliğinde, küçültülmüş çizer; arama motorları böyle bir sayfayı mobil uyumlu saymaz.',
            'fix' => 'Ana şablona <meta name="viewport" content="width=device-width, initial-scale=1"> ekleyin.',
        ],
        'favicon' => [
            'title' => 'Simge yok',
            'found' => 'Sayfa hiçbir simgeye bağlantı vermiyor.',
            'why' => 'Tarayıcı sekmeleri, yer imleri ve telefonlardaki arama sonuçları sitenin işareti yerine boş bir kare gösterir.',
            'fix' => 'Ana şablona <link rel="icon"> ekleyin.',
        ],
    ],
    'og' => [
        'missing' => [
            'title' => 'Open Graph etiketleri eksik',
            'found' => 'Sayfada og:title, og:image veya og:url yok.',
            'why' => 'Bir mesajlaşma uygulamasında veya sosyal ağda paylaşılan bağlantı, görselsiz ve başlıksız çıplak bir adres olarak görünür.',
            'fix' => 'Sayfanın SEO ayarlarında sosyal önizlemeyi doldurun veya ana şablonun etiketleri yazdırmasını sağlayın.',
        ],
    ],
    'content' => [
        'thin' => [
            'title' => 'Az metin',
            'found' => 'Dizinlenebilir bir sayfada eşikten az kelime var.',
            'why' => 'Arama motorları okunacak şeyi az olan sayfaları daha aşağı sıralar ve bunlardan çoğunu düşük kaliteli sayabilir.',
            'fix' => 'Ziyaretçiye yardımcı olan metin ekleyin, zayıf sayfaları birleştirin veya noindex ile kapatın.',
        ],
        'text_ratio' => [
            'title' => 'İşaretlemeye göre az metin',
            'found' => 'Görünen metin, HTML içindeki payı eşikten küçük.',
            'why' => 'Sayfa söylediğine göre ağır: telefonda yavaş, ve arama motorları çok kodun içinde az içerik bulur.',
            'fix' => 'Satır içi betikleri ve stilleri dosyalara taşıyın, kullanılmayan işaretlemeyi kaldırın ve içerik ekleyin.',
        ],
        'duplicate' => [
            'title' => 'Yinelenen metin',
            'found' => 'Birkaç dizinlenebilir sayfada aynı görünen metin var.',
            'why' => 'Arama motorları gösterecekleri bir kopyayı seçer ve gerisini yok sayar.',
            'fix' => 'Sayfaları birbirinden farklı kılın, birleştirin veya kopyaların canonical’ını orijinale yöneltin.',
        ],
    ],
    'url' => [
        'length' => [
            'title' => 'Uzun adres',
            'found' => 'Adres eşikten uzun.',
            'why' => 'Uzun adresler arama sonuçlarında kesilir, paylaşması ve okuması zordur.',
            'fix' => 'Sayfanın slug’ını kısaltın; eski adresten yönlendirme otomatik eklenir.',
        ],
        'format' => [
            'title' => 'Adres biçimi',
            'found' => 'Yolda büyük harfler, alt çizgiler veya ASCII dışı karakterler var.',
            'why' => 'Büyük harfler /About ile /about’u iki sayfa yapar, alt çizgiler arama motorları için kelimeleri ayırmaz ve diğer karakterler kopyalanınca %D0%B0 olur.',
            'fix' => 'Slug’larda küçük Latin harfleri, rakamlar ve tire kullanın.',
        ],
        'params' => [
            'title' => 'Canonical olmadan parametreler',
            'found' => 'Dizinlenebilir bir adreste sorgu parametreleri var ve canonical yok.',
            'why' => 'Filtre ve sıralamanın her birleşimi arama motorlarında ayrı bir sayfa olur ve gerçek sayfanın ağırlığını bölüşürler.',
            'fix' => 'Parametresiz adrese canonical yazdırın veya bu tür adresleri noindex ile kapatın.',
        ],
    ],
    'perf' => [
        'ttfb' => [
            'title' => 'Yavaş yanıt',
            'found' => 'Sayfa yanıt vermek için eşikten uzun sürdü.',
            'why' => 'Ziyaretçiler bir şey görünmeden önce bekler ve arama motorları yavaş bir siteyi daha az tarar.',
            'fix' => 'Önbellekleri açın (yapılandırma, rotalar, görünümler, sayfalar), yavaş sorguları kontrol edin ve ağır işleri kuyruğa taşıyın.',
        ],
        'html_size' => [
            'title' => 'Ağır HTML',
            'found' => 'Sayfanın HTML’i eşikten büyük.',
            'why' => 'Telefonlar onu yavaş indirir ve işler; arama motorları sona varmadan okumayı bırakabilir.',
            'fix' => 'Uzun listeleri sayfalayın, satır içi veriyi ve SVG’leri dosyalara taşıyın, içeriğin gizli kopyalarını kaldırın.',
        ],
    ],
    'links' => [
        'broken' => [
            'title' => 'Bozuk iç bağlantılar',
            'found' => 'Sitenin kendi sayfasına giden bir bağlantı 4xx, 5xx veya hiç yanıt vermiyor.',
            'why' => 'Ziyaretçiler bir hataya düşer ve arama motorları ziyaretini bunun üzerinde boşa harcar.',
            'fix' => 'Bağlantıyı düzeltin veya kaldırın ya da eksik adresten doğru sayfaya bir yönlendirme ekleyin.',
        ],
        'empty' => [
            'title' => 'Metinsiz bağlantılar',
            'found' => 'Bir bağlantıda metin ve aria-label yok, görsel bağlantıda ise alt yok.',
            'why' => 'Ekran okuyucular adresi veya sadece “bağlantı” diye okur ve arama motorları gittiği sayfa hakkında hiçbir şey öğrenmez.',
            'fix' => 'Bağlantıya bir metin, aria-label veya görseline bir alt verin.',
        ],
        'nofollow_internal' => [
            'title' => 'İç bağlantılarda nofollow',
            'found' => 'Sitenin kendi sayfasına giden bir bağlantıda rel="nofollow" var.',
            'why' => 'Site, arama motorlarından kendi bağlantılarını izlememelerini istiyor ve sayfa daha az ağırlık alıyor.',
            'fix' => 'Sitenin kendi sayfalarına giden bağlantılardan nofollow’u kaldırın.',
        ],
    ],
    'mixed_content' => [
        'title' => 'Karışık içerik',
        'found' => 'Bir https sayfası bir kaynağı http üzerinden yüklüyor.',
        'why' => 'Tarayıcılar bu tür betikleri ve stilleri engeller, görseller için uyarır; kilit simgesi kaybolur.',
        'fix' => 'Kaynağı https üzerinden yükleyin veya şemasız bir yol kullanın.',
    ],
    'forms' => [
        'insecure' => [
            'title' => 'http üzerinden gönderilen form',
            'found' => 'Bir form bir http adresine gönderiliyor.',
            'why' => 'Ziyaretçilerin yazdıkları şifrelenmeden gider ve tarayıcılar göndermeden önce uyarır.',
            'fix' => 'Formu bir https adresine veya bir yola yöneltin.',
        ],
    ],
    'images' => [
        'alt' => [
            'title' => 'alt’sız görseller',
            'found' => 'Bir <img> öğesinde alt özniteliği yok.',
            'why' => 'Ekran okuyucular dosya adını okur ve arama motorları görselin ne gösterdiğini bilmez. Süs amaçlı bir görselde boş alt uygundur.',
            'fix' => 'Görseli alt içinde tanımlayın veya süs ise alt="" yazın.',
        ],
        'dimensions' => [
            'title' => 'Boyutsuz görseller',
            'found' => 'Bir <img> öğesinde width ve height yok.',
            'why' => 'Görseller yüklenirken sayfa zıplar ve ziyaretçiler yanlış yere tıklar.',
            'fix' => 'Şablonda görsellerin width ve height değerlerini yazdırın; CSS yine de onları duyarlı yapabilir.',
        ],
    ],
    'a11y' => [
        'button_name' => [
            'title' => 'Adsız düğmeler',
            'found' => 'Bir düğmede metin, aria-label veya title yok.',
            'why' => 'Bir ekran okuyucu yalnızca “düğme” diyebilir ve kullanıcısı ne yaptığını bilemez.',
            'fix' => 'Düğmeye bir metin verin; simgeyse aria-label verin.',
        ],
        'form_label' => [
            'title' => 'Etiketsiz alanlar',
            'found' => 'Bir form alanında label ve aria-label yok.',
            'why' => 'Bir ekran okuyucu ne yazılacağını söyleyemez; placeholder yazmaya başlar başlamaz kaybolur.',
            'fix' => 'Her alana bir <label for="…"> veya bir aria-label ekleyin.',
        ],
        'iframe_title' => [
            'title' => 'Title’sız çerçeveler',
            'found' => 'Bir iframe’in title’ı yok.',
            'why' => 'Ekran okuyucular adsız bir çerçeve duyurur ve kullanıcıları bir haritayı bir videodan ayıramaz.',
            'fix' => 'Çerçevenin ne gösterdiğini söyleyen bir title ekleyin.',
        ],
    ],
    'structure' => [
        'depth' => [
            'title' => 'Derin sayfalar',
            'found' => 'Dizinlenebilir bir sayfa, ana sayfadan tıklama cinsinden eşikten daha uzakta.',
            'why' => 'Arama motorları derin sayfaları daha seyrek ziyaret eder ve daha az değer verir; ziyaretçiler nadiren oraya ulaşır.',
            'fix' => 'Sayfaya bir kategoriden, menüden veya ilgili sayfalardan bağlantı verin.',
        ],
        'orphan' => [
            'title' => 'Yetim sayfalar',
            'found' => 'Sayfa site haritasında veya adres kayıt defterinde var, ancak sitenin hiçbir sayfası ona bağlantı vermiyor.',
            'why' => 'Ziyaretçiler ona ulaşamaz ve arama motorları hiçbir şeyin bağlanmadığı bir sayfayı önemsiz sayar.',
            'fix' => 'Sayfaya ait olduğu yerden bağlantı verin veya gerekmiyorsa yayından kaldırın.',
        ],
        'dead_end' => [
            'title' => 'Çıkmaz sayfalar',
            'found' => 'Sayfa, sitenin başka hiçbir sayfasına bağlantı vermiyor.',
            'why' => 'Ona gelen ziyaretçinin geri dönmekten başka gidecek yeri yok.',
            'fix' => 'Menüsüyle birlikte ana şablonun kullanıldığını kontrol edin ve ilgili sayfalara bağlantılar ekleyin.',
        ],
    ],
];
