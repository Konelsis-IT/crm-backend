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
        // D-183: Teklif oluştur ekranının Potansiyel iş adımı (salt okunur).
        'case_in_proposal_step' => 'Potansiyel iş Teklif adımında seçilir; seçince özeti burada da görünür.',
        'ai_soon_short' => 'KonelsisAI yakında',
    ],

    // D-186: sürümleme personelde; "Düzenle" sürüm artırmaz, bu düğme N+1 açar.
    'new_version' => [
        'action' => 'Yeni teklif sürümü',
        'tooltip' => 'Güncel bilgilerle Sürüm :no hazırlayın; belgeleri çıkarıp yenilerini ekleyebilirsiniz',
        'heading' => 'Yeni sürüm (Sürüm :no)',
        'breadcrumb' => 'Yeni sürüm',
        'subheading' => 'Sürüm :current bilgileri dolu geldi. Kaydedince Sürüm :next oluşur; Sürüm :current olduğu gibi saklanır.',
        'save' => 'Yeni sürümü kaydet',
    ],

    'relation' => [
        'title' => 'Teklifler',
        'empty' => 'Henüz teklif yok.',
    ],

    'actions' => [
        'general_catalog' => 'Genel katalog',
        'general_catalog_tooltip' => 'Genel kataloğu yeni sekmede aç',
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu ":status" yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
        'open_business_case' => 'Potansiyel işi aç',
        'edit_latest' => 'Teklifi düzenle (:no)',
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
        'fixed' => 'Teklif durumu, teklif düzenleme ekranının başındaki teklif durumu düğmesinden değiştirilir',
        'no_targets' => 'Bu durumdan elle geçiş yok',
        'no_version' => 'Sürüm yok',
        // D-182: baslikta Teklif durumu acilir dugmesi (detay ve duzenleme).
        'no_offer_status' => 'Teklif durumu yok',
        'menu_label' => ':status ▾',
        'menu_tooltip' => 'Teklif durumunu değiştirin; seçtiğiniz durum hemen kaydedilir, yeni sürüm açılmaz',
        'confirm_heading' => 'Teklif ":status" olsun mu?',
        'confirm_description' => 'Teklif durumu ":from" iken ":to" olur; bu durumdan geri dönülmez. Yeni sürüm açılmaz.',
        'approved_note' => 'Teklif henüz gönderilmediyse güncel sürüm Gönderildi olarak işaretlenir; potansiyel iş Kazanıldı olur.',
        'lost_note' => 'Potansiyel işin bütün teklifleri kaçan fırsatsa potansiyel iş Kaybedildi olur. Gerekçe yazmak isteğe bağlıdır.',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'offer_status_changed' => 'Teklif durumu ":status" olarak kaydedildi.',
        'done' => 'İşlem tamamlandı.',
        'created' => 'Teklif oluşturuldu: :no',
        'draft_saved' => 'Teklif taslak olarak kaydedildi: :no',
        'new_version' => 'Teklifin yeni sürümü oluşturuldu: Sürüm :no',
        // D-186: Düzenle güncel sürümü yerinde değiştirir.
        'saved_in_place' => 'Kaydedildi (Sürüm :no güncellendi).',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
