<?php

return [
    'nav' => 'UI Deneme',
    'title' => 'Kart tasarımı denemesi',

    'pages' => [
        'cards' => 'Kart tasarımı',
        'plugin' => 'Eklenti: Filament Cards',
    ],

    'plugin' => [
        'title' => 'Eklenti denemesi: Filament Cards',
        'subheading' => 'harvirsidhu/filament-cards eklentisi (MIT). Asıl amacı sayfa/kaynak navigasyonu için kart "hub"ı kurmaktır; burada aynı kartlar belge kayıtlarıyla da doldurulup karşılaştırma için sunuluyor.',
        'groups' => [
            'documents' => 'Belge kayıtları (her belge bir kart)',
            'documents_help' => 'Kart = başlık, alt başlık, simge, durum rozeti ve bağlantı. Kart içinde alan listesi yok; tıklanınca belge detayına gider.',
            'hub' => 'Belgeler kümesi (navigasyon kartı — eklentinin asıl amacı)',
            'hub_help' => 'Kaynak sınıfları verilir; etiket, simge ve bağlantı Filament navigasyonundan otomatik gelir.',
        ],
        'search' => 'Kartlarda ara…',
        'values' => [
            'no_revision' => 'Revizyon yok',
        ],
    ],
    'subheading' => 'Projeler, personel, belgeler ve ileride sunucular için ortak kayıt kartı. Beğenilen tasarım ilgili listelere taşınacak; bu sayfa hiçbir iş akışına bağlı değil.',

    'tabs' => [
        'schema' => 'A · Şema kartları',
        'table' => 'B · Tablo kartları (arama, süzgeç, sayfalama)',
        'list' => 'C · Şema kartları + arama ve sayfalama',
    ],

    'list' => [
        'source' => 'Kaynak',
        'search' => 'Kartlarda ara…',
    ],

    'variants' => [
        'cover' => 'Kapaklı',
        'row' => 'Yatay',
    ],

    'sections' => [
        'projects' => 'Projeler',
        'personnel' => 'Personel',
        'documents' => 'Belgeler',
        'servers' => 'Sunucular',
    ],

    'help' => [
        'intro' => 'Üstteki "Kapaklı / Yatay" düğmeleriyle yerleşimi değiştirin. Her kart aynı iskeleti kullanır: görsel · başlık ve alt başlık · sağ üstte durum · rozet satırı · simgeli bilgi alanları · altta eylemler.',
        'schema' => 'Filament şema bileşenleriyle kurulan kartlar. Tam serbest yerleşim; proje/belge detay kartlarıyla aynı dil. Arama ve sayfalama bu sekmede yok (ilk 8 kayıt).',
        'table' => 'Aynı anatomi Filament tablosunun kart ızgarasıyla. Arama, süzgeç, sıralama, sayfalama ve satır eylemleri tablonun kendisinden gelir; listelerde tablo/kart geçişi bu yolla yapılır.',
        'list' => 'A sekmesindeki kapaklı kart, listelerde kullanılacak haliyle: arama kutusu, sayfa boyu ve sayfalama şema bileşenleriyle kuruldu (tablo yok). Personel, Belgeler ve Projeler listelerinde varsayılan görünüm budur; başlıktaki simge düğmesiyle tabloya geçilir. Arama, sayfa ve sayfa boyu adres çubuğunda taşınır.',
        'servers_mock' => 'Sunucu modülü henüz yok; örnek veriyle kart anatomisi gösteriliyor.',
    ],

    'entries' => [
        'address' => 'Adres',
        'channels' => 'İletişim',
        'contact' => 'Yetkili kişi',
        'manager' => 'Proje yöneticisi',
        'city' => 'Şehir',
        'dates' => 'Plan',
        'value' => 'Sözleşme değeri',
        'phone' => 'Telefon',
        'email' => 'E-posta',
        'hired_on' => 'İşe giriş',
        'last_login' => 'Son giriş',
        'owner' => 'Sahip',
        'project' => 'Proje',
        'file' => 'Dosya',
        'updated' => 'Güncellenme',
        'disk' => 'Disk',
        'uptime' => 'Çalışma süresi',
        'last_check' => 'Son kontrol',
        'environment' => 'Ortam',
        'location' => 'Konum',
    ],

    'values' => [
        'no_focus' => 'Odak yok',
        'no_channels' => 'İletişim bilgisi yok',
        'steps_ready' => ':ready / :total adım hazır',
        'no_title' => 'Görev tanımsız',
        'no_cover' => 'Kapak görseli yok',
        'empty' => 'Gösterilecek proje yok.',
        'server_status' => [
            'running' => 'Çalışıyor',
            'warning' => 'Uyarı',
            'down' => 'Erişilemiyor',
        ],
        'environment' => [
            'production' => 'Üretim',
            'test' => 'Test',
        ],
    ],

    'actions' => [
        'open' => 'Aç',
        'edit' => 'Düzenle',
        'details' => 'Ayrıntılar',
    ],
];
