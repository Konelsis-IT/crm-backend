<?php

return [
    'label' => 'İletişim kişisi',
    'plural' => 'İletişim kişileri',

    'sections' => [
        'main' => 'Kişi bilgileri',
        'channels' => 'İletişim bilgileri',
    ],

    'fields' => [
        'channels' => 'İletişim',
        'contact' => 'Ad',
        'contact_name' => 'Ad',
        'created_at' => 'Oluşturulma',
        'department_note' => 'Departman notu',
        'is_primary' => 'Varsayılan',
        'network_note' => 'Network',
        'reason' => 'Gerekçe',
        'relationship_role' => 'İlişki rolü',
        'valid_from' => 'Başlangıç',
        'valid_until' => 'Bitiş',
    ],

    'relation' => [
        'title' => 'İletişim ve kişiler',
        'empty' => 'Henüz iletişim kaydı yok.',
    ],

    'help' => [
        'channels' => 'Bu kişiye ait tüm iletişim bilgilerini ekleyin: iş telefonu, cep, e-posta, faks. İstediğiniz kadar satır açabilirsiniz.',
        'contact_name' => 'Kişi adı ya da kanal adı (örneğin Santral, Muhasebe).',
        'network_note' => 'Bu kişiyle nereden tanışıldığı.',
    ],

    'actions' => [
        'add_channel' => 'İletişim bilgisi ekle',
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

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
