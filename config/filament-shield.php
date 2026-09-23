<?php

declare(strict_types=1);
use App\Filament\Clusters\WorkReports;
use App\Filament\Pages\Work\ControlMatrix;
use App\Filament\Pages\Work\WorkAnalysis;
use App\Filament\Pages\Work\WorkBoard;
use App\Filament\Pages\Work\WorkDurationReport;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Filament\Widgets\UpcomingSocialContentsWidget;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [

    /*
    |--------------------------------------------------------------------------
    | Shield Resource
    |--------------------------------------------------------------------------
    |
    | Here you may configure the built-in role management resource. You can
    | customize the URL, choose whether to show model paths, group it under
    | a cluster, and decide which permission tabs to display.
    |
    */

    'shield_resource' => [
        'slug' => 'roles',
        'show_model_path' => true,
        // Roller ekrani Ayarlar kumesinde (D-69); URL /admin/settings/roles.
        'cluster' => \App\Filament\Clusters\Settings::class,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | When your application supports teams, Shield will automatically detect
    | and configure the tenant model during setup. This enables tenant-scoped
    | roles and permissions throughout your application.
    |
    */

    'tenant_model' => null,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This value contains the class name of your user model. This model will
    | be used for role assignments and must implement the HasRoles trait
    | provided by the Spatie\Permission package.
    |
    */

    'auth_provider_model' => 'App\\Models\\Personnel\\Personnel',

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Here you may define a super admin that has unrestricted access to your
    | application. You can choose to implement this via Laravel's gate system
    | or as a traditional role with all permissions explicitly assigned.
    |
    */

    // Konelsis: 'system_admin' zaten mevcut rol adi (D-63/D-64); Shield'in
    // varsayilan 'super_admin' yerine bu rol tum izinleri otomatik alir.
    'super_admin' => [
        'enabled' => true,
        'name' => 'Yönetici',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel User
    |--------------------------------------------------------------------------
    |
    | When enabled, Shield will create a basic panel user role that can be
    | assigned to users who should have access to your Filament panels but
    | don't need any specific permissions beyond basic authentication.
    |
    */

    // Konelsis: panel erisimi Personnel::canAccessPanel() ile kendi
    // rollerimize (system_admin/auditor) gore kontrol ediliyor; Shield'in
    // ayri panel_user rolune gerek yok.
    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Builder
    |--------------------------------------------------------------------------
    |
    | You can customize how permission keys are generated to match your
    | preferred naming convention and organizational standards. Shield uses
    | these settings when creating permission names from your resources.
    |
    | Supported formats: snake, kebab, pascal, camel, upper_snake, lower_snake
    |
    | Note: The separator must not conflict with the case format's own
    | delimiter. For example, `_` cannot be used with snake/lower_snake/
    | upper_snake, and `-` cannot be used with kebab.
    |
    | When `format_custom_permission_keys` is true (default), custom
    | permissions defined below will have their keys formatted according to
    | the case setting. If your custom permissions come from external sources
    | (e.g. Terraform, Keycloak) and must remain unchanged, set this to false.
    | When using the separator in custom permission definitions, each segment
    | will be formatted independently (e.g. 'view:system_log' with pascal
    | case becomes 'View:SystemLog').
    |
    */

    'permissions' => [
        'separator' => ':',
        'case' => 'pascal',
        'generate' => true,
        'format_custom_permission_keys' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | Shield can automatically generate Laravel policies for your resources.
    | Generated policies mirror each model's location: models under
    | app/Models map into the path below (keeping their nesting), models in
    | any other "Models" directory get a sibling "Policies" directory, and
    | vendor models fall back to the path below. When merge is enabled, the
    | methods below will be combined with any resource-specific methods you
    | define in the resources section.
    |
    */

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'generate' => true,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Shield supports multiple languages out of the box. When enabled, you
    | can provide translated labels for permissions to create a more
    | localized experience for your international users.
    |
    */

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Here you can fine-tune permissions for specific Filament resources.
    | Use the 'manage' array to override the default policy methods for
    | individual resources, giving you granular control over permissions.
    |
    */

    'resources' => [
        'subject' => 'model',
        'manage' => [
            RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
            // Raporlar (D-86): standart yetkilere ek olarak inceleme, gizli rapor
            // gorme ve IK gorusu yazma izinleri Roller ekraninda isaretlenir.
            ReportResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
                'submit',
                'withdraw',
                'review',
                'viewConfidential',
                'authorHrEvaluation',
            ],
            // Is panosu (B36, D-115): kartin standart yetkileri kayit duzeyinde
            // sahiplikle birlesir; ek olarak butun kartlar / Yonetim panosu,
            // haftalik kontrol matrisi ve personel kartindaki Dikkat karti.
            WorkItemResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
                'viewAll',
                'controlMatrix',
                'viewAttentionCard',
            ],
            // Sosyal Medya (D-106): silme yoktur. Standart yetkilere ek olarak
            // karar verme (onay / ret / revize), paylasildi isaretleme, arsive
            // kaldirma ve modul ayarlari izinleri Roller ekraninda isaretlenir.
            // Sorumlu personel yetkisini izinden degil gorevinden alir.
            SocialContentResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'approve',
                'publish',
                'archive',
                'manageSettings',
            ],
        ],
        'exclude' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Most Filament pages only require view permissions. Pages listed in the
    | exclude array will be skipped during permission generation and won't
    | appear in your role management interface.
    |
    */

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
            // Is panosu sayfalari (B36, D-115) erisimi WorkItem politikasindan
            // alir (canAccess); Roller ekraninda etkisiz sayfa kutusu olusmasin.
            WorkBoard::class,
            ControlMatrix::class,
            WorkAnalysis::class,
            WorkDurationReport::class,
            WorkReports::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    |
    | Like pages, widgets typically only need view permissions. Add widgets
    | to the exclude array if you don't want them to appear in your role
    | management interface.
    |
    */

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
            // Yaklasan sosyal medya icerikleri (D-106): gorunurlugu izinle degil
            // kuralla belirlenir (sorumlu gorev, tam yetki ya da kendi icerigi);
            // Roller ekraninda etkisiz bir kutu olusmasin diye izin uretilmez.
            UpcomingSocialContentsWidget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Sometimes you need permissions that don't map to resources, pages, or
    | widgets. Define any custom permissions here and they'll be available
    | when editing roles in your application.
    |
    | Keys are formatted per the Permission Builder settings above; set
    | permissions.format_custom_permission_keys to false to use them as-is.
    |
    */

    /*
    | Konelsis ozel izinleri (D-81, 11 Eylul 2026): bildirim/duyuru gonderme
    | kapsamlari. Anahtar bicimi PermissionKey::custom(): notify:team -> Notify:Team.
    */
    'custom_permissions' => [
        'notify:team' => 'Ekibine (kendisine bağlı personele) bildirim gönder',
        'notify:department' => 'Departmana bildirim gönder',
        'notify:role' => 'Bir role bildirim gönder',
        'notify:personnel' => 'Seçili personele bildirim gönder',
        'notify:all' => 'Genel duyuru yayımla (herkese)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Discovery
    |--------------------------------------------------------------------------
    |
    | By default, Shield only looks for entities in your default Filament
    | panel. Enable these options if you're using multiple panels and want
    | Shield to discover entities across all of them.
    |
    */

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Policy
    |--------------------------------------------------------------------------
    |
    | Shield can automatically register a policy for role management itself.
    | This lets you control who can manage roles using Laravel's built-in
    | authorization system. Requires a RolePolicy class in your app.
    |
    */

    'register_role_policy' => true,

];
