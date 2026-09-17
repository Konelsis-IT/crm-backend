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

];
