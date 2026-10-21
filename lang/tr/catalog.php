<?php

// D-189: Satin Alma > Malzeme ve Hizmetler ve Kategoriler ekranlari (D-192 oncesi "Katalog")
// (CatalogItemResource, CatalogCategoryResource).
return [
    // D-192: menu adi "Malzeme ve Hizmetler" (eski "Katalog").
    'nav' => 'Malzeme ve Hizmetler',
    'label' => 'Malzeme / hizmet',
    'plural' => 'Malzeme ve Hizmetler',

    // D-228: Genel gider (Muhasebe / Finans) ve Idari kadro (Idari) kendi ekranlarinda.
    'general_expense' => [
        'nav' => 'Genel Gider',
        'label' => 'Genel gider',
        'plural' => 'Genel Gider',
        'create' => 'Genel gider ekle',
        'categories' => 'Genel gider kategorileri',
    ],
    'admin_staff' => [
        'nav' => 'İdari Kadro',
        'label' => 'İdari kadro',
        'plural' => 'İdari Kadro',
        'create' => 'İdari kadro ekle',
        'categories' => 'İdari kadro kategorileri',
    ],

    'tabs' => [
        'all' => 'Tümü',
    ],

    'sections' => [
        'main' => 'Kalem bilgileri',
        'purchase' => 'Satın alma',
        'general' => 'Genel',
        'orders' => 'Siparişler',
    ],

    'fields' => [
        'kind' => 'Tür',
        'usage_area' => 'Kullanım yeri',
        'category' => 'Kategori',
        'name' => 'Ad',
        'unit' => 'Birim',
        'currency' => 'Para birimi',
        'brand' => 'Marka',
        'requires_approval' => 'Sipariş onayı',
        'renewal_months' => 'Yenileme',
        'specification' => 'Teknik özellik',
        'aliases' => 'Excel eşleme adları',
        'cost_lines_count' => 'Teklif satırı',
        'needs_count' => 'Satın alınacak',
        'archived_at' => 'Arşiv tarihi',
    ],

    'help' => [
        'usage_area' => 'Ürünün ana kullanılacağı yer; Şirket içi ve Saha sekmelerinde de görünür.',
        'requires_approval' => 'Kapalıysa sipariş onaysız tamamlanır',
        'renewal_months' => 'Sunucu, abonelik, kırtasiye gibi yenilenen kalemler',
        'aliases' => 'Excel\'de farklı yazılan adlar bu kaleme bağlanır; bir sonraki yüklemede kendiliğinden eşleşir.',
        'archive' => 'Kalem arşive alınır; listede ve seçim kutularında görünmez. Teklif ve sipariş bağlantıları korunur.',
        'restore' => 'Kalem yeniden listede ve seçim kutularında görünür.',
    ],

    'values' => [
        'approval_required' => 'Onay gerekir',
        'approval_not_required' => 'Onaysız',
        'renewal' => ':n ayda bir',
        'months_suffix' => 'ay',
        'no_aliases' => 'Eşleme adı yok',
    ],

    'orders' => [
        'summary' => ':count satın alınacak kalem · en uygun birim fiyat :price',
        'summary_no_quote' => ':count satın alınacak kalem · henüz tedarikçi teklifi yok',
        'none' => 'Bu malzeme / hizmet için henüz satın alınacak kalem açılmadı.',
    ],

    'relations' => [
        'cost_lines' => [
            'title' => 'Teklif ve projeler',
            'empty' => 'Bu kalemi isteyen teklif maliyet satırı yok.',
            'proposal' => 'Teklif',
            'project' => 'Proje',
            'path' => 'Excel\'deki yeri',
            'quantity' => 'Miktar',
            'unit_cost' => 'Birim maliyet',
            'total_cost' => 'Toplam maliyet',
            'price_state' => 'Fiyat durumu',
            'purchase_status' => 'Satın alma',
            'not_sent' => 'Gönderilmedi',
        ],
        'needs' => [
            'title' => 'Satın alınacaklar',
            'empty' => 'Bu malzeme / hizmet için satın alınacak kalem yok.',
            'need' => 'Satın alınacak kalem',
            'status' => 'Durum',
            'proposal' => 'Teklif',
            'project' => 'Proje',
            'quantity' => 'Miktar',
            'recommended' => 'Önerilen teklif',
            'needed_by' => 'Termin',
        ],
        // D-194: kalemin bütün satın alma tekliflerindeki fiyatları ("fiyat geçmişi olacak").
        'price_history' => [
            'title' => 'Fiyat geçmişi',
            'empty' => 'Bu malzeme / hizmet için henüz fiyatı girilmiş satın alma teklifi yok.',
            'quote_date' => 'Teklif tarihi',
            'supplier' => 'Tedarikçi',
            'quotation' => 'Teklif no',
            'quantity' => 'Miktar',
            'unit_price' => 'Birim fiyat',
            'unit_price_try' => 'TL karşılığı',
            'unit_price_try_help' => 'İndirimli birim fiyatın TL karşılığı (teklifin kuruyla)',
            'discount' => 'İndirim',
            'status' => 'Teklif durumu',
            'order' => 'Sipariş no',
            'summary' => [
                'lowest' => 'En düşük: :price',
                'last' => 'Son: :price',
                'average' => 'Ortalama: :price',
                'count' => ':count fiyat',
                'no_rate' => 'TL karşılığı olmayan teklif özete girmez',
            ],
            'badges' => [
                'option' => 'Opsiyon',
                'cancelled' => 'İptal',
            ],
            'filters' => [
                'supplier' => 'Tedarikçi',
                'status' => 'Teklif durumu',
            ],
        ],
    ],

    'filters' => [
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
        'kind' => 'Tür',
        'category' => 'Kategori',
    ],

    'actions' => [
        'create' => 'Malzeme / hizmet ekle',
        'archive' => 'Arşive al',
        'restore' => 'Arşivden çıkar',
    ],

    'messages' => [
        'archived' => 'Kalem arşive alındı.',
        'restored' => 'Kalem arşivden çıkarıldı.',
    ],

    'empty' => 'Listede malzeme / hizmet yok.',

    // Kategoriler (CatalogCategoryResource; D-192, eski "Katalog kategorileri")
    'category' => [
        'nav' => 'Kategoriler',
        'label' => 'Malzeme / hizmet kategorisi',
        'plural' => 'Kategoriler',
        'empty' => 'Bu türde kategori yok.',
        'tabs' => [
            'product' => 'Ürün / Hizmet',
        ],
        'fields' => [
            'kind' => 'Tür',
            'parent' => 'Üst kategori',
            'name' => 'Ad',
            'path' => 'Yeri',
            'items_count' => 'Kalem sayısı',
        ],
        'help' => [
            'parent' => 'Boş bırakılırsa ana kategori olur.',
            'kind' => 'Ürün ve Hizmet aynı kategorileri kullanır.',
            'archive' => 'Kategori arşive alınır; seçim kutularında görünmez. Kategorideki kalemler değişmez.',
            'restore' => 'Kategori yeniden seçim kutularında görünür.',
        ],
        'actions' => [
            'create' => 'Kategori ekle',
            'edit' => 'Kategoriyi düzenle',
        ],
        'messages' => [
            'created' => 'Kategori eklendi.',
            'saved' => 'Kategori kaydedildi.',
            'archived' => 'Kategori arşive alındı.',
            'restored' => 'Kategori arşivden çıkarıldı.',
        ],
    ],
];
