<?php

return [
    'label' => 'Teklif',
    'plural' => 'Teklifler',

    'sections' => [
        // D-158: surum penceresi ve kapsam karti.
        'version' => 'Sürüm :no',
        'version_documents' => 'Sürümün belgeleri',
        'main' => 'Teklif bilgileri',
        'business_case' => 'Potansiyel iş',
        'header' => 'Teklif kartı',
        'current_version' => 'Güncel sürüm',
        // D-155: proje kapsami ve teklif belgeleri.
        'scope' => 'Proje kapsamı',
        'documents' => 'Teklif belgeleri',
    ],

    'wizard' => [
        'case_description' => 'Teklifin bağlı olduğu potansiyel iş',
    ],

    'tabs' => [
        'all' => 'Tümü',
        'submitted' => 'Verilen Teklifler',
        'to_be_submitted' => 'Verilecek Teklifler',
        'lost' => 'Kaçan Fırsat',
    ],

    'fields' => [
        'business_case' => 'Potansiyel iş',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'is_selected' => 'Seçili',
        'offer_status' => 'Teklif durumu',
        'owner' => 'Sahip',
        'proposal_no' => 'Teklif no',
        'case_code' => 'Potansiyel iş kodu',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'title' => 'Başlık',
    ],

    'help' => [
        'business_case' => 'Teklif bu potansiyel iş için açılır. Seçince potansiyel işin özeti aşağıda görünür.',
        'documents' => 'Excel ya da herhangi bir dosya yüklenebilir. Yeni yükleme aynı belgenin yeni revizyonu olur; eski dosya silinmez.',
        'ai_soon_short' => 'KonelsisAI yakında',
        'revision_notice' => 'Kaydettiğinizde alanlarda, kapsamda ya da belgelerde değişiklik varsa teklifin yeni sürümü (Sürüm :next) oluşur. Önceki sürüm ve belgeleri saklanır.',
    ],

    'relation' => [
        'title' => 'Teklifler',
        'empty' => 'Henüz teklif yok.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu ":status" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
        'open_business_case' => 'Potansiyel işi aç',
        'edit_selected' => 'Teklifi düzenle (:no)',
    ],

    // D-158: "Sürümler" düğmesi ve sürüm penceresi.
    'versions' => [
        'button' => 'Sürümler',
        'item' => 'Sürüm :no · :status · :date',
        'current' => 'Sürüm :no · :status · güncel',
        'modal_description' => ':status · :date. Pencere kapanınca sayfada güncel sürüm görünmeye devam eder.',
        'close' => 'Kapat',
        'scope_totals' => 'Toplamlar',
        'no_documents' => 'Bu sürümde belge yok.',
    ],

    'steps' => [
        'version' => 'Sürüm :no · :status',
        'no_version' => 'Henüz sürüm yok',
    ],

    // D-161: durum dugmesi (guncel surumun durumu).
    'status' => [
        'fixed' => 'Durum teklif düzenleme ekranından değiştirilir',
        'change' => 'Teklifin durumunu değiştirmek için tıklayın',
        'no_targets' => 'Bu durumdan elle geçiş yok',
        'no_version' => 'Sürüm yok',
        'modal_heading' => 'Teklifin durumunu değiştir',
        'modal_description' => 'Şu anki durum: :status (Sürüm :no). Yeni sürüm açılmaz. Potansiyel işin durumu ve Teklif durumu (Verilecek / Verilen) buna göre güncellenir.',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
        'created' => 'Teklif oluşturuldu: :no',
        'draft_saved' => 'Teklif taslak olarak kaydedildi: :no',
        'new_version' => 'Teklifin yeni sürümü oluşturuldu: Sürüm :no',
        'saved_no_version' => 'Kaydedildi. Teklifin içeriği değişmediği için yeni sürüm açılmadı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
