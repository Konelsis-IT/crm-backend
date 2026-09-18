<?php

/*
|--------------------------------------------------------------------------
| Konelsis platform ayarlari (M01)
|--------------------------------------------------------------------------
|
| Semayi degistiren, otomatik test calistiran veya kod bicimlendiren hicbir
| ayar burada bulunmaz; bu islemler yetkili DBA/DevOps surecine aittir.
|
*/

return [

    /*
    | Tek Konelsis organizasyonu ve ana tuzel kisiligi icin seed varsayilanlari.
    | Resmi unvan hukuk/finans dogrulamasina kadar yer tutucudur (karar D-01).
    */
    'organization' => [
        'code' => env('KONELSIS_ORGANIZATION_CODE', 'KONELSIS'),
        'name_tr' => env('KONELSIS_ORGANIZATION_NAME_TR', 'Konelsis'),
        'name_en' => env('KONELSIS_ORGANIZATION_NAME_EN', 'Konelsis'),
        'default_locale' => env('KONELSIS_DEFAULT_LOCALE', 'tr'),
        'default_timezone' => env('KONELSIS_DEFAULT_TIMEZONE', 'Europe/Istanbul'),
        'default_currency' => env('KONELSIS_DEFAULT_CURRENCY', 'TRY'),
    ],

    'legal_entity' => [
        'code' => env('KONELSIS_LEGAL_ENTITY_CODE', 'KONELSIS_MAIN'),
        'legal_name' => env('KONELSIS_LEGAL_ENTITY_NAME', 'Konelsis (resmi unvan dogrulanacak)'),
        'short_name' => env('KONELSIS_LEGAL_ENTITY_SHORT_NAME', 'Konelsis'),
        'country' => env('KONELSIS_LEGAL_ENTITY_COUNTRY', 'TR'),
        'currency' => env('KONELSIS_LEGAL_ENTITY_CURRENCY', 'TRY'),
        'timezone' => env('KONELSIS_LEGAL_ENTITY_TIMEZONE', 'Europe/Istanbul'),
    ],

    /*
    | Sema degisikligi korumasi. Uygulama semayi kendi basina degistiremez:
    | migration/seed komutlari yalniz DBA o kabukta
    | KONELSIS_SCHEMA_CHANGES_ALLOWED=true dedigi zaman calisir. Yikici
    | komutlar ayrica KONELSIS_DESTRUCTIVE_SCHEMA_ALLOWED=true ister ve
    | production'da her zaman reddedilir.
    */
    'schema_guard' => [
        'enabled' => (bool) env('KONELSIS_SCHEMA_GUARD', true),
        'changes_allowed' => (bool) env('KONELSIS_SCHEMA_CHANGES_ALLOWED', false),
        'destructive_allowed' => (bool) env('KONELSIS_DESTRUCTIVE_SCHEMA_ALLOWED', false),
    ],

    /*
    | Is dosyasi ve teklifte secilebilen para birimleri (16 Eylul 2026
    | kullanici karari, D-101): is dosyasi ve teklifte yalniz bu para
    | birimleri sunulur. Kodlar currencies tablosunda tanimli olmalidir;
    | ReferenceOptions::offerCurrencies() listeyi tabloyla kesistirir.
    */
    'offer_currencies' => ['TRY', 'USD', 'EUR', 'RON'],

    /*
    | Personel hareketleri kaydinda degeri gosterilmeyecek alanlar.
    */
    'activity' => [
        'hidden_keys' => [
            'password', 'password_confirmation', 'current_password', 'remember_token',
            'api_key', 'token', 'access_token', 'refresh_token',
        ],
    ],

    /*
    | Sosyal Medya modulu (B31, D-106). Boyutlar kilobayt/megabayt cinsindedir;
    | dosyalar `local` diskte ilgili dizinlere yazilir, uygulama hicbir dosyayi
    | silmez. "Bugun" her yerde organization.default_timezone ile hesaplanir
    | (App\Services\SocialMedia\SocialClock); burada ayri bir saat dilimi ya da
    | disk anahtari yoktur.
    */
    'social_media' => [
        // Yukleme sinirlari
        'max_image_kb' => 25600,             // 25 MB
        'max_video_mb' => 1024,              // 1 GB, parcali yukleme
        'chunk_kb' => 5120,                  // 5 MB'lik parcalar
        'max_report_kb' => 20480,            // 20 MB istatistik raporu
        'image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'video_mimes' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'report_mimes' => [
            'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/csv', 'text/plain', 'application/csv',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/CDFV2', 'application/octet-stream',
        ],
        'report_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'csv', 'xls', 'xlsx'],

        // Gorsel isleme
        'max_source_megapixels' => 24,
        'max_versions_per_media' => 12,

        // Depolama dizinleri (local disk) ve gecici dosya omru
        'image_directory' => 'social/images',
        'video_directory' => 'social/videos',
        'tmp_directory' => 'social/tmp',
        'tmp_ttl_hours' => 24,
        'max_open_uploads' => 3,

        // Metin sinirlari
        'body_html_max_bytes' => 1048576,    // 1 MB
        'caption_max' => 5000,
        'comment_max' => 4000,
        'note_max' => 4000,
        'short_text_soft_limit' => 280,
        'short_text_hard_limit' => 25000,

        // Planlama, hatirlatma ve acil onay
        'approaching_days' => 3,             // "yaklasiyor" penceresi
        'urgent_window_days' => 3,           // plan tarihi bugun + N gun icindeyse acil onay istenebilir
        'urgent_cooldown_hours' => 12,

        // Depolama kutusu
        'storage_warn_free_gb' => 10,
        'prefer_link_over_mb' => 200,
    ],

];
