<?php

return [
    'label' => 'Duyuru',
    'plural' => 'Duyurular',

    'actions' => [
        'send' => 'Bildirim gönder',
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
        'send' => 'Seçtiğiniz kitledeki herkesin bildirim ziline düşer. Yalnız yetkiniz olan kitleler listelenir.',
        'action_url' => 'Uygulama içi bir sayfa bağlantısı ekleyebilirsiniz; bildirimde "Aç" düğmesi olarak görünür.',
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
        'sent' => 'Bildirim :count kişiye gönderildi.',
    ],

    'widget' => [
        'heading' => 'Duyurular',
        'description' => 'Gönderilen bildirim ve duyurular; en yeni üstte.',
        'empty' => 'Henüz duyuru yok.',
    ],
];
