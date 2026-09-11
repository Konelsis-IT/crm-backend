<?php

return [
    'kinds' => [
        'error' => 'Hata',
        'not_found' => 'Bulunamadı',
        'forbidden' => 'Yetkisiz erişim',
        'auth' => 'Oturum gerekli',
        'expired' => 'Oturum süresi doldu',
        'throttle' => 'Çok fazla istek',
        'server' => 'Sunucu hatası',
        'maintenance' => 'Bakımdayız',
        'client' => 'İstek hatası',
    ],

    '401' => [
        'title' => 'Oturum açmanız gerekiyor',
        'message' => 'Bu sayfayı görmek için Konelsis hesabınızla giriş yapın.',
    ],
    '403' => [
        'title' => 'Bu sayfaya erişim yetkiniz yok',
        'message' => 'Rolünüz bu kaydı ya da işlemi kapsamıyor. Erişim gerektiğini düşünüyorsanız yöneticinizden yetki isteyin.',
    ],
    '404' => [
        'title' => 'Aradığınız sayfa bulunamadı',
        'message' => 'Bağlantı eskimiş olabilir ya da kayıt taşınmış olabilir. Menüden ilgili bölüme yeniden gidebilirsiniz.',
    ],
    '419' => [
        'title' => 'Sayfa zaman aşımına uğradı',
        'message' => 'Oturumunuz uzun süre işlem yapılmadığı için kapandı. Yeniden giriş yapıp işleminizi tekrar deneyin.',
    ],
    '429' => [
        'title' => 'Biraz yavaşlayalım',
        'message' => 'Kısa sürede çok fazla istek gönderildi. Birkaç saniye bekleyip tekrar deneyin.',
    ],
    '500' => [
        'title' => 'Beklenmeyen bir hata oluştu',
        'message' => 'İşlem kaydedilmedi. Sorun kaydedildi; sürerse sistem yöneticisine bildirin.',
    ],
    '503' => [
        'title' => 'Konelsis kısa süreliğine bakımda',
        'message' => 'Planlı bir güncelleme yapılıyor. Birkaç dakika sonra yeniden deneyin; verileriniz güvende.',
    ],
    '4xx' => [
        'title' => 'İstek işlenemedi',
        'message' => 'Gönderilen istek anlaşılamadı ya da geçersiz. Sayfayı yenileyip tekrar deneyin.',
    ],
    '5xx' => [
        'title' => 'Sunucu şu anda yanıt veremiyor',
        'message' => 'Geçici bir sorun oluştu. Kısa süre sonra tekrar deneyin.',
    ],

    'actions' => [
        'back' => 'Geri dön',
        'home' => 'Panele git',
        'login' => 'Giriş yap',
        'retry' => 'Tekrar dene',
    ],

    'footer' => [
        'help' => 'Sorun sürerse sistem yöneticinize bu sayfanın kodunu iletin.',
        'signed_in_as' => 'Oturum: :name',
    ],
];
