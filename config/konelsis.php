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
    | Seed ile olusturulan ilk yonetici personel. Parola bos birakilirsa
    | rastgele uretilir ve konsolda bir kez gosterilir.
    */
    'bootstrap_admin' => [
        'email' => env('KONELSIS_BOOTSTRAP_ADMIN_EMAIL'),
        'name' => env('KONELSIS_BOOTSTRAP_ADMIN_NAME', 'Sistem Yoneticisi'),
        'password' => env('KONELSIS_BOOTSTRAP_ADMIN_PASSWORD'),
    ],

    /*
    | M03 (D-16) gercek role gectikten sonra bu liste calisma zamaninda
    | okunmaz; RoleResolver artik spatie/laravel-permission uzerinden
    | hasRole() sorar. auditor_emails yalniz RoleSeeder'da bir kerelik
    | gecis kaynagi olarak kullanilir (personel varsa auditor rolu atanir).
    */
    'interim_roles' => [
        'auditor_emails' => env('KONELSIS_AUDITOR_EMAILS', ''),
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
    | DBA'nin bu ortamda uyguladigini teyit ettigi migration gruplarinin
    | virgullu listesi (docs/planning/16), orn. "B00,B01,B02,B08". Batch'ler
    | her zaman alfabetik sirayla yazilip uygulanmaz (D-62 ile B25, B03'ten
    | once kuruldu), bu yuzden bu bir "en yuksek deger" degil bir kumedir
    | (bkz. App\Services\Platform\SchemaReadiness). Ilgili gruba bagli
    | ekranlar teyit gelene kadar gizli kalir.
    */
    'applied_schema_batch' => env('KONELSIS_APPLIED_SCHEMA_BATCH'),

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
