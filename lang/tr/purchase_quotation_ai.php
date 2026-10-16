<?php

declare(strict_types=1);

// Konelsis AI ile satin alma teklifi okuma (D-192, u-mv0njps9-98cc).
return [
    'actions' => [
        'read' => [
            'label' => 'Konelsis AI ile belgeden doldur',
            'heading' => 'Konelsis AI ile belgeden doldur',
            'description' => 'Tedarikçinin teklif belgelerini yükleyin: PDF, Word, Excel ya da fotoğraf. Konelsis AI her belgenin teklif olup olmadığına bakar; teklifse tedarikçi, tarih, para birimi, koşullar ve kalemleri boş alanlara yazar. Girdiğiniz değerler değişmez. Düzenleme ekranında kaydedilmemiş değişiklik varsa önce kaydedin.',
            'files' => 'Belgeler',
            'files_hint' => 'En çok 10 belge; PDF ve fotoğraf belge başına 10 MB, toplam 20 MB.',
            'submit' => 'Okumaya gönder',
        ],
        'items' => [
            'label' => 'Konelsis AI olarak yeni oluşturduğum ürünler',
            'heading' => 'Konelsis AI olarak yeni oluşturduğum ürünler',
            'description' => 'Bu teklifteki kalemler Malzeme ve Hizmetler\'de bulunamadığı için Konelsis AI bunları açtı. Uygun olanları olduğu gibi ekleyin, uygun olmayanları düzenleyin ya da listeden başka bir kalem seçin.',
            'submit' => 'Kaydet',
            'accept_all' => 'Hepsi uygun, ekle',
        ],
    ],

    'items' => [
        'line' => 'Teklif satırı',
        'item' => 'Açılan kalem',
        'decision' => 'Karar',
        'decisions' => [
            'accept' => 'Uygun, ekle',
            'edit' => 'Düzenle ve ekle',
            'relink' => 'Listeden başka kalem seç',
        ],
        'name' => 'Ad',
        'kind' => 'Tür',
        'category' => 'Kategori',
        'unit' => 'Birim',
        'brand' => 'Marka',
        'target' => 'Listeden kalem',
        'later' => 'Karar verilmeyen satırlar listede kalır; sonra yeniden açabilirsiniz.',
        'saved_title' => 'Kalemler kaydedildi',
        'saved_body' => ':accepted kalem olduğu gibi eklendi, :edited kalem düzenlendi, :relinked satır listedeki başka bir kaleme bağlandı.',
        'nothing' => 'Karar verilen satır yok.',
    ],

    'notifications' => [
        'started_title' => 'Konelsis AI belgeleri okuyor',
        'started_body' => ':count belge gönderildi. Okuma bitince alanlar dolar ve sayfa yenilenir.',
        'completed_title' => 'Konelsis AI belgeleri okudu',
        'completed_body' => ':documents teklif belgesi okundu: :filled alan dolduruldu, :priced kalem fiyatlandı, :added kalem eklendi.',
        'no_quotation_title' => 'Teklif belgesi bulunamadı',
        'no_quotation_body' => 'Yüklenen belgelerin hiçbiri tedarikçi teklifi değil; alanlara dokunulmadı.',
        'failed_title' => 'Konelsis AI belgeleri okuyamadı',
        'new_items' => ':count yeni ürün / hizmet açıldı; "Konelsis AI olarak yeni oluşturduğum ürünler" penceresinden kontrol edin.',
    ],

    'progress' => [
        'title' => 'Konelsis AI belgeleri okuyor',
        'body' => ':count belge okunuyor. Bitince alanlar dolar ve sayfa kendiliğinden yenilenir.',
    ],

    // Belge satirindaki kisa aciklama (purchase_quotation_documents.ai_summary).
    'document' => [
        'quotation_summary' => ':reason (:lines kalem)',
        'currency_kept' => 'Belgenin para birimi :document; teklifte fiyatı girilmiş kalem olduğu için :current olarak bırakıldı.',
        'locked' => 'Teklif artık değiştirilemediği için alanlar doldurulmadı.',
        'legacy_format' => 'Eski Word / Excel biçimi (.doc / .xls) okunamıyor; PDF, .docx ya da .xlsx olarak yükleyin.',
        'unsupported' => 'Bu dosya türü Konelsis AI ile okunamıyor; PDF, Word, Excel ya da fotoğraf yükleyin.',
        'too_large' => 'Dosya Konelsis AI\'nin okuyabileceği boyutu aşıyor (belge başına 10 MB, toplam 20 MB).',
        'too_many' => 'Bir okumada en çok 10 belge gönderilebilir.',
        'missing' => 'Dosya bulunamadı.',
        'blocked' => 'Dosya güvenlik taramasında engellendi.',
        'empty' => 'Belgede okunacak metin yok.',
        'unreadable' => 'Dosya açılamadı ya da okunamadı.',
        'not_sent' => 'Belge Konelsis AI\'ye gönderilemedi.',
        'no_result' => 'Konelsis AI bu belge için sonuç döndürmedi.',
    ],

    'failure' => [
        'timeout' => 'Konelsis AI zamanında yanıt vermedi; belgeleri yeniden yükleyip tekrar deneyin.',
        'service' => 'Konelsis AI okumayı tamamlayamadı; tekrar deneyin ya da alanları elle doldurun.',
        'limit' => 'Konelsis AI\'nin günlük okuma sınırı doldu; yarın tekrar deneyin.',
        'invalid_result' => 'Konelsis AI\'nin cevabı okunamadı; tekrar deneyin.',
    ],
];
