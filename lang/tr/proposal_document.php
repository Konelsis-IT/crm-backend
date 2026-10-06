<?php

return [
    'label' => 'Teklif dokümanı',
    'plural' => 'Teklif dokümanları',

    'sections' => [
        'main' => 'Doküman bilgileri',
    ],

    'fields' => [
        // D-158: Dokumanlar sekmesi.
        'document' => 'Belge',
        'file' => 'Dosya',
        'revision' => 'Revizyon',
        'uploaded_at' => 'Yüklenme',
        'role' => 'Belge türü',
        'created_at' => 'Oluşturulma',
        'document_revision' => 'Doküman revizyonu',
        'document_role' => 'Doküman rolü',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Dokümanlar',
        'empty' => 'Henüz doküman yok.',
    ],

    'actions' => [
        'upload' => 'Belge yükle',
        'upload_revision' => 'Yeni sürüm yükle',
        'download' => 'İndir',
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
    ],

    'help' => [
        'upload' => 'Bu türde belge varsa yüklediğiniz dosya o belgenin yeni revizyonu olur; teklifin yeni sürümü açılır. Değişmeyen belgeler kopyalanmaz.',
        'upload_revision' => 'Seçtiğiniz dosya bu belgenin yeni revizyonu olur ve teklifin yeni sürümü açılır.',
        'current_only' => 'Güncel sürümün belgeleri. Önceki sürümlerin belgeleri sayfanın üstündeki Sürümler düğmesinden görülür.',
    ],

    'messages' => [
        'uploaded' => 'Belge yüklendi; teklifin yeni sürümü oluştu: Sürüm :no',
        'uploaded_in_place' => 'Belge yüklendi.',
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
