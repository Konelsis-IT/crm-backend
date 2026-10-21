<?php

// D-232: Operasyon - Satin alma > Tedarikciler ve Hizmet alanlari (PurchaseSupplierResource, PurchaseServiceAreaResource).
return [
    'label' => 'Tedarikçi',
    'plural' => 'Tedarikçiler',
    'nav' => 'Tedarikçiler',
    'empty' => 'Tedarikçi yok.',

    'sections' => [
        'supplier' => 'Satın alma',
        'side' => 'Tedarikçi',
    ],

    'fields' => [
        'name' => 'Ad',
        'service_areas' => 'Hizmet alanları',
        'note' => 'Not',
        'shared' => 'Taraflarda da',
        'contacts_count' => 'Kişi',
        'archived_at' => 'Arşiv tarihi',
        'created_at' => 'Eklenme',
    ],

    'help' => [
        'service_areas' => 'Firmanın hizmet verdiği alanlar; birden fazla seçilebilir. Yeni alan "Hizmet alanları" sayfasından eklenir.',
        'note' => 'Ürün notu, şehir, ilgili kişi gibi kısa bilgiler.',
    ],

    'values' => [
        'shared' => 'Taraflarda da kayıtlı',
        'purchase_only' => 'Yalnız tedarikçi',
    ],

    'filters' => [
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
        'areas' => 'Hizmet alanı',
        'shared' => 'Taraflarda da',
    ],

    'actions' => [
        'create' => 'Tedarikçi ekle',
        'archive' => 'Arşive al',
        'restore' => 'Arşivden çıkar',
        'areas' => 'Hizmet alanları',
        'open_party' => 'Taraflar\'daki kaydı aç',
    ],

    'modals' => [
        'archive_description' => 'Tedarikçi listede görünmez, seçim kutularında çıkmaz; kayıtları silinmez. Taraflar\'daki kayıt etkilenmez.',
        'restore_description' => 'Tedarikçi yeniden listede ve seçimlerde görünür.',
    ],

    'messages' => [
        'created' => 'Tedarikçi eklendi.',
        'saved' => 'Tedarikçi kaydedildi.',
        'archived' => 'Tedarikçi arşive alındı.',
        'restored' => 'Tedarikçi arşivden çıkarıldı.',
    ],

    'area' => [
        'label' => 'Hizmet alanı',
        'plural' => 'Hizmet alanları',
        'empty' => 'Hizmet alanı yok.',
        'fields' => [
            'name' => 'Ad',
            'sort_order' => 'Sıra',
            'suppliers_count' => 'Tedarikçi',
        ],
        'actions' => [
            'create' => 'Hizmet alanı ekle',
            'edit' => 'Hizmet alanını düzenle',
        ],
        'messages' => [
            'created' => 'Hizmet alanı eklendi.',
            'saved' => 'Hizmet alanı kaydedildi.',
            'archived' => 'Hizmet alanı arşive alındı.',
            'restored' => 'Hizmet alanı arşivden çıkarıldı.',
        ],
        'modals' => [
            'archive_description' => 'Alan yeni seçimlerde çıkmaz; tedarikçilerdeki kayıtlar kalır.',
            'restore_description' => 'Alan yeniden seçilebilir.',
        ],
    ],
];
