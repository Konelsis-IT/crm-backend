<?php

return [
    'label' => 'Duyuru',
    'plural' => 'Duyurular',

    'actions' => [
        'send' => 'Duyuru gönder',
        'submit' => 'Gönder',
        'open' => 'Aç',
        'read' => 'Oku',
        'close' => 'Kapat',
    ],

    'fields' => [
        'audience_kind' => 'Kime',
        'department' => 'Departman',
        'role' => 'Rol',
        'personnel' => 'Personel',
        'priority' => 'Önem',
        'title' => 'Başlık',
        'body' => 'Mesaj',
        'action_url' => 'Bağlantı (isteğe bağlı)',
        'sent_at' => 'Gönderildi',
        'sender' => 'Gönderen',
        'audience' => 'Kitle',
        'recipient_count' => 'Alıcı',
    ],

    'help' => [
        'send' => 'Duyuru seçtiğiniz kitledeki herkesin bildirim ziline düşer ve Genel bakıştaki Duyurular bölümünde görünür. Yalnız yetkiniz olan kitleler listelenir.',
        'action_url' => 'Uygulama içi bir sayfa bağlantısı ekleyebilirsiniz; zil bildiriminde "Aç", Duyurular bölümünde "Bağlantıyı aç" olarak görünür.',
    ],

    'audiences' => [
        'team_of' => ':name ekibi',
        'department_of' => ':name departmanı',
        'role_of' => ':name rolü',
        'personnel_count' => ':count kişi',
        'all' => 'Herkes',
    ],

    'notifications' => [
        'body' => ':sender: :body',
    ],

    'messages' => [
        'sent' => 'Duyuru :count kişiye gönderildi.',
    ],

    'widget' => [
        'heading' => 'Duyurular',
        'description' => 'Gönderilen duyurular; en yeni üstte.',
        'empty' => 'Henüz duyuru yok.',
        'open_link' => 'Bağlantıyı aç',
        'show_all' => 'Tümünü gör',
    ],
];
