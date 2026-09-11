<?php

return [
    'label' => 'Doküman',
    'plural' => 'Dokümanlar',

    'sections' => [
        'main' => 'Doküman bilgileri',
        'identity' => 'Kimlik',
        'ownership' => 'Sahiplik ve sınıflandırma',
        'context' => 'Bağlam',
        'initial_content' => 'Belgenin aslı',
        'header' => 'Doküman kartı',
        'content' => 'Güncel içerik',
        'body' => 'Belge içeriği',
        'file' => 'Dosya',
    ],

    'fields' => [
        'project' => 'Proje',
        'document_no' => 'Doküman no',
        'title' => 'Başlık',
        'document_type' => 'Doküman tipi',
        'is_controlled' => 'Kontrollü doküman',
        'owner' => 'Sahip',
        'owner_org_unit' => 'Sahip birim',
        'classification' => 'Gizlilik sınıfı',
        'retention_policy' => 'Saklama politikası',
        'default_language' => 'Varsayılan dil',
        'description' => 'Açıklama',
        'current_revision' => 'Güncel revizyon',
        'status' => 'Durum',
        'created_at' => 'Oluşturulma',
    ],

    'tabs' => [
        'revisions' => 'Revizyonlar',
        'approvals' => 'Onaylar',
        'shares' => 'Paylaşımlar',
        'links' => 'Bağlantılar',
        'reviews' => 'İncelemeler',
        'distributions' => 'Dağıtımlar',
        'acknowledgements' => 'Teyitler',
    ],

    'help' => [
        'project' => 'Belge bir projeye bağlıysa seçin; proje raporları ve devir manifestleri bu bağ üzerinden dallanır.',
        'document_no' => 'Doküman tipine göre otomatik üretilir.',
        'classification' => 'Boş bırakılırsa doküman tipinin varsayılanı kullanılır.',
        'retention_policy' => 'Boş bırakılırsa doküman tipinin varsayılanı kullanılır.',
        'identity' => 'Belgenin adı, tipi ve dili. Doküman numarası tipin önekinden otomatik üretilir.',
        'ownership' => 'Belgeden kim sorumlu, hangi birime ait, ne kadar gizli ve ne kadar süre saklanacak.',
        'context' => 'Belgenin bağlı olduğu proje; boş bırakılabilir.',
        'initial_content' => 'Belgenin aslını yükleyin ya da belgeyi burada yazın; ilk revizyon (01) taslak olarak açılır.',
        'title' => 'Belgenin listelerde ve kartta görünen adı.',
        'document_type' => 'Tip; numaralandırma, varsayılan gizlilik sınıfı ve saklama politikası buradan gelir.',
        'description' => 'Belgenin ne olduğu, kısa açıklama.',
        'owner' => 'Belgeden sorumlu kişi; varsayılan olarak siz.',
        'is_controlled' => 'Kontrollü belgelerin dağıtımı ve revizyonu izlenir.',
        'first_revision_note' => 'İlk sürüm için kısa not (isteğe bağlı).',
        'create_intro' => 'Bilgileri doldurun ve belgenin aslını ekleyin; kaydedince belge detay arayüzü açılır.',
        'no_content' => 'Bu belgenin henüz içeriği yok. "Yeni sürüm" ile dosya yükleyin ya da belgeyi sistemde yazın.',
        'working_revision' => 'Üzerinde çalışılan taslak: Rev :code (:status). Yayımlanınca güncel içerik bu olur.',
        'content' => 'Yayımlanmış revizyon; yoksa en son açılan revizyon gösterilir.',
        'no_file' => 'Bu revizyona dosya eklenmemiş.',
        'new_revision' => 'Yeni bir revizyon açılır (taslak). Yayımlanmış revizyon değiştirilmez.',
        'share_public' => 'Bağlantı şimdilik yetkisiz erişime açıktır: bağlantıyı bilen herkes belgeyi görür. Yetki/şifre mekanizması sonraki turda gelecek.',
        'approval' => 'Üzerinde çalışılan revizyon onay politikasına göre onaycılara gönderilir; onaylanınca revizyon "onaylandı" olur.',
        'no_policy' => 'Bu konu için yayımlı bir onay politikası yok. Ayarlar › Onay Politikaları\'ndan ekleyin.',
        'approval_tab' => 'Bu belgenin revizyonları için açılmış onay talepleri. Karar "Onaylar" ekranında verilir.',
    ],

    'values' => [
        'previewable' => 'Tarayıcıda önizlenebilir',
        'download_only' => 'Yalnız indirme',
    ],

    'actions' => [
        'download_current' => 'Güncel dosyayı indir',
        'preview_current' => 'Güncel dosyayı önizle',
        'create_with_file' => 'Doküman ekle (dosya ile)',
        'edit_details' => 'Bilgileri düzenle',
        'new_revision' => 'Yeni sürüm',
        'share' => 'Paylaş',
        'send_to_approval' => 'Onaya gönder',
        'open_share' => 'Bağlantıyı aç',
        'view_body' => 'İçeriği görüntüle',
        'close' => 'Kapat',
    ],

    'messages' => [
        'revision_created' => 'Yeni revizyon açıldı.',
        'share_created' => 'Paylaşım bağlantısı oluşturuldu.',
        'approval_requested' => 'Onay talebi açıldı; onaycılar bildirildi.',
    ],

    'share' => [
        'title' => 'Paylaşılan belge',
        'intro' => 'Bu belge sizinle paylaşıldı.',
        'unavailable' => 'Bu paylaşım bağlantısı artık geçerli değil.',
        'no_content' => 'Belgenin henüz içeriği yok.',
        'footer' => 'Bu belge :app üzerinden paylaşıldı.',
    ],

    'relation' => [
        'automation_title' => 'SCADA / PLC dokümanları',
        'revisions' => [
            'title' => 'Revizyonlar',
            'empty' => 'Henüz revizyon yok.',
        ],
        'links' => [
            'title' => 'Bağlantılar',
            'empty' => 'Henüz bağlantı yok.',
        ],
        'reviews' => [
            'title' => 'İncelemeler',
            'empty' => 'Henüz inceleme kaydı yok.',
        ],
        'distributions' => [
            'title' => 'Dağıtımlar',
            'empty' => 'Henüz dağıtım kaydı yok.',
        ],
        'acknowledgements' => [
            'title' => 'Okundu/Kabul Teyitleri',
            'empty' => 'Henüz teyit kaydı yok.',
        ],
    ],
];
